<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Seccion;
use Illuminate\Http\Request;

/**
 * Pantallas Plan + Construcción de una página (P3 y P4 del mockup).
 * El panel planifica, aprueba y pide correcciones; la CONSTRUCCIÓN y sus
 * tiempos los escribe la consola (registro:add). Las correcciones pedidas
 * quedan en una cola que la consola lee con `php artisan registro:cola`.
 */
class FlujoController extends Controller
{
    /** Secciones estándar de la guía técnica de construcción (v1). */
    private const PLAN_ESTANDAR = [
        ['Hero',                 'Contenedor + Heading + Button',    12],
        ['Servicios / destacados', 'Grid + Icon Box',                10],
        ['Contenido principal',  'Contenedor + Image + Text Editor',  8],
        ['Testimonios',          'Carousel',                          8],
        ['Galería / portafolio', 'Gallery + Heading',                 8],
        ['Llamado a la acción',  'Contenedor + Button',               5],
        ['Pie de página',        'Template de sitio (footer)',        5],
    ];

    public function pagina(Pagina $pagina)
    {
        $pagina->load(['proyecto', 'secciones' => fn ($q) => $q->orderByRaw("CASE estado WHEN 'planificada' THEN 2 ELSE 1 END")->orderBy('id')]);

        $pendientes = Evento::where('tipo', 'solicitud_correccion')->where('resuelto', false)
            ->whereIn('seccion_id', $pagina->secciones->pluck('id'))->get()->groupBy('seccion_id');

        $construidas = $pagina->secciones->whereIn('estado', ['construida', 'aprobada']);

        return view('panel.pagina', [
            'pagina'      => $pagina,
            'pendientes'  => $pendientes,
            'resumen'     => [
                'estimado'  => $pagina->secciones->sum(fn ($s) => $s->min_estimado ?? 0),
                'real'      => $construidas->sum('minutos'),
                'aprobadas' => $pagina->secciones->where('estado', 'aprobada')->count(),
                'total'     => $pagina->secciones->count(),
            ],
            'enVivo'      => $pagina->estado === 'construyendo',
        ]);
    }

    public function planEstandar(Pagina $pagina)
    {
        $existentes = $pagina->secciones()->pluck('nombre')->map(fn ($n) => mb_strtolower($n))->all();
        $creadas = 0;
        foreach (self::PLAN_ESTANDAR as [$nombre, $widget, $est]) {
            if (! in_array(mb_strtolower($nombre), $existentes, true)) {
                $pagina->secciones()->create([
                    'nombre' => $nombre, 'widget_plan' => $widget,
                    'min_estimado' => $est, 'estado' => 'planificada', 'ejecuto' => 'asistente',
                ]);
                $creadas++;
            }
        }

        return back()->with('ok', "Plan estándar aplicado: {$creadas} secciones planificadas según la guía técnica.");
    }

    public function agregarPlan(Request $request, Pagina $pagina)
    {
        $datos = $request->validate([
            'nombre'       => 'required|string|max:120',
            'widget_plan'  => 'nullable|string|max:160',
            'min_estimado' => 'nullable|integer|min:1|max:480',
        ], ['nombre.required' => 'La sección necesita un nombre.']);

        $pagina->secciones()->create($datos + ['estado' => 'planificada', 'ejecuto' => 'asistente']);

        return back()->with('ok', "Sección \"{$datos['nombre']}\" agregada al plan.");
    }

    public function eliminarPlan(Seccion $seccion)
    {
        abort_unless($seccion->estado === 'planificada', 422, 'Solo se pueden quitar secciones aún no construidas.');
        $nombre = $seccion->nombre;
        $seccion->delete();

        return back()->with('ok', "Sección planificada \"{$nombre}\" eliminada del plan.");
    }

    public function aprobar(Seccion $seccion)
    {
        abort_unless($seccion->estado === 'construida', 422, 'Solo se aprueba una sección ya construida.');
        $seccion->update(['estado' => 'aprobada', 'aprobada' => true]);
        Evento::create(['seccion_id' => $seccion->id, 'tipo' => 'aprobacion', 'detalle' => 'Aprobada desde el panel']);

        $pagina = $seccion->pagina;
        $todas = $pagina->secciones()->count();
        if ($todas > 0 && $pagina->secciones()->where('estado', 'aprobada')->count() === $todas) {
            $pagina->update(['estado' => 'aprobada']);
        }

        return back()->with('ok', "Sección \"{$seccion->nombre}\" aprobada.");
    }

    public function pedirCorreccion(Request $request, Seccion $seccion)
    {
        $datos = $request->validate(
            ['detalle' => 'required|string|max:500'],
            ['detalle.required' => 'Describe qué debe corregirse.']
        );
        abort_unless(in_array($seccion->estado, ['construida', 'aprobada'], true), 422, 'La sección aún no fue construida.');

        Evento::create([
            'seccion_id' => $seccion->id, 'tipo' => 'solicitud_correccion',
            'detalle' => $datos['detalle'], 'resuelto' => false,
        ]);
        $seccion->update(['estado' => 'construyendo', 'aprobada' => false, 'correcciones' => $seccion->correcciones + 1]);
        $seccion->pagina->update(['estado' => 'construyendo']);

        return back()->with('ok', "Corrección solicitada para \"{$seccion->nombre}\"; quedó en la cola de la consola (registro:cola).");
    }

    public function enviarQC(Pagina $pagina)
    {
        $sinAprobar = $pagina->secciones()->whereNotIn('estado', ['aprobada'])->count();
        abort_unless($pagina->secciones()->count() > 0 && $sinAprobar === 0, 422, 'Aprueba todas las secciones antes de enviar a control de calidad.');

        $pagina->update(['estado' => 'en_qc']);
        Evento::create(['tipo' => 'envio_qc', 'detalle' => "Página {$pagina->nombre} enviada a control de calidad"]);

        return back()->with('ok', "\"{$pagina->nombre}\" enviada a control de calidad.");
    }
}
