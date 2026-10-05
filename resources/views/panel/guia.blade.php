@extends('layouts.app')
@section('titulo', 'Guía · ' . $proyecto->nombre)
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <a href="{{ route('proyecto', $proyecto) }}">{{ $proyecto->nombre }}</a> / @if ($pagina)<a href="{{ route('pagina', $pagina) }}">Página: {{ $pagina->nombre }}</a> / @endif<b aria-current="page">Guía y prompt</b>@endsection

@section('contenido')
    <div class="cabeza">
        @php
            $padreUrl = $pagina ? route('pagina', $pagina) : route('proyecto', $proyecto);
            $padre = $pagina ? "la página {$pagina->nombre}" : $proyecto->nombre;
        @endphp
        <div class="titulo-con-padre">
            <a class="btn-subir" href="{{ $padreUrl }}" aria-label="Volver a {{ $padre }}" title="Volver a {{ $padre }} (Alt+↑)" aria-keyshortcuts="Alt+ArrowUp"><x-ic n="atras" /></a>
            <div>
                <p class="contexto">Guía de @if ($pagina)<a href="{{ $padreUrl }}">{{ $pagina->nombre }}</a> · {{ $proyecto->nombre }}@else<a href="{{ $padreUrl }}">{{ $proyecto->nombre }}</a>@endif</p>
                <h1>Guía de construcción</h1>
            </div>
            <p>Así recibe el asistente el sistema de diseño de {{ $proyecto->nombre }}: solo entran los tokens con el switch activo. Si algo se ve mal aquí, corrígelo antes de construir.</p>
        </div>
        <form method="get" class="acciones">
            <select name="pagina" onchange="this.form.submit()" aria-label="Página del prompt" style="width:auto;min-width:200px">
                <option value="">Prompt para cualquier página</option>
                @foreach ($proyecto->paginas->where('incluida', true) as $pg)
                    <option value="{{ $pg->id }}" @selected($pagina?->id === $pg->id)>Página: {{ $pg->nombre }}</option>
                @endforeach
            </select>
            <a class="btn" href="{{ route('proyecto.agents', $proyecto) }}" title="Contexto para el agente (Claude Code, Codex, Cursor…): guárdalo en la carpeta del cliente"><x-ic n="terminal" />Descargar AGENTS.md</a>
        </form>
    </div>

    <div class="guia">
        <div class="col">
            <section class="caja">
                <div class="caja-cab"><span class="icono-suave chico"><x-ic n="paleta" c="sm" /></span><h2>Colores</h2><span class="cifra">{{ $guia['colores']->count() }}</span></div>
                <div class="caja-cuerpo">
                    @if ($guia['colores']->isNotEmpty())
                        <div class="colores">
                            @foreach ($guia['colores'] as $t)
                                <div class="color"><i style="background: {{ $t->valor }}"></i><b>{{ $t->nota ?: 'Sin rol' }}</b><span class="mono">{{ strtoupper($t->valor) }}</span></div>
                            @endforeach
                        </div>
                    @else
                        <p class="nota">Sin colores activos. El asistente los lee del Figma o puedes agregarlos en el proyecto.</p>
                    @endif
                </div>
            </section>

            <section class="caja">
                <div class="caja-cab"><span class="icono-suave chico"><x-ic n="libro" c="sm" /></span><h2>Tipografía</h2></div>
                <div class="caja-cuerpo">
                    @forelse ($guia['tipografias'] as $t)
                        @php $f = \App\Support\PromptGuia::fuente($t->valor); @endphp
                        <div class="fuente">
                            <span class="muestra" style="font-family: '{{ $f }}', sans-serif">Aa</span>
                            <div>
                                <b>{{ $t->valor }}</b> <span>· {{ $t->nota ?: 'uso sin definir' }}</span>
                                <p style="font-family: '{{ $f }}', sans-serif">Diseño que se construye sección por sección</p>
                            </div>
                        </div>
                    @empty
                        <p class="nota">Sin tipografías activas.</p>
                    @endforelse
                    <p class="nota">La muestra usa la fuente si está instalada en este equipo; en el sitio se carga desde Elementor.</p>
                </div>
            </section>

            @if ($guia['espaciados']->isNotEmpty() || $guia['otros']->isNotEmpty())
                <section class="caja">
                    <div class="caja-cab"><h2>Espaciado y otros</h2></div>
                    <div class="caja-cuerpo">
                        @if ($guia['espaciados']->isNotEmpty())
                            <div class="espacios">
                                @foreach ($guia['espaciados'] as $t)
                                    @php $px = min((int) filter_var($t->valor, FILTER_SANITIZE_NUMBER_INT), 72); @endphp
                                    <div><i style="width:{{ max($px, 4) }}px;height:{{ max($px, 4) }}px"></i>{{ $t->valor }}</div>
                                @endforeach
                            </div>
                        @endif
                        @foreach ($guia['otros'] as $t)
                            <div class="token"><div class="txt"><b>{{ $t->valor }}</b><small>{{ $t->nota }}</small></div></div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="caja">
                <div class="caja-cab"><span class="icono-suave chico"><x-ic n="lista" c="sm" /></span><h2>Plan de secciones</h2>
                    <span class="nota derecha">{{ $pagina && $pagina->secciones()->exists() ? 'Plan de ' . $pagina->nombre : 'Plan estándar de la guía técnica' }}</span></div>
                <div class="caja-cuerpo">
                    <ol class="plan-lista">
                        @foreach ($guia['plan'] as [$nombre, $widget, $min])
                            <li>{{ $nombre }} <span>{{ $widget }}{{ $min ? " · {$min} min" : '' }}</span></li>
                        @endforeach
                    </ol>
                </div>
            </section>
        </div>

        <section class="caja" style="position:sticky;top:78px">
            <div class="caja-cab"><span class="icono-suave ora chico"><x-ic n="terminal" c="sm" /></span><h2>Prompt para el asistente</h2>
                <button class="btn chico p derecha" type="button" data-copiar="#prompt"><x-ic n="copiar" c="sm" />Copiar</button></div>
            <pre class="prompt" id="prompt">{{ $prompt }}</pre>
        </section>
    </div>
@endsection
