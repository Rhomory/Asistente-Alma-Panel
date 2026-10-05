<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * Puente con Figma: el asistente lee el archivo (MCP de Figma) y carga de una vez
 * páginas y tokens. El panel abierto se recarga solo y los muestra.
 *
 *   php artisan registro:figma "ECOCREATIONS" --json='{"figma":"https://www.figma.com/design/…",
 *       "paginas":[{"nombre":"Inicio","secciones":7}],
 *       "tokens":[{"tipo":"color","valor":"#2F6B4F","nota":"primario"}]}'
 *
 * También acepta --archivo=ruta.json o el JSON por la entrada estándar (--stdin).
 */
class RegistroFigma extends Command
{
    protected $signature = 'registro:figma {proyecto}
        {--json= : JSON con figma, paginas y tokens}
        {--archivo= : Ruta a un archivo .json con el mismo formato}
        {--stdin : Leer el JSON desde la entrada estándar}';

    protected $description = 'Carga páginas y tokens leídos del archivo de Figma (lo ejecuta el asistente)';

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
            $this->error('Falta un JSON válido (usa --json, --archivo o --stdin). Formato: {"figma":"…","paginas":[{"nombre":"Inicio","secciones":7}],"tokens":[{"tipo":"color","valor":"#2F6B4F","nota":"primario"}]}');

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

        $paginas = collect($datos['paginas'] ?? [])
            ->filter(fn ($p) => is_array($p) && trim((string) ($p['nombre'] ?? '')) !== '')
            ->map(fn ($p) => trim($p['nombre']) . (isset($p['secciones']) ? '|' . (int) $p['secciones'] : ''))
            ->values()->all();
        $tokens = collect($datos['tokens'] ?? [])
            ->filter(fn ($t) => is_array($t))
            ->map(fn ($t) => ($t['tipo'] ?? '') . '|' . ($t['valor'] ?? '') . '|' . ($t['nota'] ?? ''))
            ->values()->all();

        if ($paginas) {
            $this->call('registro:paginas', ['proyecto' => $proyecto->nombre, 'paginas' => $paginas]);
        }
        if ($tokens) {
            $this->call('registro:tokens', ['proyecto' => $proyecto->nombre, 'tokens' => $tokens]);
        }

        Evento::create(['tipo' => 'figma', 'detalle' => sprintf(
            'Figma leído en %s: %d páginas y %d tokens', $proyecto->nombre, count($paginas), count($tokens)
        )]);
        $proyecto->touch();

        $this->info("Listo: el panel de \"{$proyecto->nombre}\" ya muestra lo leído del Figma.");

        return self::SUCCESS;
    }
}
