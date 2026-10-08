@extends('layouts.app')
@section('titulo', 'Conexión')
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <b aria-current="page">Conexión</b>@endsection

@php
    // Celda de versión: punto de color + versión + enlace si hay una más nueva.
    $version = function (?string $v, ?string $ultima = null, ?string $enlace = null) {
        if (! $v) return '<div class="celda"><i class="punto g"></i><span class="mut">No detectado</span></div>';
        $vieja = \App\Support\EntornoWP::desactualizada($v, $ultima);
        $extra = $vieja ? '<small><a href="' . e($enlace) . '" target="_blank" rel="noopener">' . e($ultima) . ' disponible ↗</a></small>' : '';
        return '<div class="celda"><i class="punto ' . ($vieja ? 'w' : '') . '"></i><div>' . e($v) . $extra . '</div></div>';
    };
@endphp

@section('contenido')
    <div class="cabeza">
        <div>
            <h1>Conexión del entorno</h1>
            <p>Cada proyecto se conecta a su propio WordPress con su propio servidor MCP de Elementor. Aquí ves cuál usa el asistente y qué versiones responde cada sitio.</p>
        </div>
        @if ($conexiones->isNotEmpty())
            <form method="post" action="{{ route('conexion.comprobar') }}" class="acciones">@csrf
                <button class="btn" type="submit"><x-ic n="refrescar" />Comprobar todos</button>
            </form>
        @endif
    </div>

    @if ($actual)
        @php $p = $actual->proyecto; @endphp
        <section class="caja">
            <div class="estado-gral">
                <span class="icono-suave {{ $actual->estado === 'conectado' ? 'ok' : ($actual->estado === 'sin_conexion' ? 'err' : '') }}"><x-ic :n="$actual->estado === 'conectado' ? 'check' : 'enchufe'" c="lg" /></span>
                <div>
                    <b>Conexión actual · {{ $p->nombre }}</b>
                    <p>
                        El asistente usa <code>{{ $actual->nombre_mcp }}</code> para este proyecto.
                        @if ($actual->comprobada_en) Comprobada {{ $actual->comprobada_en->diffForHumans() }}@if ($actual->via) vía {{ $actual->via }}@endif.
                        @else Aún no se comprueba. @endif
                    </p>
                </div>
                <div class="acciones">
                    <a class="btn fant" href="{{ route('proyecto', $p) }}">Ver proyecto</a>
                    <form method="post" action="{{ route('conexion.comprobar') }}" class="inline">@csrf
                        <input type="hidden" name="conexion_id" value="{{ $actual->id }}">
                        <button class="btn" type="submit"><x-ic n="refrescar" />Comprobar</button>
                    </form>
                </div>
            </div>
            <div class="actual">
                <div><small>Sitio</small><b><a href="{{ $actual->sitio_url }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $actual->sitio_url) }}</a></b>
                    <small>{{ $actual->rest_activo ? 'API REST activa (/wp-json/)' : ($actual->comprobada_en ? 'API REST sin respuesta' : '—') }}</small></div>
                <div><small>WordPress</small>{!! $version($actual->wp_version, $ultimas['wordpress'], \App\Support\EntornoWP::ENLACE_WP) !!}</div>
                <div><small>Elementor</small>{!! $version($actual->elementor_version, $ultimas['elementor'], \App\Support\EntornoWP::ENLACE_ELEMENTOR) !!}</div>
                <div><small>JetEngine</small>{!! $version($actual->jetengine_version) !!}</div>
            </div>
        </section>

        @php
            $configs = \App\Support\PromptElementor::configAgentes($actual);
            $varAuth = \App\Support\PromptElementor::variableAuth($p->nombre);
        @endphp
        <section class="caja" id="agente">
            <div class="caja-cab"><h2>Configurar en tu agente · <code>{{ $actual->nombre_mcp }}</code></h2>
                <span class="nota derecha">Cada agente tiene su formato: usa el tuyo tal cual</span></div>
            <div class="caja-cuerpo">
                <ol class="pasos">
                    <li><span>Guarda la credencial del sitio como variable de entorno de Windows <b>{{ $varAuth }}</b> con el valor
                        <code>Basic &lt;usuario:contraseña de aplicación en Base64&gt;</code> (cómo: README, sección 2.1). Nunca la pegues aquí ni en archivos del proyecto.</span></li>
                    <li><span>Copia el bloque de tu agente en el archivo indicado y reinicia el agente.</span></li>
                    <li><span>Comprueba que aparezcan <code>{{ $actual->nombre_mcp }}</code> y <code>figwright</code> entre sus servidores MCP.</span></li>
                </ol>
                @unless ($actual->endpoint)
                    <div class="aviso"><x-ic n="alerta" /><span>Esta conexión no tiene el endpoint exacto: pega el prompt de Elementor en “Agregar conexión” o reemplaza <code>/wp-json/…</code> por la URL del prompt.</span></div>
                @endunless
                @foreach ($configs as $clave => $cfg)
                    <details class="config-agente" @if ($loop->first) open @endif>
                        <summary><b>{{ $cfg['titulo'] }}</b> <span class="nota">· {{ $cfg['archivo'] }}</span></summary>
                        <div class="config-cuerpo">
                            <pre class="bloque" id="cfg-{{ $clave }}">{{ $cfg['codigo'] }}</pre>
                            <button class="copiar" type="button" data-copiar="#cfg-{{ $clave }}" title="Copiar" aria-label="Copiar la configuración de {{ $cfg['titulo'] }}"><x-ic n="copiar" c="sm" /></button>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    <section class="caja">
        <div class="caja-cab"><h2>Sitios por proyecto</h2><span class="cifra">{{ $conexiones->count() }}</span>
            <span class="nota derecha">
                @if ($ultimas['wordpress'] || $ultimas['elementor'])
                    Últimas en WordPress.org: WordPress {{ $ultimas['wordpress'] ?? '?' }} · Elementor {{ $ultimas['elementor'] ?? '?' }}
                @else
                    No se pudo consultar WordPress.org (sin internet)
                @endif
            </span></div>
        <div class="tabla-env">
            <table class="tabla">
                <thead><tr><th>Proyecto</th><th>Servidor MCP</th><th>WordPress</th><th>Elementor</th><th>JetEngine</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($conexiones as $c)
                    <tr>
                        <td><a href="{{ route('proyecto', $c->proyecto) }}"><b style="font-weight:500">{{ $c->proyecto->nombre }}</b></a>
                            <div class="nota">{{ preg_replace('#^https?://#', '', $c->sitio_url) }}@if ($c->via) → {{ $c->via }}@endif</div></td>
                        <td><code>{{ $c->nombre_mcp }}</code>@if ($c->usuario_wp)<div class="nota">usuario {{ $c->usuario_wp }}</div>@endif</td>
                        <td>{!! $version($c->wp_version, $ultimas['wordpress'], \App\Support\EntornoWP::ENLACE_WP) !!}</td>
                        <td>{!! $version($c->elementor_version, $ultimas['elementor'], \App\Support\EntornoWP::ENLACE_ELEMENTOR) !!}</td>
                        <td>{!! $version($c->jetengine_version) !!}</td>
                        <td class="nw">
                            @if ($c->estado === 'conectado')<span class="tag ok">Conectado</span>
                            @elseif ($c->estado === 'sin_conexion')<span class="tag err">Sin respuesta</span>
                            @else <span class="tag">Sin comprobar</span>@endif
                            <div class="nota">{{ $c->comprobada_en?->diffForHumans() ?? 'nunca' }}</div>
                        </td>
                        <td class="nw">
                            <a class="btn chico fant" href="{{ route('conexion', ['conexion' => $c->id]) }}#agente">Configurar</a>
                            @if ($c->prompt_saneado)
                                <button class="btn chico fant" type="button" onclick="const d=document.getElementById('pr-{{ $c->id }}'); d.hidden=!d.hidden">Prompt</button>
                            @endif
                            <form method="post" action="{{ route('conexion.eliminar', $c) }}" class="inline" onsubmit="return confirm('¿Eliminar esta conexión del panel?')">@csrf @method('DELETE')
                                <button class="btn chico fant peligro" type="submit" title="Eliminar" aria-label="Eliminar la conexión {{ $c->nombre_mcp }}"><x-ic n="basura" c="sm" /></button>
                            </form>
                        </td>
                    </tr>
                    @if ($c->prompt_saneado)
                        <tr id="pr-{{ $c->id }}" hidden><td colspan="7">
                            <pre class="bloque">{{ $c->prompt_saneado }}</pre>
                            <p class="nota" style="margin-top:6px">Copia guardada sin contraseñas ni tokens. La versión completa solo vive en la consola.</p>
                        </td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="vacio">Aún no hay conexiones. Agrega la primera con el formulario de abajo.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="rejilla">
        <section class="caja" id="nueva">
            <div class="caja-cab"><span class="icono-suave ora chico"><x-ic n="mas" c="sm" /></span><h2>Agregar conexión</h2></div>
            <form method="post" action="{{ route('conexion.guardar') }}" class="caja-cuerpo" style="display:flex;flex-direction:column;gap:14px">@csrf
                <div class="form-fila">
                    <div class="campo"><label for="proyecto_id">Proyecto</label>
                        <select id="proyecto_id" name="proyecto_id" required onchange="const o=this.selectedOptions[0]; const n=document.getElementById('nombre_mcp'); if(!n.dataset.tocado) n.value=o.dataset.mcp||''; const s=document.getElementById('sitio_url'); if(!s.value) s.value=o.dataset.sitio||'';">
                            <option value="">Elige el proyecto…</option>
                            @foreach ($proyectos as $pr)
                                <option value="{{ $pr->id }}" data-mcp="{{ \App\Support\PromptElementor::nombreSugerido($pr->nombre) }}" data-sitio="{{ $pr->sitio_wp }}" @selected(old('proyecto_id') == $pr->id || (! old('proyecto_id') && $sinConexion->first()?->id === $pr->id))>{{ $pr->nombre }}{{ $sinConexion->contains('id', $pr->id) ? '' : ' (ya tiene conexión)' }}</option>
                            @endforeach
                        </select>
                        @error('proyecto_id')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="campo"><label for="nombre_mcp">Nombre del servidor MCP</label>
                        <input id="nombre_mcp" name="nombre_mcp" type="text" value="{{ old('nombre_mcp', $sinConexion->first() ? \App\Support\PromptElementor::nombreSugerido($sinConexion->first()->nombre) : '') }}" maxlength="80" placeholder="elementor-mi-proyecto" oninput="this.dataset.tocado=1">
                        @error('nombre_mcp')<span class="error">{{ $message }}</span>@enderror</div>
                </div>
                <div class="campo">
                    <label for="prompt_mcp">Prompt de conexión que generó Elementor</label>
                    <textarea id="prompt_mcp" name="prompt_mcp" rows="6" maxlength="8000" placeholder="Pega aquí el texto de Elementor › Elementor MCP › Generate Prompt. El panel toma el sitio, el endpoint y el usuario, y oculta la contraseña antes de guardar."></textarea>
                    <small>La contraseña de aplicación nunca se guarda: se reemplaza por [oculto]. Pega el mismo prompt completo en tu agente (Claude Code, Codex, Cursor…) para que registre el servidor.</small>
                </div>
                <div class="form-fila">
                    <div class="campo"><label for="sitio_url">URL del sitio WordPress</label>
                        <input id="sitio_url" name="sitio_url" type="url" value="{{ old('sitio_url', $sinConexion->first()?->sitio_wp) }}" maxlength="200" placeholder="Se completa desde el prompt">
                        @error('sitio_url')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="campo"><label for="usuario_wp">Usuario de WordPress</label>
                        <input id="usuario_wp" name="usuario_wp" type="text" value="{{ old('usuario_wp') }}" maxlength="80" placeholder="Opcional · se completa desde el prompt"></div>
                    <div class="campo"><label for="app_password">Contraseña de aplicación</label>
                        <input id="app_password" name="app_password" type="password" autocomplete="new-password" maxlength="80" placeholder="Opcional si pegaste el prompt">
                        <small>Va directo a Windows como variable de usuario; el panel no la guarda.</small></div>
                </div>
                <div><button class="btn p" type="submit"><x-ic n="enchufe" />Guardar conexión</button></div>
            </form>
        </section>

        <div class="col">
            <section class="caja">
                <div class="caja-cab"><span class="icono-suave chico"><x-ic n="libro" c="sm" /></span><h2>Cómo se conecta un proyecto nuevo</h2></div>
                <div class="caja-cuerpo">
                    <ol class="pasos">
                        <li><span>En el WordPress del cliente entra a <b>Elementor › Elementor MCP</b>, activa el acceso, elige tu agente (por ejemplo <b>Claude Code</b> o <b>Codex</b>) y pulsa <b>Generate Prompt</b>. Requiere Elementor 4.3 o superior y WordPress 6.8 o superior.</span></li>
                        <li><span>Abre tu agente <b>dentro de la carpeta de ese proyecto</b> y pega el prompt. Así el servidor queda registrado solo para esa carpeta y el asistente no confunde un sitio con otro.</span></li>
                        <li><span>Pega el mismo prompt aquí al lado. El panel guarda el sitio y el nombre del servidor, sin la contraseña, y comprueba las versiones.</span></li>
                        <li><span>Verifica que el servidor aparezca con el nombre que registraste (en Claude Code: <code>claude mcp list</code>).</span></li>
                    </ol>
                </div>
            </section>

            <section class="caja">
                <div class="caja-cab"><span class="icono-suave chico"><x-ic n="terminal" c="sm" /></span><h2>Comandos de la consola</h2></div>
                @foreach ([
                    ['claude mcp list', 'Servidores MCP de la carpeta (Claude Code; cada agente tiene el suyo)'],
                    ['php artisan conexion:comprobar', 'Comprueba versiones de todos los sitios'],
                    ['php artisan registro:figma', 'Carga páginas y tokens leídos del Figma'],
                    ['php artisan registro:cola', 'Correcciones pendientes para el asistente'],
                    ['php artisan registro:add', 'Registra una sección construida'],
                ] as [$cmd, $txt])
                    <div class="cmd"><code>{{ $cmd }}</code><span>{{ $txt }}</span>
                        <button class="copiar" type="button" data-texto="{{ $cmd }}" title="Copiar" aria-label="Copiar {{ $cmd }}"><x-ic n="copiar" c="sm" /></button></div>
                @endforeach
            </section>
        </div>
    </div>
@endsection
