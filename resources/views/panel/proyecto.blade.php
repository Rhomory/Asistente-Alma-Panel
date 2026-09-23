@extends('layouts.app')
@section('titulo', $proyecto->nombre)

@section('contenido')
    <h1>{{ $proyecto->nombre }}</h1>
    <p class="sub">
        {{ $proyecto->cliente }} @if($proyecto->sitio_wp) · staging: {{ $proyecto->sitio_wp }} @endif
    </p>

    @foreach ($paginas as $rp)
        <div class="card">
            <div class="fila-titulo">
                <h3>{{ $rp->nombre }} <span class="tag estado-{{ $rp->estado }}">{{ $rp->estado }}</span></h3>
                @if ($rp->min_total > 0)
                    <div class="mini-kpis">
                        <span><b>{{ intdiv($rp->min_total, 60) }} h {{ str_pad($rp->min_total % 60, 2, '0', STR_PAD_LEFT) }}</b> total</span>
                        <span><b>▼ {{ round(100 - 100 * $rp->min_total / $rp->linea_base_min) }} %</b> vs línea base</span>
                        <span><b>{{ $rp->min_asistente + $rp->min_dev > 0 ? round(100 * $rp->min_asistente / ($rp->min_asistente + $rp->min_dev)) : 0 }} %</b> asistente</span>
                        <span><b>{{ $rp->correcciones }}</b> correcciones</span>
                    </div>
                @endif
            </div>
            @if (isset($secciones[$rp->id]))
                <table>
                    <thead><tr><th>Sección</th><th>Widget (guía técnica)</th><th class="n">Min</th><th class="n">Asist.</th><th class="n">Dev</th><th class="n">Correcc.</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($secciones[$rp->id] as $s)
                        <tr>
                            <td>{{ $s->nombre }}</td>
                            <td class="mut">{{ $s->widget_plan ?? '—' }}</td>
                            <td class="n">{{ $s->minutos }}</td>
                            <td class="n">{{ $s->min_asistente }}</td>
                            <td class="n">{{ $s->min_dev }}</td>
                            <td class="n">{{ $s->correcciones }}</td>
                            <td>{{ $s->aprobada ? '✓ aprobada' : 'en revisión' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="vacio">Sin secciones registradas todavía ({{ $rp->nombre }} está {{ $rp->estado }}).</p>
            @endif
        </div>
    @endforeach
@endsection
