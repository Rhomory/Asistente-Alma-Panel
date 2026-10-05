<?php

namespace App\Console\Commands;

use App\Http\Controllers\FlujoController;
use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * Puente con Figma: el asistente lee el archivo (MCP figwright) y carga de una vez páginas,
 * secciones con su ID de Figma (la "memoria" del diseño) y tokens. El panel se recarga solo.
 *
 *   php artisan registro:figma "Cota" --archivo=figma.json
 *
 * {
 *   "figma": "https://www.figma.com/design/…",
 *   "paginas": [
 *     { "nombre": "Inicio", "figma_id": "12:3", "url": "/",
 *       "secciones": [ { "nombre": "Hero", "figma_id": "12:4" }, { "nombre": "Servicios", "figma_id": "12:9" } ] },
 *     { "nombre": "Contacto", "secciones": 3 }
 *   ],
 *   "tokens": [ { "tipo": "color", "valor": "#2F6B4F", "nota": "primario" } ]
 * }
 *
 * Nada se duplica: páginas y secciones se reconocen por su ID de Figma o por su nombre.
 */
class RegistroFigma extends Command
{
    protected $signature = 'registro:figma {proyecto}
        {--json= : JSON con figma, paginas y tokens}
        {--archivo= : Ruta a un archivo .json con el mismo formato}
        {--stdin : Leer el JSON desde la entrada estándar}';

    protected $description = 'Carga páginas, secciones (con ID de Figma) y tokens leídos del diseño';

    public function handle(): int
    {
        $crudo = match (true) {
            (bool) $this->option('json')    => $this->option('json'),
            (bool) $this->option('archivo') => @file_get_contents($this->option('archivo')),
            (bool) $this->option('stdin')   => stream_get_contents(STDIN),
            default                         => null,
        };
        $datos = $crudo ? json_decode($crudo, true) : null;
        if (! is_array($datos)) {
            $this->error('Falta un JSON válido (usa --archivo, --json o --stdin). Formato en: php artisan help registro:figma');

            return self::FAILURE;
        }
        if (empty($datos['paginas']) && empty($datos['tokens']) && empty($datos['figma'])) {
            $this->warn('El JSON no trae páginas, tokens ni enlace de Figma: no se registró nada.');

            return self::SUCCESS;
        }

        $proyecto = Proyecto::firstOrCreate(['nombre' => $this->argument('proyecto')]);

        $figma = trim((string) ($datos['figma'] ?? ''));
        if ($figma !== '') {
            if (preg_match('#^https://(www\.)?figma\.com/#', $figma)) {
                $proyecto->update(['archivo_figma' => $figma]);
            } else {
                $this->warn("Enlace de Figma ignorado (debe ser https://www.figma.com/…): {$figma}");
            }
        }

        $resumen = ['paginas' => 0, 'secciones' => 0];
        foreach ($datos['paginas'] ?? [] as $p) {
            if (! is_array($p) || trim((string) ($p['nombre'] ?? '')) === '') {
                continue;
            }
            $this->cargarPagina($proyecto, $p, $resumen);
        }

        $tokens = collect($datos['tokens'] ?? [])
            ->filter(fn ($t) => is_array($t))
            ->map(fn ($t) => ($t['tipo'] ?? '') . '|' . ($t['valor'] ?? '') . '|' . ($t['nota'] ?? ''))
            ->values()->all();
        if ($tokens) {
            $this->call('registro:tokens', ['proyecto' => $proyecto->nombre, 'tokens' => $tokens]);
        }

        Evento::create(['tipo' => 'figma', 'detalle' => sprintf(
            'Figma leído en %s: %d páginas, %d secciones nuevas y %d tokens',
            $proyecto->nombre, $resumen['paginas'], $resumen['secciones'], count($tokens)
        )]);
        $proyecto->touch();

        $this->info("Listo: {$resumen['paginas']} páginas y {$resumen['secciones']} secciones nuevas en \"{$proyecto->nombre}\". El panel ya las muestra.");

        return self::SUCCESS;
    }

    private function cargarPagina(Proyecto $proyecto, array $p, array &$resumen): void
    {
        $nombre = trim($p['nombre']);
        $figmaId = isset($p['figma_id']) ? trim((string) $p['figma_id']) : null;

        $pagina = ($figmaId ? $proyecto->paginas()->where('figma_id', $figmaId)->first() : null)
            ?? $proyecto->paginas()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();

        $secciones = $p['secciones'] ?? null;
        $total = is_array($secciones) ? count($secciones) : (is_numeric($secciones) ? (int) $secciones : null);

        $campos = array_filter([
            'figma_id'        => $figmaId ?: null,
            'url'             => isset($p['url']) ? trim((string) $p['url']) : null,
            'secciones_total' => $total,
            'origen'          => 'detectada',
        ], fn ($v) => $v !== null && $v !== '');

        if ($pagina) {
            $pagina->update($campos);
        } else {
            $pagina = $proyecto->paginas()->create($campos + ['nombre' => $nombre, 'estado' => 'pendiente']);
        }
        $resumen['paginas']++;

        if (! is_array($secciones)) {
            return;
        }
        $nuevas = 0;
        foreach ($secciones as $s) {
            $s = is_string($s) ? ['nombre' => $s] : $s;
            $nombreSec = trim((string) ($s['nombre'] ?? ''));
            if ($nombreSec === '') {
                continue;
            }
            $idSec = isset($s['figma_id']) ? trim((string) $s['figma_id']) : null;

            $existente = ($idSec ? $pagina->secciones()->where('figma_id', $idSec)->first() : null)
                ?? $pagina->secciones()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombreSec)])->first();

            if ($existente) {
                if ($idSec && ! $existente->figma_id) {
                    $existente->update(['figma_id' => $idSec]);
                }
                continue;
            }
            $pagina->secciones()->create([
                'nombre' => $nombreSec, 'figma_id' => $idSec, 'estado' => 'planificada', 'ejecuto' => 'asistente',
            ]);
            $nuevas++;
        }
        $resumen['secciones'] += $nuevas;
        if ($nuevas) {
            FlujoController::reabrir($pagina);
        }
    }
}
