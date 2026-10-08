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
    public const PLAN_ESTANDAR = [
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
            // Pestañas del tablero: las páginas en alcance del mismo proyecto (y esta, aunque esté fuera).
            'hermanas'    => $pagina->proyecto->paginas()->where(fn ($q) => $q->where('incluida', true)->orWhere('id', $pagina->id))->orderBy('id')->get(['id', 'nombre', 'estado']),
        ]);
    }

    public function planEstandar(Pagina $pagina)
    {
        // Con secciones ya construidas, el plan genérico duplicaría trabajo hecho con otro nombre
        // ("Hero" junto a "Hero con video"): se agregan solo las que falten, a mano.
        if ($pagina->secciones()->where('estado', '!=', 'planificada')->exists()) {
            return back()->withErrors(['plan' => 'Esta página ya tiene secciones construidas: agrega solo las que falten con el formulario.']);
        }

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
        if ($creadas) {
            self::reabrir($pagina);
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
        self::reabrir($pagina);

        return back()->with('ok', "Sección \"{$datos['nombre']}\" agregada al plan.");
    }

    /** Quita todas las secciones planificadas (aún no construidas) y recalcula el estado de la página. */
    public function quitarPlanificadas(Pagina $pagina)
    {
        $n = $pagina->secciones()->where('estado', 'planificada')->delete();

        $total = $pagina->secciones()->count();
        if ($total > 0 && $pagina->secciones()->where('estado', '!=', 'aprobada')->doesntExist() && $pagina->estado === 'construyendo') {
            $pagina->update(['estado' => 'aprobada']); // lo que queda ya está todo aprobado
        } elseif ($total === 0) {
            $pagina->update(['estado' => 'pendiente']);
        }

        return back()->with('ok', $n ? "Se quitaron {$n} secciones planificadas." : 'No había secciones planificadas.');
    }

    /**
     * Una página aprobada (o con QA pedido) que recibe secciones nuevas vuelve a obra:
     * ya no está lista para QA hasta que se aprueben también las nuevas.
     */
    public static function reabrir(Pagina $pagina): void
    {
        if (in_array($pagina->estado, ['aprobada', 'en_qc'], true)
            && $pagina->secciones()->where('estado', '!=', 'aprobada')->exists()) {
            $pagina->update(['estado' => 'construyendo']);
        }
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

    /** Mensaje estándar del canal para pedir QA, con el sitio y el Figma del proyecto y el Trello de la página. */
    public static function mensajeQA(Pagina $pagina): string
    {
        $p = $pagina->proyecto;
        $sitio = $pagina->urlSitio() ?: '[falta la URL del sitio]'; // la URL de la página, no solo la raíz
        $figma = $p->archivo_figma ?: '[falta el archivo de Figma]';
        $trello = $pagina->trello_url ?: '[falta el link de Trello]';

        return "@canal Solicito QA para {$sitio} aquí archivo Figma: {$figma} y link de Trello: {$trello}";
    }

    /** Ruta de la página en el sitio: "/nosotros" o una URL completa; vacío = se calcula desde el nombre. */
    public function guardarUrl(Request $request, Pagina $pagina)
    {
        $datos = $request->validate(
            ['url' => ['nullable', 'string', 'max:255', 'regex:#^(/|https?://)#i']],
            ['url.regex' => 'Escribe una ruta que empiece con "/" (ej. /nosotros) o una URL completa.']
        );
        $pagina->update(['url' => $datos['url'] ?? null]);

        return back()->with('ok', 'URL de la página guardada: ' . ($pagina->fresh()->urlSitio() ?? 'falta el sitio del proyecto') . '.');
    }

    public function guardarTrello(Request $request, Pagina $pagina)
    {
        $datos = $request->validate(
            ['trello_url' => ['nullable', 'url', 'max:255', 'regex:#^https://(www\.)?trello\.com/#']],
            ['trello_url.url' => 'Pega el enlace completo de la tarjeta (https://trello.com/c/…).',
             'trello_url.regex' => 'El enlace debe ser de trello.com.']
        );
        $pagina->update(['trello_url' => $datos['trello_url'] ?? null]);

        return back()->with('ok', 'Enlace de Trello guardado: el mensaje de QA ya lo incluye.');
    }

    /**
     * Se llama al copiar el mensaje: deja constancia de la solicitud en la actividad y,
     * si todas las secciones están aprobadas, pasa la página a "QA solicitado" (en_qc).
     */
    public function qaSolicitado(Pagina $pagina)
    {
        $pagina->load('proyecto');
        $total = $pagina->secciones()->count();
        $completa = $total > 0 && $pagina->secciones()->where('estado', '!=', 'aprobada')->count() === 0;

        if ($completa && $pagina->estado !== 'en_qc') {
            $pagina->update(['estado' => 'en_qc']);
        }
        Evento::create(['tipo' => 'qa_solicitado', 'detalle' => self::mensajeQA($pagina)]);

        return response()->json(['estado' => $pagina->fresh()->estado, 'completa' => $completa]);
    }
}
