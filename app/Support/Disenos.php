<?php

namespace App\Support;

use App\Models\Diseno;
use App\Models\Evento;
use App\Models\Proyecto;

/**
 * Versiones del diseño. Cada lectura del Figma se guarda como Design/vN (lectura.json + capturas/) y como
 * registro en el panel. La primera se adopta sola; las siguientes quedan "nuevas" hasta que alguien pulsa
 * "Usar esta versión": así el panel no cambia cada vez que el agente relee el Figma.
 */
class Disenos
{
    public static function guardar(Proyecto $proyecto, array $datos): Diseno
    {
        $version = ((int) $proyecto->disenos()->max('version')) + 1;
        $diseno = $proyecto->disenos()->create([
            'version' => $version,
            'estado'  => 'nueva',
            'datos'   => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resumen' => self::conteo($datos),
        ]);

        $dir = CarpetaProyecto::carpetaVersion($proyecto, $version);
        if ($dir) {
            file_put_contents($dir . '/lectura.json', json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        Evento::create(['tipo' => 'figma', 'detalle' => "Diseño v{$version} de {$proyecto->nombre} guardado: "
            . self::texto($diseno->resumen)]);

        if (! $proyecto->disenos()->where('estado', 'activa')->exists()) {
            self::adoptar($diseno);
        }
        $proyecto->touch();

        return $diseno->fresh();
    }

    /** Aplica la versión al panel (páginas, secciones, tokens) y la marca como activa. */
    public static function adoptar(Diseno $diseno): array
    {
        $proyecto = $diseno->proyecto;
        $resumen = CargaFigma::aplicar($proyecto, $diseno->lectura());

        $proyecto->disenos()->where('estado', 'activa')->update(['estado' => 'anterior']);
        $diseno->update(['estado' => 'activa', 'adoptada_en' => now()]);

        Evento::create(['tipo' => 'figma', 'detalle' => "Diseño v{$diseno->version} en uso en {$proyecto->nombre}: "
            . "{$resumen['paginas']} páginas, {$resumen['secciones']} secciones nuevas y {$resumen['tokens']} tokens nuevos"]);
        CarpetaProyecto::preparar($proyecto->fresh());
        $proyecto->touch();

        return $resumen;
    }

    /**
     * Qué cambia respecto de la versión activa: páginas y secciones nuevas o que ya no están.
     *
     * @return array{paginas_nuevas: string[], paginas_quitadas: string[], secciones_nuevas: string[], secciones_quitadas: string[]}
     */
    public static function diferencias(Diseno $nueva, ?Diseno $activa): array
    {
        [$pn, $sn] = self::mapa($nueva->lectura());
        [$pa, $sa] = $activa ? self::mapa($activa->lectura()) : [[], []];

        return [
            'paginas_nuevas'     => array_values(array_diff($pn, $pa)),
            'paginas_quitadas'   => array_values(array_diff($pa, $pn)),
            'secciones_nuevas'   => array_values(array_diff($sn, $sa)),
            'secciones_quitadas' => array_values(array_diff($sa, $sn)),
        ];
    }

    public static function texto(?array $r): string
    {
        $r ??= [];

        return ($r['paginas'] ?? 0) . ' páginas, ' . ($r['secciones'] ?? 0) . ' secciones y ' . ($r['tokens'] ?? 0) . ' tokens';
    }

    private static function conteo(array $datos): array
    {
        $paginas = array_filter($datos['paginas'] ?? [], 'is_array');

        return [
            'paginas'   => count($paginas),
            'secciones' => array_sum(array_map(fn ($p) => is_array($p['secciones'] ?? null) ? count($p['secciones']) : 0, $paginas)),
            'tokens'    => count(array_filter($datos['tokens'] ?? [], 'is_array')),
        ];
    }

    /** @return array{0: string[], 1: string[]} nombres de páginas y "Página › Sección" */
    private static function mapa(array $datos): array
    {
        $paginas = [];
        $secciones = [];
        foreach ($datos['paginas'] ?? [] as $p) {
            if (! is_array($p) || empty($p['nombre'])) {
                continue;
            }
            $paginas[] = trim($p['nombre']);
            foreach (is_array($p['secciones'] ?? null) ? $p['secciones'] : [] as $s) {
                $nombre = is_string($s) ? $s : ($s['nombre'] ?? '');
                if ($nombre !== '') {
                    $secciones[] = trim($p['nombre']) . ' › ' . trim($nombre);
                }
            }
        }

        return [$paginas, $secciones];
    }
}
