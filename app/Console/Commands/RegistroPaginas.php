<?php

namespace App\Console\Commands;

use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * El asistente registra las páginas que detectó al leer el archivo de Figma:
 *
 *   php artisan registro:paginas "Sitio Bodega Andina" "Inicio|7" "Nosotros|5" "Contacto|3"
 *
 * Cada página va como "Nombre|secciones" (las secciones son opcionales).
 * Las existentes se actualizan (n.º de secciones), nunca se duplican; en el
 * panel aparecen como "detectadas" y ahí se decide cuáles se maquetan.
 */
class RegistroPaginas extends Command
{
    protected $signature = 'registro:paginas {proyecto} {paginas*}';
    protected $description = 'Registra las páginas detectadas en el diseño (las escribe el asistente)';

    public function handle(): int
    {
        $proyecto = Proyecto::firstOrCreate(['nombre' => $this->argument('proyecto')]);
        $nuevas = $actualizadas = 0;

        foreach ($this->argument('paginas') as $entrada) {
            [$nombre, $secciones] = array_pad(explode('|', $entrada, 2), 2, null);
            $nombre = trim($nombre);
            if ($nombre === '') {
                continue;
            }

            $pagina = $proyecto->paginas()->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first();
            if ($pagina) {
                $pagina->update(array_filter([
                    'secciones_total' => $secciones !== null ? (int) $secciones : null,
                    'origen'          => 'detectada',
                ], fn ($v) => $v !== null));
                $actualizadas++;
            } else {
                $proyecto->paginas()->create([
                    'nombre'          => $nombre,
                    'secciones_total' => (int) ($secciones ?? 0),
                    'estado'          => 'pendiente',
                    'origen'          => 'detectada',
                ]);
                $nuevas++;
            }
        }

        $this->info("Páginas de \"{$proyecto->nombre}\": {$nuevas} detectadas nuevas, {$actualizadas} actualizadas. Marca en el panel cuáles se maquetan.");

        return self::SUCCESS;
    }
}
