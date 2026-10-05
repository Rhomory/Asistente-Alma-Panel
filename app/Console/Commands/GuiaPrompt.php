<?php

namespace App\Console\Commands;

use App\Models\Proyecto;
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
    protected $signature = 'guia:prompt {proyecto} {pagina? : Página a construir (opcional)}';
    protected $description = 'Muestra la guía y el prompt de construcción de un proyecto o página';

    public function handle(): int
    {
        $proyecto = Proyecto::where('nombre', $this->argument('proyecto'))->first();
        if (! $proyecto) {
            $this->error('No existe el proyecto "' . $this->argument('proyecto') . '". Proyectos: ' . Proyecto::pluck('nombre')->implode(', '));

            return self::FAILURE;
        }

        $pagina = null;
        if ($nombre = $this->argument('pagina')) {
            $pagina = $proyecto->paginas()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
            if (! $pagina) {
                $this->error("La página \"{$nombre}\" no está en {$proyecto->nombre}. Páginas: " . $proyecto->paginas()->pluck('nombre')->implode(', '));

                return self::FAILURE;
            }
        }

        $this->line(PromptGuia::prompt($proyecto, $pagina));

        return self::SUCCESS;
    }
}
