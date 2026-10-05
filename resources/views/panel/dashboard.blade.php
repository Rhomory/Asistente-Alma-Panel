@extends('layouts.app')
@section('titulo', 'Inicio')

@section('contenido')
    @php
        $hora = now('America/Lima')->hour;
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
        $resumen = [];
        $resumen[] = $hoy ? "El asistente registró {$hoy} " . ($hoy === 1 ? 'sección' : 'secciones') . ' hoy.' : 'Hoy el asistente aún no registra secciones.';
        if ($pendientes->count()) { $resumen[] = "Hay {$pendientes->count()} " . ($pendientes->count() === 1 ? 'corrección' : 'correcciones') . ' en cola.'; }
        if ($listasQa->count()) { $resumen[] = "{$listasQa->count()} " . ($listasQa->count() === 1 ? 'página está lista' : 'páginas están listas') . ' para pedir QA.'; }
        $rotulos = [
            'construccion'         => ['robot', 'lav', 'construida'],
            'aprobacion'           => ['check', 'ok', 'aprobada'],
            'solicitud_correccion' => ['llave', 'warn', 'con corrección pedida'],
            'correccion'           => ['llave', 'warn', 'corregida'],
            'envio_qc'             => ['mensaje', 'ora', 'QA solicitado'],
            'qa_solicitado'        => ['mensaje', 'ora', 'QA solicitado'],
            'token'                => ['paleta', '', 'Token de diseño'],
            'figma'                => ['figma', '', 'Figma leído'],
        ];
    @endphp

    <div class="cabeza">
        <div>
            <h1>{{ $saludo }}</h1>
            <p>{{ implode(' ', $resumen) }}</p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('registro.export') }}"><x-ic n="lista" />Exportar registro</a>
            <a class="btn p" href="{{ route('proyecto.nuevo') }}"><x-ic n="mas" />Nuevo proyecto</a>
        </div>
    </div>

    <section class="caja resumen" aria-label="Resumen">
        <div><small>Promedio por página</small><b>{{ $stats['promedio_min'] ?: '—' }}</b><em>min · base {{ $stats['linea_base'] }}</em></div>
        <div><small>Hecho por el asistente</small><b>{{ $stats['pct_asistente'] }} %</b><em>del tiempo</em></div>
        <div><small>Correcciones</small><b>{{ str_replace('.', ',', (string) $stats['correcciones']) }}</b><em>por página</em></div>
        <div><small>En cola</small><b class="{{ $pendientes->count() ? 'alerta' : '' }}">{{ $pendientes->count() }}</b><em>{{ $pendientes->count() === 1 ? 'corrección' : 'correcciones' }}</em></div>
        <div><small>En construcción</small><b>{{ $stats['en_obra'] }}</b><em>páginas</em></div>
        <div><small>QA solicitado</small><b>{{ $stats['en_qa'] }}</b><em>páginas</em></div>
    </section>

    <div class="rejilla">
        <div class="col">
            <section class="caja">
                <div class="caja-cab"><h2>Esperan tu atención</h2><span class="cifra">{{ $pendientes->count() + $listasQa->count() }}</span></div>
                @foreach ($pendientes as $e)
                    <div class="pendiente">
                        <span class="icono-suave warn"><x-ic n="llave" /></span>
                        <div class="txt">
                            <b>{{ $e->seccion?->nombre }}</b> <span class="donde">· {{ $e->seccion?->pagina?->nombre }} — {{ $e->seccion?->pagina?->proyecto?->nombre }}</span>
                            <div class="cita">“{{ $e->detalle }}”</div>
                            <div class="meta"><x-ic n="reloj" c="sm" />En cola {{ $e->created_at->diffForHumans() }} · la consola la ve con <code>registro:cola</code></div>
                        </div>
                        @if ($e->seccion)<a class="btn chico" href="{{ route('pagina', $e->seccion->pagina_id) }}">Abrir</a>@endif
                    </div>
                @endforeach
                @foreach ($listasQa as $pg)
                    <div class="pendiente">
                        <span class="icono-suave ok"><x-ic n="check" /></span>
                        <div class="txt">
                            <b>{{ $pg->nombre }}</b> <span class="donde">· {{ $pg->proyecto->nombre }}</span>
                            <div class="meta" style="margin-top:2px">Todas sus secciones están aprobadas. Falta copiar el mensaje para el canal de QA.</div>
                        </div>
                        <a class="btn chico p" href="{{ route('pagina', $pg) }}#qa"><x-ic n="mensaje" c="sm" />Preparar mensaje</a>
                    </div>
                @endforeach
                @if ($pendientes->isEmpty() && $listasQa->isEmpty())
                    <p class="vacio">Todo en orden: no hay correcciones en cola ni páginas esperando QA.</p>
                @endif
            </section>

            <section class="caja">
                <div class="caja-cab"><h2>Últimos registros</h2><a class="ver" href="{{ route('registro') }}">Ver registro <x-ic n="flecha" c="sm" /></a></div>
                <div class="tabla-env">
                    <table class="tabla">
                        <thead><tr><th>Sección</th><th>Página</th><th>Proyecto</th><th class="n">Min</th><th class="n">Asistente</th><th>Estado</th></tr></thead>
                        <tbody>
                        @forelse ($ultimas as $s)
                            @php $t = $s->min_asistente + $s->min_dev; @endphp
                            <tr>
                                <td class="nw">{{ \Illuminate\Support\Str::limit($s->nombre, 28) }}</td>
                                <td class="nw"><a href="{{ route('pagina', $s->pagina_id) }}">{{ $s->pagina->nombre }}</a></td>
                                <td class="mut nw">{{ $s->pagina->proyecto->nombre }}</td>
                                <td class="n">{{ $s->minutos }}</td>
                                <td class="n">{{ $t ? round(100 * $s->min_asistente / $t) . ' %' : '—' }}</td>
                                <td>@include('panel.partes.estado', ['estado' => $s->estado])</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="vacio">Aún no hay secciones registradas. Aparecerán cuando la consola ejecute <code>registro:add</code>.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="col">
            <section class="caja">
                <div class="caja-cab"><h2>Actividad reciente</h2></div>
                <div style="padding:6px 0">
                    @forelse ($actividad as $e)
                        @php [$icono, $tono, $verbo] = $rotulos[$e->tipo] ?? ['chispa', '', ucfirst(str_replace('_', ' ', $e->tipo))]; @endphp
                        <div class="evento">
                            <span class="icono-suave chico {{ $tono }}"><x-ic :n="$icono" c="sm" /></span>
                            <p>
                                @if ($e->seccion)
                                    <b>{{ $e->seccion->nombre }}</b> {{ $verbo }} en {{ $e->seccion->pagina?->nombre }}
                                @else
                                    <b>{{ $verbo }}</b> · {{ \Illuminate\Support\Str::limit($e->detalle, 70) }}
                                @endif
                            </p>
                            <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->diffForHumans(short: true) }}</time>
                        </div>
                    @empty
                        <p class="vacio">Sin actividad todavía: cuando la consola construya la primera sección, aparecerá aquí.</p>
                    @endforelse
                </div>
            </section>

            <section class="caja">
                <div class="caja-cab"><h2>Proyectos</h2><span class="cifra">{{ $proyectos->count() }}</span></div>
                @forelse ($proyectos as $p)
                    @php
                        $alcance = $p->paginas->where('incluida', true);
                        $n = max($alcance->count(), 1);
                        $apr = $alcance->where('estado', 'aprobada')->count();
                        $qa = $alcance->where('estado', 'en_qc')->count();
                        $obra = $alcance->where('estado', 'construyendo')->count();
                    @endphp
                    <a class="proy" href="{{ route('proyecto', $p) }}">
                        <b>{{ $p->nombre }}</b>
                        <span>{{ $alcance->count() ? ($apr + $qa) . ' de ' . $alcance->count() . ' páginas listas' : 'Sin páginas todavía' }}</span>
                        <div class="barra-prog"><i class="a" style="width:{{ 100 * $apr / $n }}%"></i><i class="q" style="width:{{ 100 * $qa / $n }}%"></i><i class="r" style="width:{{ 100 * $obra / $n }}%"></i></div>
                    </a>
                @empty
                    <p class="vacio">Crea tu primer proyecto con “Nuevo proyecto”.</p>
                @endforelse
                @if ($proyectos->isNotEmpty())
                    <div class="leyenda"><span class="tag ok">Aprobada</span><span class="tag qa">QA solicitado</span><span class="tag obra">En construcción</span></div>
                @endif
            </section>
        </div>
    </div>
@endsection
