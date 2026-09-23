<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Panel de solo lectura del Asistente Alma.
 * La consola (Claude Code) escribe el registro; aquí solo se consulta.
 */
class PanelController extends Controller
{
    public function dashboard()
    {
        $reportes = DB::table('reporte_pagina')
            ->join('proyectos', 'proyectos.id', '=', 'reporte_pagina.proyecto_id')
            ->select('reporte_pagina.*', 'proyectos.nombre as proyecto')
            ->get();

        $medidas = $reportes->where('min_total', '>', 0);
        // El promedio solo considera páginas terminadas; las que están en
        // construcción entran a los gráficos pero no distorsionan el KPI.
        $terminadas = $medidas->where('estado', 'aprobada');

        $kpis = [
            'promedio_min'  => $terminadas->count() ? round($terminadas->avg('min_total')) : 0,
            'linea_base'    => 480,
            'pct_asistente' => $this->pctAsistente($medidas),
            'correcciones'  => $terminadas->count() ? round($terminadas->avg('correcciones'), 1) : 0,
            'paginas_medidas' => $terminadas->count(),
        ];

        $ultimas = Seccion::with('pagina.proyecto')->orderByDesc('fin')->limit(8)->get();

        return view('panel.dashboard', [
            'kpis'     => $kpis,
            'reportes' => $medidas->values(),
            'donut'    => ['asistente' => $medidas->sum('min_asistente'), 'dev' => $medidas->sum('min_dev')],
            'ultimas'  => $ultimas,
            'proyectos' => Proyecto::withCount('paginas')->get(),
        ]);
    }

    public function proyecto(Proyecto $proyecto)
    {
        $paginas = DB::table('reporte_pagina')->where('proyecto_id', $proyecto->id)->get();
        $secciones = Seccion::whereHas('pagina', fn ($q) => $q->where('proyecto_id', $proyecto->id))
            ->with('pagina')->orderBy('pagina_id')->orderBy('inicio')->get()->groupBy('pagina_id');

        return view('panel.proyecto', compact('proyecto', 'paginas', 'secciones'));
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
            fputcsv($out, ['fecha', 'proyecto', 'pagina', 'seccion', 'minutos', 'min_asistente', 'min_dev', 'ejecuto', 'correcciones', 'aprobada']);
            foreach ($filas as $s) {
                fputcsv($out, [
                    optional($s->inicio)->format('Y-m-d'),
                    $s->pagina->proyecto->nombre, $s->pagina->nombre, $s->nombre,
                    $s->minutos, $s->min_asistente, $s->min_dev, $s->ejecuto,
                    $s->correcciones, $s->aprobada ? 'si' : 'no',
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
