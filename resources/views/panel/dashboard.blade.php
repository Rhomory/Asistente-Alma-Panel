@extends('layouts.app')
@section('titulo', 'Panel')

@section('contenido')
    <h1>Panel de operaciones</h1>
    <p class="sub">Lo importante primero: qué pide atención, qué se movió y cómo van los sitios en trabajo.</p>

    <div class="stats-mini">
        <div class="stat">
            <b>{{ $stats['promedio_min'] ? intdiv($stats['promedio_min'], 60) . ' h ' . str_pad($stats['promedio_min'] % 60, 2, '0', STR_PAD_LEFT) : '—' }}</b>
            <span>promedio por página @if ($stats['promedio_min'])· ▼ {{ round(100 - 100 * $stats['promedio_min'] / $stats['linea_base']) }} % vs manual @endif</span>
        </div>
        <div class="stat"><b>{{ $stats['pct_asistente'] }} %</b><span>del trabajo lo ejecuta el asistente</span></div>
        <div class="stat"><b>{{ str_replace('.', ',', (string) $stats['correcciones']) }}</b><span>correcciones por página</span></div>
        <div class="stat {{ $pendientes->count() ? 'alerta' : '' }}"><b>{{ $pendientes->count() }}</b><span>correcciones en cola</span></div>
        <div class="stat"><b>{{ $stats['en_obra'] }}</b><span>páginas en construcción</span></div>
        <div class="stat"><b>{{ $stats['en_qa'] }}</b><span>páginas con QA solicitado</span></div>
    </div>

    @if ($pendientes->isNotEmpty())
        <div class="card atencion">
            <h3>Requiere atención: correcciones en cola de la consola</h3>
            @foreach ($pendientes as $e)
                <div class="item-cola">
                    <div>
                        <b>{{ $e->seccion?->nombre }}</b> · {{ $e->seccion?->pagina?->proyecto?->nombre }} / {{ $e->seccion?->pagina?->nombre }}
                        <div class="mut-chico">"{{ $e->detalle }}" · pedida el {{ $e->created_at->format('d/m H:i') }}</div>
                    </div>
                    @if ($e->seccion)
                        <a class="btn s chico" href="{{ route('pagina', $e->seccion->pagina_id) }}">Ver página →</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="dos-col">
        <div class="card">
            <h3>Actividad reciente</h3>
            @php
                $rotulos = [
                    'construccion'         => ['●', 'Sección construida'],
                    'aprobacion'           => ['✓', 'Sección aprobada'],
                    'solicitud_correccion' => ['↺', 'Corrección solicitada'],
                    'correccion'           => ['↺', 'Corrección registrada'],
                    'envio_qc'             => ['➜', 'QA solicitado'],
                    'qa_solicitado'        => ['➜', 'QA solicitado'],
                    'token'                => ['◆', 'Token de diseño'],
                ];
            @endphp
            @forelse ($actividad as $e)
                @php [$icono, $rotulo] = $rotulos[$e->tipo] ?? ['·', ucfirst(str_replace('_', ' ', $e->tipo))]; @endphp
                <div class="evento">
                    <span class="ev-icono ev-{{ $e->tipo }}">{{ $icono }}</span>
                    <div>
                        <b>{{ $rotulo }}</b>@if ($e->seccion) — {{ $e->seccion->nombre }} <span class="mut">({{ $e->seccion->pagina?->proyecto?->nombre }} / {{ $e->seccion->pagina?->nombre }})</span>@endif
                        <div class="mut-chico">{{ $e->created_at->format('d/m H:i') }}@if ($e->detalle) · {{ \Illuminate\Support\Str::limit($e->detalle, 80) }}@endif</div>
                    </div>
                </div>
            @empty
                <p class="vacio">Sin actividad todavía: cuando la consola construya la primera sección, aparecerá aquí.</p>
            @endforelse
        </div>

        <div>
            <div class="card">
                <h3>Proyectos</h3>
                @forelse ($proyectos as $p)
                    @php $alcance = $p->paginas->where('incluida', true); @endphp
                    <div class="fila-proy">
                        <a href="{{ route('proyecto', $p) }}"><b>{{ $p->nombre }}</b></a>
                        <span class="mut-chico">
                            {{ $alcance->count() }} pág. en alcance ·
                            {{ $alcance->whereIn('estado', ['aprobada', 'en_qc'])->count() }} listas ·
                            {{ $alcance->where('estado', 'construyendo')->count() }} en obra
                        </span>
                    </div>
                @empty
                    <p class="vacio">Crea tu primer proyecto con "+ Nuevo proyecto".</p>
                @endforelse
            </div>

            <div class="card">
                <div class="fila-titulo"><h3>Últimos registros</h3><a class="mut-chico" href="{{ route('registro') }}">ver todo →</a></div>
                <table class="compacta">
                    <thead><tr><th>Sección</th><th>Página</th><th class="n">Min</th><th>Estado</th></tr></thead>
                    <tbody>
                    @forelse ($ultimas as $s)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::limit($s->nombre, 26) }}</td>
                            <td class="mut">{{ $s->pagina->proyecto->nombre }} / {{ $s->pagina->nombre }}</td>
                            <td class="n">{{ $s->minutos }}</td>
                            <td><span class="tag estado-{{ $s->estado }}">{{ $s->estado }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="vacio">Aún no hay secciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
