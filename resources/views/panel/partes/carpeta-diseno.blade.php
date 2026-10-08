{{-- Carpeta del proyecto en Windows y versiones del diseño (Design/vN). --}}
@php
    $carpetaOk = \App\Support\CarpetaProyecto::disponible();
    $ruta = \App\Support\CarpetaProyecto::rutaWindows($proyecto);
    $activa = $proyecto->disenos->firstWhere('estado', 'activa');
    $varAuth = \App\Support\PromptElementor::variableAuth($proyecto->nombre);
@endphp
<div class="rejilla">
    <section class="caja">
        <div class="caja-cab"><h2>Carpeta del proyecto</h2><span class="nota derecha">Lista para Claude Code, Codex, Cursor y OpenCode</span></div>
        <div class="caja-cuerpo">
            @if (! $carpetaOk)
                <div class="aviso"><x-ic n="alerta" /><span>Falta la carpeta base de proyectos. Ejecuta <code>scripts\instalar-windows.ps1</code>: crea <code>%USERPROFILE%\AlmaProyectos</code> y la registra en el panel.</span></div>
            @else
                <p class="ruta-carpeta"><x-ic n="carpeta" c="sm" /><code>{{ $ruta ?? 'aún sin crear' }}</code></p>
                <div class="acciones">
                    @foreach (['terminal' => ['terminal', 'Abrir terminal aquí'], 'cursor' => ['externo', 'Abrir en Cursor'], 'carpeta' => ['carpeta', 'Abrir carpeta']] as $que => [$icono, $texto])
                        <form method="post" action="{{ route('proyecto.abrir', [$proyecto, $que]) }}">@csrf
                            <button class="btn {{ $que === 'terminal' ? 'p' : '' }}" type="submit"><x-ic :n="$icono" />{{ $texto }}</button>
                        </form>
                    @endforeach
                    <form method="post" action="{{ route('proyecto.carpeta', $proyecto) }}">@csrf
                        <button class="btn fant" type="submit"><x-ic n="refrescar" />{{ $ruta ? 'Actualizar archivos' : 'Crear carpeta' }}</button>
                    </form>
                </div>
                <ul class="lista-chica">
                    <li><b>AGENTS.md</b> y <b>CLAUDE.md</b>: contexto del proyecto para el agente.</li>
                    <li>Configuración MCP de cada agente con figwright{{ $proyecto->conexion ? ' y ' . $proyecto->conexion->nombre_mcp : '' }}.</li>
                    <li>
                        Credencial del sitio:
                        @if ($proyecto->conexion?->credencial_en_equipo)
                            <span class="tag ok">guardada en Windows</span> como <code>{{ $varAuth }}</code>
                        @elseif ($proyecto->conexion)
                            <span class="tag rev">falta</span> pega el prompt de Elementor en <a href="{{ route('conexion', ['conexion' => $proyecto->conexion->id]) }}">Conexión</a>
                        @else
                            <span class="tag">sin conexión</span>
                        @endif
                    </li>
                </ul>
                <p class="nota">Abre el agente desde “Abrir terminal aquí”: esa terminal ya ve la credencial. La primera vez, Claude Code pide aprobar <code>.mcp.json</code> y Codex marcar la carpeta como de confianza.</p>
            @endif
        </div>
    </section>

    <section class="caja">
        <div class="caja-cab"><h2>Diseño</h2><span class="cifra">{{ $proyecto->disenos->count() }}</span>
            @if ($ruta)
                <form method="post" action="{{ route('proyecto.abrir', [$proyecto, 'diseno']) }}" class="derecha">@csrf
                    <button class="btn chico fant" type="submit"><x-ic n="carpeta" c="sm" />Design</button>
                </form>
            @endif
        </div>
        @forelse ($proyecto->disenos as $d)
            @php $dif = $d->estado === 'nueva' ? \App\Support\Disenos::diferencias($d, $activa) : null; @endphp
            <div class="version {{ $d->estado }}">
                <div>
                    <b>v{{ $d->version }}</b>
                    @if ($d->estado === 'activa')<span class="tag ok">En uso</span>
                    @elseif ($d->estado === 'nueva')<span class="tag qa">Nueva</span>
                    @else <span class="tag">Anterior</span>@endif
                    <span class="nota">· {{ \App\Support\Disenos::texto($d->resumen) }} · {{ $d->created_at->diffForHumans() }}</span>
                    @if ($dif)
                        <ul class="lista-chica">
                            @foreach (['paginas_nuevas' => 'Páginas nuevas', 'paginas_quitadas' => 'Páginas que ya no están', 'secciones_nuevas' => 'Secciones nuevas', 'secciones_quitadas' => 'Secciones que ya no están'] as $k => $titulo)
                                @if ($dif[$k])<li><b>{{ $titulo }}:</b> {{ implode(', ', array_slice($dif[$k], 0, 6)) }}{{ count($dif[$k]) > 6 ? '…' : '' }}</li>@endif
                            @endforeach
                            @if (! array_filter($dif))<li>Sin cambios de páginas ni secciones respecto de la versión en uso.</li>@endif
                        </ul>
                    @endif
                </div>
                @if ($d->estado !== 'activa')
                    <form method="post" action="{{ route('diseno.usar', $d) }}">@csrf
                        <button class="btn chico {{ $d->estado === 'nueva' ? 'p' : '' }}" type="submit">Usar esta versión</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="caja-cuerpo">
                <div class="aviso-suave"><x-ic n="figma" />Aún no hay versiones. Cuando el agente lea el Figma y ejecute <code>diseno:guardar</code>, aparecerá aquí como v1 y quedará en uso.</div>
            </div>
        @endforelse
        @if ($proyecto->disenos->where('estado', 'nueva')->isNotEmpty())
            <p class="nota caja-cuerpo">Las versiones nuevas no cambian el panel hasta que pulses “Usar esta versión”. Al usarla se agregan las páginas y secciones nuevas; las que ya no están no se borran.</p>
        @endif
    </section>
</div>
