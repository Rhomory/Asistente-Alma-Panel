<?php

namespace App\Console\Commands;

use App\Models\Proyecto;
use App\Support\NombresProyecto;
use App\Support\PromptGuia;
use Illuminate\Console\Command;

/**
 * Imprime la guía actualizada (tokens activos, plan de secciones y reglas) para que el
 * asistente la lea antes de construir una página:
 *
 *   php artisan guia:prompt "ECOCREATIONS" "Inicio"
 */
class GuiaPrompt extends Command
{
    protected $signature = 'guia:prompt {proyecto} {pagina? : Página a construir (opcional)}
        {--siguiente : Solo la próxima sección por construir (para retomar con poco contexto)}';
    protected $description = 'Muestra la guía y el prompt de construcción de un proyecto o página';

    public function handle(): int
    {
        $proyecto = NombresProyecto::buscar($this->argument('proyecto'));
        if (! $proyecto) {
            $this->error('No existe el proyecto "' . $this->argument('proyecto') . '". Proyectos: ' . Proyecto::pluck('nombre')->implode(', '));

            return self::FAILURE;
        }

        $pagina = null;
        if ($nombre = $this->argument('pagina')) {
            $pagina = NombresProyecto::pagina($proyecto, $nombre);
            if (! $pagina) {
                $this->error("La página \"{$nombre}\" no está en {$proyecto->nombre}. Páginas: " . $proyecto->paginas()->pluck('nombre')->implode(', '));

                return self::FAILURE;
            }
        }

        if ($this->option('siguiente')) {
            if (! $pagina) {
                $this->error('--siguiente necesita la página: guia:prompt "' . $proyecto->nombre . '" "<Página>" --siguiente');

                return self::FAILURE;
            }
            $this->line(PromptGuia::siguiente($proyecto, $pagina));

            return self::SUCCESS;
        }

        $this->line(PromptGuia::prompt($proyecto, $pagina));

        return self::SUCCESS;
    }
}
