@extends('layouts.app')
@section('titulo', $proyecto->nombre)
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <b aria-current="page">{{ $proyecto->nombre }}</b>@endsection

@section('contenido')
    @php
        $alcance = $paginas->where('incluida', true);
        $listas = $alcance->whereIn('estado', ['aprobada', 'en_qc'])->count();
        $tokensColor = $proyecto->tokens->where('tipo', 'color');
        $tokensOtros = $proyecto->tokens->where('tipo', '!=', 'color');
    @endphp

    <div class="cabeza">
        <div class="titulo-con-padre">
            <a class="btn-subir" href="{{ route('dashboard') }}" aria-label="Volver al panel" title="Volver al panel (Alt+↑)" aria-keyshortcuts="Alt+ArrowUp"><x-ic n="atras" /></a>
            <div>
                <p class="contexto">Proyecto del <a href="{{ route('dashboard') }}">Panel</a></p>
                <h1>{{ $proyecto->nombre }}</h1>
            </div>
            <p>
                {{ $proyecto->cliente ? $proyecto->cliente . '.' : '' }}
                {{ $alcance->count() ? "{$listas} de {$alcance->count()} páginas listas." : 'Aún sin páginas: el asistente las carga al leer el Figma (registro:figma).' }}
            </p>
            <div class="enlaces">
                @if ($proyecto->sitio_wp)
                    <a class="enlace" href="{{ $proyecto->sitio_wp }}" target="_blank" rel="noopener"><x-ic n="globo" c="sm" /><b>{{ preg_replace('#^https?://#', '', $proyecto->sitio_wp) }}</b></a>
                @endif
                @if ($proyecto->archivo_figma)
                    @if (str_starts_with($proyecto->archivo_figma, 'https://'))
                        <a class="enlace" href="{{ $proyecto->archivo_figma }}" target="_blank" rel="noopener"><x-ic n="figma" c="sm" /><b>Archivo de Figma</b></a>
                    @else
                        <span class="enlace"><x-ic n="figma" c="sm" /><b>{{ $proyecto->archivo_figma }}</b></span>
                    @endif
                @endif
                <a class="enlace" href="{{ route('conexion') }}"><x-ic n="enchufe" c="sm" />
                    @if ($proyecto->conexion)<b>{{ $proyecto->conexion->nombre_mcp }}</b>@else Sin conexión MCP @endif
                </a>
                <span class="enlace"><x-ic n="reloj" c="sm" />Actualizado {{ $proyecto->updated_at->diffForHumans() }}</span>
            </div>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('proyecto.guia', $proyecto) }}"><x-ic n="libro" />Ver guía y prompt</a>
            <a class="btn" href="{{ route('proyecto.agents', $proyecto) }}" title="Contexto para el agente (Claude Code, Codex, Cursor…): guárdalo en la carpeta del cliente"><x-ic n="terminal" />AGENTS.md</a>
            <button class="btn fant peligro" type="button" onclick="document.getElementById('eliminar-proyecto').showModal()"><x-ic n="basura" />Eliminar</button>
        </div>
    </div>

    @include('panel.partes.eliminar-proyecto')

    <div class="rejilla ancha">
        <section class="caja">
            <div class="caja-cab"><h2>Páginas</h2><span class="cifra">{{ $paginas->count() }}</span>
                <span class="nota derecha">El switch decide qué páginas se maquetan</span></div>
            @if ($paginas->isNotEmpty())
                <div class="pag cab"><span>Incluir</span><span>Página</span><span>Secciones</span><span>Estado</span><span style="text-align:right">Min</span><span></span></div>
            @endif
            @forelse ($paginas as $rp)
                @php
                    $estados = ($secciones[$rp->id] ?? collect())->pluck('estado');
                    $planeadas = max($rp->secciones_total, $estados->count());
                    $esLista = $rp->estado === 'aprobada';
                @endphp
                <div class="pag {{ $rp->incluida ? '' : 'fuera' }}">
                    <form method="post" action="{{ route('pagina.incluir', $rp->id) }}">@csrf
                        <input type="hidden" name="incluida" value="{{ $rp->incluida ? 0 : 1 }}">
                        <input type="checkbox" class="switch" @checked($rp->incluida) onchange="this.form.submit()" aria-label="{{ $rp->incluida ? 'Quitar del alcance' : 'Incluir en el alcance' }}: {{ $rp->nombre }}">
                    </form>
                    <div class="nom"><b>{{ $rp->nombre }}</b>
                        <small>{{ $rp->origen === 'detectada' ? 'Figma' : 'Manual' }} · {{ $rp->incluida ? ($planeadas ? $planeadas . ' secciones' : 'sin plan') : 'fuera del alcance' }}</small></div>
                    <div class="seg" title="{{ $estados->count() }} secciones registradas">
                        @foreach ($estados as $e)<i class="{{ ['aprobada' => 'a', 'construida' => 'c', 'construyendo' => 'r'][$e] ?? '' }}"></i>@endforeach
                        @for ($i = $estados->count(); $i < min($planeadas, 14); $i++)<i></i>@endfor
                    </div>
                    <span>
                        @if (! $rp->incluida) <span class="tag">Excluida</span>
                        @elseif ($esLista) <span class="tag ok">Lista para QA</span>
                        @else @include('panel.partes.estado', ['estado' => $rp->estado])
                        @endif
                    </span>
                    <span class="min">{{ $rp->min_total ?: '—' }}</span>
                    <span class="abrir">@if ($rp->incluida)<a class="btn chico fant" href="{{ route('pagina', $rp->id) }}">Abrir <x-ic n="flecha" c="sm" /></a>@endif</span>
                </div>
            @empty
                <div class="caja-cuerpo">
                    <div class="aviso-suave"><x-ic n="figma" />
                        <div>Este proyecto aún no tiene páginas. Cuando el asistente lea el archivo de Figma ejecutará
                            <code>registro:figma</code> y esta vista se actualizará sola con las páginas y tokens detectados.</div>
                    </div>
                </div>
            @endforelse
            <details class="caja-cuerpo" style="border-top:1px solid var(--line)" {{ $errors->has('nombre') ? 'open' : '' }}>
                <summary class="btn chico fant"><x-ic n="mas" c="sm" />Agregar página a mano</summary>
                <form method="post" action="{{ route('pagina.guardar', $proyecto) }}" class="form-linea" style="margin-top:12px">@csrf
                    <div class="campo"><label for="nombre">Nombre de la página</label>
                        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="Ej.: Aviso legal" required maxlength="120">
                        @error('nombre')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="campo corto"><label for="secciones_total">Secciones</label>
                        <input id="secciones_total" name="secciones_total" type="number" min="0" max="50" value="{{ old('secciones_total') }}" placeholder="7"></div>
                    <button class="btn p" type="submit">Agregar</button>
                </form>
            </details>
        </section>

        <section class="caja">
            <div class="caja-cab"><h2>Tokens de diseño</h2><span class="cifra">{{ $proyecto->tokens->count() }}</span>
                <a class="ver" href="{{ route('proyecto.guia', $proyecto) }}"><x-ic n="libro" c="sm" />Guía</a></div>

            @if ($tokensColor->isNotEmpty())
                <div class="grupo-tok">
                    <h3>Colores</h3>
                    @foreach ($tokensColor as $t)
                        @include('panel.partes.token', ['t' => $t])
                    @endforeach
                </div>
            @endif
            @if ($tokensOtros->isNotEmpty())
                <div class="grupo-tok">
                    <h3>Tipografía, espaciado y otros</h3>
                    @foreach ($tokensOtros as $t)
                        @include('panel.partes.token', ['t' => $t])
                    @endforeach
                </div>
            @endif
            @if ($proyecto->tokens->isEmpty())
                <p class="vacio">Sin tokens todavía. El asistente los carga al leer el Figma, o agrégalos abajo.</p>
            @endif

            <details class="grupo-tok" {{ $errors->has('valor') ? 'open' : '' }}>
                <summary class="btn chico fant"><x-ic n="mas" c="sm" />Agregar token</summary>
                <form method="post" action="{{ route('token.guardar', $proyecto) }}" style="display:flex;flex-direction:column;gap:10px;margin-top:12px">@csrf
                    <div class="form-fila">
                        <div class="campo"><label for="tipo">Tipo</label>
                            <select id="tipo" name="tipo"><option value="color">Color</option><option value="tipografia">Tipografía</option><option value="espaciado">Espaciado</option><option value="otro">Otro</option></select></div>
                        <div class="campo"><label for="valor">Valor</label><input id="valor" name="valor" type="text" required maxlength="120" placeholder="#2F6B4F · Poppins · 8 px"></div>
                    </div>
                    @error('valor')<span class="error">{{ $message }}</span>@enderror
                    <div class="campo"><label for="nota">Nota</label><input id="nota" name="nota" type="text" maxlength="160" placeholder="primario, títulos, contenedor…"></div>
                    <div><button class="btn p" type="submit">Agregar token</button></div>
                </form>
            </details>
            <div class="grupo-tok">
                <div class="aviso-suave"><x-ic n="figma" />Los colores no se editan aquí: si uno está mal, elimínalo y registra el correcto desde el Figma. El switch decide qué tokens entran a la guía.</div>
            </div>
        </section>
    </div>
@endsection
