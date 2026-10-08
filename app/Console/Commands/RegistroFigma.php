<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LeeJson;
use App\Models\Evento;
use App\Models\Proyecto;
use App\Support\NombresProyecto;
use App\Support\CargaFigma;
use Illuminate\Console\Command;

/**
 * Carga directa de una lectura del Figma al panel (páginas, secciones con su ID y tokens), sin versionar.
 * Para el flujo normal usa diseno:guardar, que además guarda la versión en Design/vN.
 * Formato del JSON: ver la skill alma-figma o `php artisan help diseno:guardar`.
 */
class RegistroFigma extends Command
{
    use LeeJson;

    protected $signature = 'registro:figma {proyecto}
        {--json= : JSON con figma, paginas y tokens}
        {--archivo= : Ruta a un archivo .json con el mismo formato}
        {--stdin : Leer el JSON desde la entrada estándar}
        {--forzar-variante : Crear el proyecto aunque se parezca a otro (solo si el usuario lo confirma)}';

    protected $description = 'Carga páginas, secciones (con ID de Figma) y tokens leídos del diseño, sin versionar';

    public function handle(): int
    {
        $datos = $this->leerJson();
        if ($datos === null) {
            $this->error('Falta un JSON válido (usa --archivo, --json o --stdin).');

            return self::FAILURE;
        }
        if (empty($datos['paginas']) && empty($datos['tokens']) && empty($datos['figma'])) {
            $this->warn('El JSON no trae páginas, tokens ni enlace de Figma: no se registró nada.');

            return self::SUCCESS;
        }

        $proyecto = NombresProyecto::paraConsola($this, $this->argument('proyecto'));
        if (! $proyecto) {
            return self::FAILURE;
        }
        $r = CargaFigma::aplicar($proyecto, $datos);
        foreach ($r['avisos'] as $aviso) {
            $this->warn($aviso);
        }

        Evento::create(['tipo' => 'figma', 'detalle' => sprintf(
            'Figma leído en %s: %d páginas, %d secciones nuevas y %d tokens', $proyecto->nombre, $r['paginas'], $r['secciones'], $r['tokens']
        )]);
        $proyecto->touch();

        $this->info("Listo: {$r['paginas']} páginas y {$r['secciones']} secciones nuevas en \"{$proyecto->nombre}\". El panel ya las muestra.");

        return self::SUCCESS;
    }
}
