<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Pagina;
use App\Models\Proyecto;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel de solo lectura del Asistente Alma.
 * La consola (el agente) escribe el registro; aquí solo se consulta.
 */
class PanelController extends Controller
{
    public function dashboard()
    {
        $reportes = DB::table('reporte_pagina')
            ->join('paginas', 'paginas.id', '=', 'reporte_pagina.id')
            ->where('paginas.incluida', true)
            ->select('reporte_pagina.*')
            ->get();
        $medidas = $reportes->where('min_total', '>', 0);
        // El promedio solo considera páginas cerradas; las que están en obra lo distorsionarían.
        $terminadas = $medidas->whereIn('estado', ['aprobada', 'en_qc']);

        $stats = [
            'promedio_min'  => $terminadas->count() ? round($terminadas->avg('min_total')) : 0,
            'linea_base'    => 480,
            'pct_asistente' => $this->pctAsistente($medidas),
            'correcciones'  => $terminadas->count() ? round($terminadas->avg('correcciones'), 1) : 0,
            'en_obra'       => $reportes->where('estado', 'construyendo')->count(),
            'en_qa'         => $reportes->where('estado', 'en_qc')->count(),
        ];

        $pendientes = Evento::with('seccion.pagina.proyecto')
            ->where('tipo', 'solicitud_correccion')->where('resuelto', false)
            ->orderByDesc('created_at')->get();

        return view('panel.dashboard', [
            'stats'      => $stats,
            'pendientes' => $pendientes,
            'actividad'  => Evento::with('seccion.pagina.proyecto')->orderByDesc('created_at')->orderByDesc('id')->limit(8)->get(),
            'ultimas'    => Seccion::with('pagina.proyecto')->where('estado', '!=', 'planificada')->orderByDesc('updated_at')->limit(5)->get(),
            'proyectos'  => Proyecto::with('paginas')->orderByDesc('updated_at')->get(),
            'listasQa'   => Pagina::with('proyecto')->where('incluida', true)->where('estado', 'aprobada')->orderByDesc('updated_at')->get(),
            // Ritmo: minutos de las últimas 8 páginas medidas, frente a la base manual.
            'ritmo'      => $medidas->sortByDesc('id')->take(8)->reverse()->values(),
            'hoy'        => Evento::where('tipo', 'construccion')->where('created_at', '>=', now('America/Lima')->startOfDay()->utc())->count(),
        ]);
    }

    public function proyecto(Proyecto $proyecto)
    {
        $proyecto->load('tokens', 'conexion', 'disenos');
        $paginas = DB::table('reporte_pagina')
            ->join('paginas', 'paginas.id', '=', 'reporte_pagina.id')
            ->select('reporte_pagina.*', 'paginas.incluida', 'paginas.origen', 'paginas.secciones_total')
            ->where('reporte_pagina.proyecto_id', $proyecto->id)
            ->orderByDesc('paginas.incluida')->orderBy('paginas.id')
            ->get();

        // Estado de cada sección para la barra segmentada de avance.
        $secciones = Seccion::whereIn('pagina_id', $paginas->pluck('id'))->orderBy('id')
            ->get(['pagina_id', 'estado'])->groupBy('pagina_id');

        $conteo = GestionController::conteoEliminar($proyecto);

        return view('panel.proyecto', compact('proyecto', 'paginas', 'secciones', 'conteo'));
    }

    public function registro(Request $request)
    {
        $secciones = $this->consultaRegistro($request)->paginate(25)->withQueryString();

        return view('panel.registro', [
            'secciones' => $secciones,
            'proyectos' => Proyecto::orderBy('nombre')->get(),
            'filtro'    => $request->only('proyecto_id', 'q'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $filas = $this->consultaRegistro($request)->get();

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['fecha', 'proyecto', 'pagina', 'seccion', 'estado', 'minutos', 'min_estimado', 'min_asistente', 'min_dev', 'ejecuto', 'correcciones']);
            foreach ($filas as $s) {
                fputcsv($out, [
                    optional($s->inicio)->format('Y-m-d'),
                    $s->pagina->proyecto->nombre, $s->pagina->nombre, $s->nombre, $s->estado,
                    $s->minutos, $s->min_estimado, $s->min_asistente, $s->min_dev, $s->ejecuto,
                    $s->correcciones,
                ]);
            }
            fclose($out);
        }, 'registro-asistente-alma-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function consultaRegistro(Request $request)
    {
        return Seccion::with('pagina.proyecto')
            ->when($request->integer('proyecto_id'), fn ($q, $id) => $q->whereHas('pagina', fn ($p) => $p->where('proyecto_id', $id)))
            ->when($request->string('q')->toString(), fn ($q, $texto) => $q->where('nombre', 'like', "%{$texto}%"))
            ->orderByDesc('fin');
    }

    private function pctAsistente($medidas): int
    {
        $total = $medidas->sum('min_asistente') + $medidas->sum('min_dev');

        return $total > 0 ? (int) round(100 * $medidas->sum('min_asistente') / $total) : 0;
    }
}
