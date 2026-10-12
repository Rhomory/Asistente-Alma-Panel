<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LeeJson;
use App\Models\Proyecto;
use App\Support\NombresProyecto;
use App\Support\CarpetaProyecto;
use App\Support\Disenos;
use App\Support\LecturaFigma;
use Illuminate\Console\Command;

/**
 * Guarda una lectura del Figma como nueva versión del diseño (Design/vN/lectura.json + capturas/).
 * La primera versión se usa al instante; las siguientes esperan a que el desarrollador pulse
 * "Usar esta versión" en el panel.
 *
 *   php artisan diseno:guardar "Cota" --archivo=figma.json
 *
 * {
 *   "figma": "https://www.figma.com/design/…",
 *   "paginas": [ { "nombre": "Inicio", "figma_id": "12:3", "url": "/",
 *                  "secciones": [ { "nombre": "Hero", "figma_id": "12:4" } ] } ],
 *   "tokens": [ { "tipo": "color", "valor": "#2F6B4F", "nota": "primario" } ]
 * }
 */
class DisenoGuardar extends Command
{
    use LeeJson;

    protected $signature = 'diseno:guardar {proyecto}
        {--json= : JSON con figma, paginas y tokens}
        {--archivo= : Ruta a un archivo .json con el mismo formato}
        {--stdin : Leer el JSON desde la entrada estándar}';

    protected $description = 'Guarda una lectura del Figma como nueva versión del diseño del proyecto';

    public function handle(): int
    {
        $crudo = $this->leerJson();
        if ($crudo === null) {
            $this->error($this->errorJson);

            return self::FAILURE;
        }

        $proyecto = NombresProyecto::buscar($this->argument('proyecto'));
        if (! $proyecto) {
            $this->error('No existe el proyecto "' . $this->argument('proyecto') . '". Créalo primero en el panel.');

            return self::FAILURE;
        }

        ['datos' => $datos, 'avisos' => $avisos] = LecturaFigma::normalizar($crudo);
        foreach ($avisos as $aviso) {
            $this->warn($aviso);
        }
        if (! $datos['paginas']) {
            $this->error('La lectura no tiene páginas: no se guardó ninguna versión. Formato esperado (claves en español):');
            $this->line('{"figma": "https://www.figma.com/design/…", "paginas": [{"nombre": "Inicio", "figma_id": "12:3", "url": "/", '
                . '"secciones": [{"nombre": "Hero", "figma_id": "12:4"}]}], "tokens": [{"tipo": "color", "valor": "#2F6B4F", "nota": "primario"}]}');

            return self::FAILURE;
        }

        // Reintentar con la misma lectura no crea otra versión.
        $ultima = $proyecto->disenos()->orderByDesc('version')->first();
        if ($ultima && $ultima->lectura() == $datos) {
            $this->info("Es igual al diseño v{$ultima->version}: no se creó otra versión. " . Disenos::texto($ultima->resumen) . '.');

            return self::SUCCESS;
        }

        $d = Disenos::guardar($proyecto, $datos);
        $carpeta = CarpetaProyecto::aWindows(CarpetaProyecto::ruta($proyecto->fresh()));

        $this->info("Diseño v{$d->version} guardado: " . Disenos::texto($d->resumen) . '.');
        $this->line($d->estado === 'activa'
            ? 'Es la versión en uso: el panel ya muestra sus páginas y secciones.'
            : 'Queda como versión nueva: el panel la usará cuando el desarrollador pulse "Usar esta versión".');
        if ($carpeta) {
            $this->line("Guarda las capturas de cada página en: {$carpeta}\\Design\\v{$d->version}\\capturas");
        }

        return self::SUCCESS;
    }
}
