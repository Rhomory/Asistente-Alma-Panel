<?php

namespace App\Console\Commands;

use App\Models\Evento;
use Illuminate\Console\Command;

/**
 * Cola de trabajo para la consola: correcciones solicitadas desde el panel
 * que aún no fueron atendidas. Al re-registrar la sección con registro:add,
 * la solicitud se marca resuelta automáticamente.
 */
class RegistroCola extends Command
{
    protected $signature = 'registro:cola {proyecto? : Solo las correcciones de este proyecto (recomendado)}';
    protected $description = 'Lista las correcciones pedidas desde el panel y pendientes de atender';

    public function handle(): int
    {
        $proyecto = null;
        if ($nombre = $this->argument('proyecto')) {
            $proyecto = \App\Support\NombresProyecto::buscar($nombre);
            if (! $proyecto) {
                $this->error("No existe el proyecto \"{$nombre}\". Proyectos: " . \App\Models\Proyecto::pluck('nombre')->implode(', '));

                return self::FAILURE;
            }
        }
        $pendientes = Evento::with('seccion.pagina.proyecto')
            ->where('tipo', 'solicitud_correccion')->where('resuelto', false)
            ->when($proyecto, fn ($q, $pr) => $q->whereHas('seccion.pagina', fn ($p) => $p->where('proyecto_id', $pr->id)))
            ->orderBy('created_at')->get();

        if ($pendientes->isEmpty()) {
            $this->info('Cola vacía: no hay correcciones pendientes' . ($proyecto ? ' en "' . $proyecto->nombre . '".' : '.'));

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Proyecto', 'Página', 'Sección', 'Pedido', 'Corrección solicitada'],
            $pendientes->map(fn ($e, $i) => [
                $i + 1,
                $e->seccion?->pagina?->proyecto?->nombre ?? '—',
                $e->seccion?->pagina?->nombre ?? '—',
                $e->seccion?->nombre ?? '—',
                $e->created_at->format('d/m H:i'),
                $e->detalle,
            ])
        );
        $this->line('Atiende cada una (edita la sección que ya existe) y vuelve a registrarla con registro:add "<proyecto>" "<página>" "<sección>" <min> --asistente=<min> --dev=<min>.');

        return self::SUCCESS;
    }
}
