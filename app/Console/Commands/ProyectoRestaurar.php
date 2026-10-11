<?php

namespace App\Console\Commands;

use App\Support\ArchivoProyecto;
use App\Support\CarpetaProyecto;
use Illuminate\Console\Command;

/**
 * Restaura un proyecto eliminado desde su respaldo (.json.gz):
 *
 *   php artisan proyecto:restaurar                    → lista los respaldos disponibles
 *   php artisan proyecto:restaurar cota-20261010-0930 → restaura ese respaldo (nombre o ruta del archivo)
 *
 * Vuelve a crear páginas, secciones, tiempos, tokens, versiones del diseño y conexiones. La carpeta del proyecto se
 * regenera (AGENTS.md y configuración); las capturas no venían en el respaldo y se vuelven a exportar al leer el Figma.
 */
class ProyectoRestaurar extends Command
{
    protected $signature = 'proyecto:restaurar {archivo? : Nombre o ruta del respaldo; sin él, lista los disponibles}';
    protected $description = 'Restaura un proyecto eliminado desde su respaldo';

    public function handle(): int
    {
        $lista = ArchivoProyecto::lista();
        $archivo = $this->argument('archivo');

        if (! $archivo) {
            if (! $lista) {
                $this->info('No hay respaldos en ' . ArchivoProyecto::carpeta() . '.');

                return self::SUCCESS;
            }
            $this->table(['Respaldo', 'Tamaño', 'Fecha'], array_map(fn ($r) => [
                preg_replace('/\.json\.gz$/', '', $r['nombre']),
                max(1, (int) round($r['bytes'] / 1024)) . ' KB',
                date('d/m/Y H:i', $r['fecha']),
            ], $lista));
            $this->line('Restaura uno con: proyecto:restaurar <respaldo>');

            return self::SUCCESS;
        }

        $ruta = is_file($archivo) ? $archivo : collect($lista)->first(fn ($r) => in_array($archivo, [$r['nombre'], preg_replace('/\.json\.gz$/', '', $r['nombre'])], true))['ruta'] ?? null;
        if (! $ruta) {
            $this->error("No encuentro el respaldo \"{$archivo}\". Ejecuta proyecto:restaurar sin argumentos para ver la lista.");

            return self::FAILURE;
        }

        try {
            $p = ArchivoProyecto::restaurar($ruta);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $archivos = CarpetaProyecto::preparar($p);
        $this->info("Proyecto \"{$p->nombre}\" restaurado: {$p->paginas()->count()} páginas, {$p->tokens()->count()} tokens.");
        if ($archivos) {
            $this->line('Carpeta regenerada en ' . CarpetaProyecto::rutaWindows($p) . '. Las capturas se vuelven a exportar al leer el Figma.');
        }
        if ($p->conexion) {
            $this->line('La credencial no viaja en el respaldo: vuelve a pegar el prompt de Elementor en Conexión.');
        }

        return self::SUCCESS;
    }
}
