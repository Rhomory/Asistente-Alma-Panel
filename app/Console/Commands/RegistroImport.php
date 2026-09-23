<?php

namespace App\Console\Commands;

use App\Models\Pagina;
use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * Importa la plantilla CSV del piloto (allegado del proyecto de mejora):
 * fecha,proyecto,pagina,seccion,hora_inicio,hora_fin,minutos,ejecuto,correcciones,aprobada,notas
 */
class RegistroImport extends Command
{
    protected $signature = 'registro:import {csv : Ruta del archivo CSV}';
    protected $description = 'Importa el registro de tiempos desde la plantilla CSV del piloto';

    public function handle(): int
    {
        $ruta = $this->argument('csv');
        if (! is_readable($ruta)) {
            $this->error("No se puede leer: {$ruta}");

            return self::FAILURE;
        }

        $f = fopen($ruta, 'r');
        $cab = fgetcsv($f); // encabezado
        $col = array_flip(array_map('trim', $cab));
        $n = 0;

        while (($fila = fgetcsv($f)) !== false) {
            if (count($fila) < 7 || trim($fila[$col['proyecto']]) === '') {
                continue;
            }
            $proyecto = Proyecto::firstOrCreate(['nombre' => trim($fila[$col['proyecto']])]);
            $pagina = Pagina::firstOrCreate(
                ['proyecto_id' => $proyecto->id, 'nombre' => trim($fila[$col['pagina']])],
                ['estado' => 'construyendo']
            );

            $min = (int) $fila[$col['minutos']];
            $ejecuto = strtolower(trim($fila[$col['ejecuto']] ?? 'asistente')) ?: 'asistente';
            [$asis, $dev] = match ($ejecuto) {
                'desarrollador' => [0, $min],
                'mixto'         => [intdiv($min, 2), $min - intdiv($min, 2)],
                default         => [$min, 0],
            };

            $fecha = trim($fila[$col['fecha']] ?? '') ?: now()->toDateString();
            $hi = trim($fila[$col['hora_inicio']] ?? '') ?: '09:00';
            $hf = trim($fila[$col['hora_fin']] ?? '') ?: null;

            $pagina->secciones()->create([
                'nombre'        => trim($fila[$col['seccion']]),
                'ejecuto'       => $ejecuto,
                'inicio'        => "{$fecha} {$hi}",
                'fin'           => $hf ? "{$fecha} {$hf}" : null,
                'minutos'       => $min,
                'min_asistente' => $asis,
                'min_dev'       => $dev,
                'correcciones'  => (int) ($fila[$col['correcciones']] ?? 0),
                'aprobada'      => strtolower(trim($fila[$col['aprobada']] ?? '')) === 'si',
            ]);
            $n++;
        }
        fclose($f);

        $this->info("Importadas {$n} secciones desde {$ruta}");

        return self::SUCCESS;
    }
}
