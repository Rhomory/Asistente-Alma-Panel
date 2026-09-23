<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use Illuminate\Console\Command;

/**
 * Puente consola → registro. Lo invoca el asistente (o un hook de Claude Code)
 * al terminar cada sección:
 *
 *   php artisan registro:add "Sitio Bodega Andina" "Inicio" "Hero" 41 --asistente=30 --dev=11 --correcciones=1 --aprobada
 */
class RegistroAdd extends Command
{
    protected $signature = 'registro:add
        {proyecto : Nombre del proyecto (se crea si no existe)}
        {pagina : Nombre de la página (se crea si no existe)}
        {seccion : Nombre de la sección}
        {minutos : Minutos totales de la sección}
        {--asistente=0 : Minutos ejecutados por el asistente}
        {--dev=0 : Minutos ejecutados por el desarrollador}
        {--widget= : Widget aplicado según la guía técnica}
        {--correcciones=0 : Número de correcciones pedidas}
        {--aprobada : Marcar la sección como aprobada}';

    protected $description = 'Registra una sección construida (lo escribe la consola, el panel solo lee)';

    public function handle(): int
    {
        $proyecto = Proyecto::firstOrCreate(['nombre' => $this->argument('proyecto')]);
        $pagina = Pagina::firstOrCreate(
            ['proyecto_id' => $proyecto->id, 'nombre' => $this->argument('pagina')],
            ['estado' => 'construyendo']
        );

        $min = (int) $this->argument('minutos');
        $asis = (int) $this->option('asistente');
        $dev = (int) $this->option('dev');
        if ($asis === 0 && $dev === 0) {
            $asis = $min; // por defecto se asume ejecución del asistente
        }

        $s = $pagina->secciones()->create([
            'nombre'        => $this->argument('seccion'),
            'widget_plan'   => $this->option('widget'),
            'ejecuto'       => $dev === 0 ? 'asistente' : ($asis === 0 ? 'desarrollador' : 'mixto'),
            'inicio'        => now()->subMinutes($min),
            'fin'           => now(),
            'minutos'       => $min,
            'min_asistente' => $asis,
            'min_dev'       => $dev,
            'correcciones'  => (int) $this->option('correcciones'),
            'aprobada'      => (bool) $this->option('aprobada'),
        ]);

        Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => "Registrada vía consola ({$min} min)"]);

        $this->info("Sección registrada: {$proyecto->nombre} / {$pagina->nombre} / {$s->nombre} — {$min} min");

        return self::SUCCESS;
    }
}
