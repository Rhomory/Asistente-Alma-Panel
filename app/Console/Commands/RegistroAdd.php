<?php

namespace App\Console\Commands;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Support\NombresProyecto;
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
        {--aprobada : Marcar la sección como aprobada}
        {--forzar-variante : Crear el proyecto aunque se parezca a otro (solo si el usuario lo confirma)}';

    protected $description = 'Registra una sección construida (lo escribe la consola, el panel solo lee)';

    public function handle(): int
    {
        $proyecto = NombresProyecto::paraConsola($this, $this->argument('proyecto'));
        if (! $proyecto) {
            return self::FAILURE;
        }
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

        $valores = [
            'widget_plan'   => $this->option('widget'),
            'ejecuto'       => $dev === 0 ? 'asistente' : ($asis === 0 ? 'desarrollador' : 'mixto'),
            'inicio'        => now()->subMinutes($min),
            'fin'           => now(),
            'minutos'       => $min,
            'min_asistente' => $asis,
            'min_dev'       => $dev,
            'estado'        => $this->option('aprobada') ? 'aprobada' : 'construida',
            'aprobada'      => (bool) $this->option('aprobada'),
        ];

        // Si la sección ya estaba en el plan (planificada) o en corrección
        // (construyendo), se ACTUALIZA en lugar de duplicarse.
        $s = $pagina->secciones()
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($this->argument('seccion'))])
            ->whereIn('estado', ['planificada', 'construyendo'])
            ->first();

        if ($s) {
            $reconstruccion = $s->estado === 'construyendo';
            if ($valores['widget_plan'] === null) {
                unset($valores['widget_plan']); // conserva el mapeo del plan
            }
            $s->update($valores + ['correcciones' => $s->correcciones + (int) $this->option('correcciones')]);
            Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => $reconstruccion ? "Corrección aplicada vía consola ({$min} min)" : "Construida según el plan ({$min} min)"]);
            // La corrección atendida sale de la cola.
            Evento::where('seccion_id', $s->id)->where('tipo', 'solicitud_correccion')->where('resuelto', false)->update(['resuelto' => true]);
        } else {
            $s = $pagina->secciones()->create($valores + [
                'nombre'       => $this->argument('seccion'),
                'correcciones' => (int) $this->option('correcciones'),
            ]);
            Evento::create(['seccion_id' => $s->id, 'tipo' => 'construccion', 'detalle' => "Registrada vía consola ({$min} min)"]);
        }

        if ($pagina->estado === 'pendiente') {
            $pagina->update(['estado' => 'construyendo']); // el panel refleja el avance real
        }

        $this->info("Sección registrada: {$proyecto->nombre} / {$pagina->nombre} / {$s->nombre} — {$min} min");

        return self::SUCCESS;
    }
}
