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
    protected $signature = 'registro:cola';
    protected $description = 'Lista las correcciones pedidas desde el panel y pendientes de atender';

    public function handle(): int
    {
        $pendientes = Evento::with('seccion.pagina.proyecto')
            ->where('tipo', 'solicitud_correccion')->where('resuelto', false)
            ->orderBy('created_at')->get();

        if ($pendientes->isEmpty()) {
            $this->info('Cola vacía: no hay correcciones pendientes.');

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
        $this->line('Atiende cada una y re-registra la sección con: php artisan registro:add "proyecto" "página" "sección" <min> ...');

        return self::SUCCESS;
    }
}
