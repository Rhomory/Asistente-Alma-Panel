<?php

namespace App\Support;

use App\Models\Conexion;
use App\Models\Diseno;
use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Seccion;
use App\Models\Token;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Respaldo liviano de un proyecto al eliminarlo: un .json.gz de pocos KB con el proyecto, sus páginas, secciones
 * (con tiempos), tokens, versiones del diseño, conexiones y eventos. No lleva contraseñas (el panel nunca las
 * guarda) ni las capturas PNG, que son lo pesado. Se restaura con `php artisan proyecto:restaurar`.
 *
 * Se guarda en <carpeta de proyectos>\_archivo\ (fuera del panel, sobrevive a reinstalarlo) o, si no hay carpeta
 * base, en storage/app/archivo.
 */
class ArchivoProyecto
{
    public const FORMATO = 1;

    public static function carpeta(): string
    {
        $base = CarpetaProyecto::disponible() ? CarpetaProyecto::base() . '/_archivo' : storage_path('app/archivo');
        if (! is_dir($base)) {
            mkdir($base, 0775, true);
        }

        return $base;
    }

    public static function exportar(Proyecto $p): array
    {
        $fila = fn (Model $m, array $quitar = []) => Arr::except($m->getAttributes(), array_merge(['id'], $quitar));
        $paginas = Pagina::where('proyecto_id', $p->id)->orderBy('id')->get();
        $secciones = Seccion::whereIn('pagina_id', $paginas->pluck('id'))->orderBy('id')->get();

        return [
            'formato'     => self::FORMATO,
            'archivado'   => now()->toIso8601String(),
            'proyecto'    => $fila($p, ['slug', 'carpeta']),
            'paginas'     => $paginas->map(fn ($pg) => $fila($pg, ['proyecto_id']) + [
                '_secciones' => $secciones->where('pagina_id', $pg->id)->values()->map(fn ($s) => $fila($s, ['pagina_id']) + [
                    '_eventos' => Evento::where('seccion_id', $s->id)->orderBy('id')->get()->map(fn ($e) => $fila($e, ['seccion_id']))->all(),
                ])->all(),
            ])->all(),
            'tokens'      => Token::where('proyecto_id', $p->id)->orderBy('id')->get()->map(fn ($t) => $fila($t, ['proyecto_id']))->all(),
            'conexiones'  => Conexion::where('proyecto_id', $p->id)->orderBy('id')->get()->map(fn ($c) => $fila($c, ['proyecto_id', 'credencial_en_equipo']))->all(),
            'disenos'     => Diseno::where('proyecto_id', $p->id)->orderBy('version')->get()->map(fn ($d) => $fila($d, ['proyecto_id']))->all(),
        ];
    }

    /** Escribe el respaldo y devuelve su ruta (vista desde WSL). */
    public static function guardar(Proyecto $p): string
    {
        $ruta = self::carpeta() . '/' . ($p->slug ?: 'proyecto') . '-' . now('America/Lima')->format('Ymd-His') . '.json.gz';
        $json = json_encode(self::exportar($p), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($ruta, gzencode($json, 9)) === false) {
            throw new RuntimeException('No se pudo escribir el respaldo en ' . $ruta);
        }

        return $ruta;
    }

    /** @return array<int, array{ruta: string, nombre: string, bytes: int, fecha: int}> los respaldos disponibles, el más nuevo primero */
    public static function lista(): array
    {
        return collect(glob(self::carpeta() . '/*.json.gz') ?: [])
            ->map(fn ($r) => ['ruta' => $r, 'nombre' => basename($r), 'bytes' => filesize($r), 'fecha' => filemtime($r)])
            ->sortByDesc('fecha')->values()->all();
    }

    public static function leer(string $ruta): array
    {
        $datos = is_file($ruta) ? json_decode((string) gzdecode((string) file_get_contents($ruta)), true) : null;
        if (! is_array($datos) || ($datos['formato'] ?? null) !== self::FORMATO || empty($datos['proyecto']['nombre'])) {
            throw new RuntimeException('El archivo no es un respaldo válido del Asistente Alma.');
        }

        return $datos;
    }

    /** Vuelve a crear el proyecto con todos sus registros. Falla si ya existe uno con ese nombre. */
    public static function restaurar(string $ruta): Proyecto
    {
        $d = self::leer($ruta);
        if ($otro = NombresProyecto::buscar($d['proyecto']['nombre'])) {
            throw new RuntimeException("Ya existe el proyecto \"{$otro->nombre}\". Elimínalo o renómbralo antes de restaurar.");
        }

        return DB::transaction(function () use ($d) {
            $p = self::crear(new Proyecto, $d['proyecto']);
            foreach ($d['paginas'] as $pg) {
                $pagina = self::crear(new Pagina, Arr::except($pg, '_secciones') + ['proyecto_id' => $p->id]);
                foreach ($pg['_secciones'] as $s) {
                    $seccion = self::crear(new Seccion, Arr::except($s, '_eventos') + ['pagina_id' => $pagina->id]);
                    foreach ($s['_eventos'] as $e) {
                        self::crear(new Evento, $e + ['seccion_id' => $seccion->id]);
                    }
                }
            }
            foreach (['tokens' => Token::class, 'conexiones' => Conexion::class, 'disenos' => Diseno::class] as $clave => $clase) {
                foreach ($d[$clave] as $fila) {
                    self::crear(new $clase, $fila + ['proyecto_id' => $p->id]);
                }
            }

            return $p;
        });
    }

    /** Guarda los valores tal como se exportaron (sin volver a pasar por los casts). */
    private static function crear(Model $m, array $atributos): Model
    {
        $m->setRawAttributes($atributos);
        $m->save();

        return $m;
    }
}
