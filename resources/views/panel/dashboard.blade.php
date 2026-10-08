@extends('layouts.app')
@section('titulo', 'Panel')

@section('contenido')
    @php
        // El titular dice qué hacer ahora (concepto A2): primera línea en tinta, segunda en naranja.
        $tareas = $pendientes->count() + $listasQa->count();
        [$linea1, $linea2] = match (true) {
            $tareas === 0 => ['Todo en orden', 'sin pendientes'],
            $tareas === 1 => ['1 cosa espera', 'tu revisión'],
            default       => ["{$tareas} cosas esperan", 'tu revisión'],
        };
        $partes = [];
        if ($pendientes->count()) { $partes[] = $pendientes->count() . ' ' . ($pendientes->count() === 1 ? 'corrección en cola' : 'correcciones en cola'); }
        if ($listasQa->count()) { $partes[] = $listasQa->count() . ' ' . ($listasQa->count() === 1 ? 'página lista para QA' : 'páginas listas para QA'); }
        $resumen = ($partes ? ucfirst(implode(' y ', $partes)) . '. ' : 'Sin correcciones en cola ni páginas esperando QA. ')
            . ($hoy ? "Hoy el asistente registró {$hoy} " . ($hoy === 1 ? 'sección.' : 'secciones.') : 'Nada se publica sin tu aprobación.');
        $ahora = now('America/Lima');
        $saludo = ($ahora->hour < 12 ? 'Buenos días' : ($ahora->hour < 19 ? 'Buenas tardes' : 'Buenas noches')) . ' · ' . $ahora->translatedFormat('l j \d\e F');
        $correccionesPorProyecto = $pendientes->groupBy(fn ($e) => $e->seccion?->pagina?->proyecto_id)->map->count();
        $tonos = ['construccion' => 'lav', 'aprobacion' => 'ok', 'solicitud_correccion' => 'warn', 'correccion' => 'warn', 'envio_qc' => 'ora', 'qa_solicitado' => 'ora', 'proyecto_eliminado' => 'err'];
        $verbos = ['construccion' => 'Construida', 'aprobacion' => 'Aprobada', 'solicitud_correccion' => 'Corrección pedida en', 'correccion' => 'Corregida', 'envio_qc' => 'QA solicitado para', 'qa_solicitado' => 'QA solicitado para', 'token' => 'Token de diseño', 'figma' => 'Figma leído', 'proyecto_eliminado' => 'Proyecto eliminado'];
        $base = $stats['linea_base'];
        $tope = max($base, $ritmo->max('min_total') ?? 0) * 1.08;
    @endphp

    <div class="hero">
        <div class="hero-txt">
            <p class="saludo">{{ $saludo }}</p>
            <span class="chip-hoy"><i class="{{ $tareas ? '' : 'ok' }}"></i>Hoy · {{ $tareas ? $tareas . ' ' . ($tareas === 1 ? 'pendiente' : 'pendientes') : 'al día' }}</span>
            <h1 class="titular"><span>{{ $linea1 }}</span><span class="acento">{{ $linea2 }}</span></h1>
            <p class="hero-sub">{{ $resumen }}</p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('registro.export') }}"><x-ic n="lista" />Exportar registro</a>
        </div>
    </div>

    <div class="tablero-inicio">
        <div class="col">
            @if ($tareas)
                <section class="filas-cap" aria-label="Esperan tu atención" id="atencion">
                    @foreach ($pendientes as $e)
                        <div class="fila-cap pendiente">
                            <span class="icono-suave warn redondo"><x-ic n="llave" /></span>
                            <div class="txt">
                                <b>{{ $e->seccion?->nombre }}</b> <span class="donde">· {{ $e->seccion?->pagina?->nombre }} — {{ $e->seccion?->pagina?->proyecto?->nombre }}</span>
                                <div class="cita">“{{ $e->detalle }}”</div>
                                <div class="meta"><x-ic n="reloj" c="sm" />En cola {{ $e->created_at->diffForHumans() }} · la consola la ve con <code>registro:cola</code></div>
                            </div>
                            @if ($e->seccion)<a class="btn chico" href="{{ route('pagina', $e->seccion->pagina_id) }}">Abrir</a>@endif
                        </div>
                    @endforeach
                    @foreach ($listasQa as $pg)
                        <div class="fila-cap pendiente">
                            <span class="icono-suave ok redondo"><x-ic n="check" /></span>
                            <div class="txt">
                                <b>{{ $pg->nombre }}</b> <span class="donde">· {{ $pg->proyecto->nombre }}</span>
                                <div class="meta" style="margin-top:2px">Todas sus secciones están aprobadas. Falta copiar el mensaje para el canal de QA.</div>
                            </div>
                            <a class="btn chico p" href="{{ route('pagina', $pg) }}#qa"><x-ic n="mensaje" c="sm" />Preparar mensaje</a>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="caja ritmo" aria-label="Minutos por página">
                <div class="ritmo-cab">
                    <div><h2>Minutos por página</h2><p class="nota">Últimas {{ $ritmo->count() ?: 8 }} páginas frente a la base manual</p></div>
                    <b class="ritmo-cifra">{{ $stats['promedio_min'] ?: '—' }}<small> min prom.</small></b>
                </div>
                @if ($ritmo->isNotEmpty())
                    <div class="ritmo-graf" role="img" aria-label="Minutos de las últimas páginas; la base manual es {{ $base }} min">
                        <span class="ritmo-base" style="bottom:{{ 100 * $base / $tope }}%"><em>base {{ $base }}</em></span>
                        @foreach ($ritmo as $r)
                            <div class="ritmo-col" title="{{ $r->nombre }}: {{ $r->min_total }} min">
                                <i class="{{ $loop->last ? 'ultima' : '' }}" style="height:{{ max(6, 100 * $r->min_total / $tope) }}%"></i>
                                <span>{{ $r->nombre }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="vacio">Aparecerá cuando la consola registre las primeras secciones.</p>
                @endif
            </section>
        </div>

        <div class="col">
            <div class="col-cab"><h2>Proyectos</h2><span class="cifra">{{ $proyectos->count() }} {{ $proyectos->count() === 1 ? 'activo' : 'activos' }}</span></div>
            @forelse ($proyectos as $p)
                @php
                    $alcance = $p->paginas->where('incluida', true);
                    $n = max($alcance->count(), 1);
                    $apr = $alcance->whereIn('estado', ['aprobada', 'en_qc'])->count();
                    $obra = $alcance->where('estado', 'construyendo')->count();
                    $corr = $correccionesPorProyecto[$p->id] ?? 0;
                @endphp
                <a class="proy-cap" href="{{ route('proyecto', $p) }}">
                    <div class="proy-cab">
                        <div class="min0">
                            @if ($p->cliente)<small>{{ $p->cliente }}</small>@endif
                            <b>{{ $p->nombre }}</b>
                        </div>
                        @if ($corr)<span class="tag rev">{{ $corr }} {{ $corr === 1 ? 'corrección' : 'correcciones' }}</span>@endif
                        @if ($alcance->count())<strong>{{ round(100 * $apr / $n) }}<small>%</small></strong>@endif
                    </div>
                    @if ($alcance->count())
                        <div class="riel"><i class="a" style="width:{{ 100 * $apr / $n }}%"></i><i class="r" style="width:{{ 100 * $obra / $n }}%"></i></div>
                        <div class="proy-ley">
                            <span><i class="a"></i>{{ $apr }} {{ $apr === 1 ? 'lista' : 'listas' }}</span>
                            @if ($obra)<span><i class="r"></i>{{ $obra }} en obra</span>@endif
                            @if ($alcance->count() - $apr - $obra > 0)<span><i></i>{{ $alcance->count() - $apr - $obra }} {{ $alcance->count() - $apr - $obra === 1 ? 'pendiente' : 'pendientes' }}</span>@endif
                        </div>
                    @else
                        <p class="nota">Esperando la primera lectura del Figma</p>
                    @endif
                </a>
            @empty
                <p class="vacio caja">Crea tu primer proyecto con el botón + de arriba.</p>
            @endforelse

            <div class="cifras">
                <div class="cifra-cap lav"><small>Hecho por el asistente</small><b>{{ $stats['pct_asistente'] }}<small>%</small></b><span>del tiempo de construcción</span></div>
                @if ($stats['promedio_min'])
                    @php $ahorro = round(100 * ($base - $stats['promedio_min']) / $base); @endphp
                    <div class="cifra-cap ok"><small>Frente a la base</small><b>{{ $ahorro > 0 ? '−' : '+' }}{{ abs($ahorro) }}<small>%</small></b><span>{{ abs($base - $stats['promedio_min']) }} min {{ $ahorro > 0 ? 'menos' : 'más' }} por página</span></div>
                @else
                    <div class="cifra-cap ok"><small>En construcción</small><b>{{ $stats['en_obra'] }}</b><span>{{ $stats['en_obra'] === 1 ? 'página' : 'páginas' }} · {{ $stats['en_qa'] }} en QA</span></div>
                @endif
            </div>

            <section class="caja">
                <div class="caja-cab"><h2>Actividad</h2><a class="ver" href="{{ route('registro') }}">Ver registro <x-ic n="flecha" c="sm" /></a></div>
                <div class="actividad">
                    @forelse ($actividad->take(5) as $e)
                        <div class="evento">
                            <i class="punto-act {{ $tonos[$e->tipo] ?? '' }}"></i>
                            <p>
                                @if ($e->seccion)
                                    {{ $verbos[$e->tipo] ?? ucfirst(str_replace('_', ' ', $e->tipo)) }} <b>{{ $e->seccion->nombre }}</b> · {{ $e->seccion->pagina?->nombre }}
                                @else
                                    <b>{{ $verbos[$e->tipo] ?? ucfirst(str_replace('_', ' ', $e->tipo)) }}</b> · {{ \Illuminate\Support\Str::limit($e->detalle, 60) }}
                                @endif
                            </p>
                            <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->diffForHumans(short: true) }}</time>
                        </div>
                    @empty
                        <p class="vacio">Sin actividad todavía: cuando la consola construya la primera sección, aparecerá aquí.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection
