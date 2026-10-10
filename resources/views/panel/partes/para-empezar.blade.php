{{-- "Para empezar": qué falta para que el agente pueda construir. Se marca solo y desaparece cuando todo está listo. --}}
@php
    $rutaCarpeta = \App\Support\CarpetaProyecto::ruta($proyecto);
    $hayCarpeta = $rutaCarpeta && is_file($rutaCarpeta . '/AGENTS.md');
    $baseLista = \App\Support\CarpetaProyecto::disponible();
    $hayConexion = (bool) $proyecto->conexion;
    $enEquipo = (bool) $proyecto->conexion?->credencial_en_equipo;
    $hayDiseno = $proyecto->disenos->contains('estado', 'activa');
    $primera = $paginas->where('incluida', true)->first();
    $construyo = \App\Models\Seccion::whereIn('pagina_id', $paginas->pluck('id'))->where('estado', '!=', 'planificada')->exists();
    $mcp = $proyecto->conexion?->nombre_mcp ?? \App\Support\PromptElementor::nombreSugerido($proyecto->nombre);
    $msgLeer = "Comprueba la conexión (figwright y {$mcp}), lee el Figma del proyecto y guárdalo en el panel.";
    $msgConstruir = 'Construye la página ' . ($primera->nombre ?? '<Página>') . ' siguiendo la guía; usa el lector de Figma en paralelo.';
    $pasos = [
        ['ok' => $hayCarpeta, 'titulo' => 'Carpeta del proyecto', 'ok_txt' => 'AGENTS.md y la configuración de los 4 agentes están listos.', 'falta' => $baseLista ? 'Crea la carpeta con su AGENTS.md, la configuración MCP y los roles de agente.' : 'Falta la carpeta base de proyectos: ejecuta scripts\\instalar-windows.ps1 una vez en este equipo.'],
        ['ok' => $hayConexion && $enEquipo, 'titulo' => 'Conexión del sitio', 'ok_txt' => "{$mcp} con su credencial en Windows.", 'falta' => $hayConexion ? 'Falta guardar la credencial en Windows: vuelve a pegar el prompt de Elementor en Conexión.' : 'Pega el prompt de Elementor del sitio en Conexión.'],
        ['ok' => $hayDiseno, 'titulo' => 'Figma leído', 'ok_txt' => 'Hay una versión del diseño en uso.', 'falta' => 'Abre la terminal del proyecto y pide al agente que lea el Figma.'],
        ['ok' => $construyo, 'titulo' => 'Primera sección construida', 'ok_txt' => 'El agente ya registra secciones.', 'falta' => $primera ? 'Pide construir la primera página; el lector de Figma prepara las secciones en paralelo.' : 'Marca con el switch qué páginas se maquetan.'],
    ];
    $siguiente = collect($pasos)->search(fn ($p) => ! $p['ok']);
@endphp
@if ($siguiente !== false)
    <section class="caja para-empezar" aria-label="Para empezar">
        <div class="caja-cab"><span class="icono-suave ora chico"><x-ic n="chispa" c="sm" /></span><h2>Para empezar</h2>
            <span class="nota derecha">{{ collect($pasos)->where('ok', true)->count() }} de {{ count($pasos) }} listos</span></div>
        <ol class="arranque">
            @foreach ($pasos as $i => $paso)
                <li class="{{ $paso['ok'] ? 'hecho' : ($i === $siguiente ? 'actual' : '') }}">
                    <span class="marca-paso">@if ($paso['ok'])<x-ic n="check" c="sm" />@else{{ $i + 1 }}@endif</span>
                    <div>
                        <b>{{ $paso['titulo'] }}</b>
                        <p>{{ $paso['ok'] ? $paso['ok_txt'] : $paso['falta'] }}</p>
                        @if ($i === $siguiente)
                            <div class="acciones">
                                @if ($i === 0 && $baseLista)
                                    <form method="post" action="{{ route('proyecto.carpeta', $proyecto) }}">@csrf<button class="btn chico p" type="submit"><x-ic n="carpeta" c="sm" />Preparar carpeta</button></form>
                                @elseif ($i === 0)
                                    <a class="btn chico" href="{{ route('flujo') }}"><x-ic n="libro" c="sm" />Ver cómo instalar</a>
                                @elseif ($i === 1)
                                    <a class="btn chico p" href="{{ route('conexion') }}"><x-ic n="enchufe" c="sm" />Ir a Conexión</a>
                                @elseif ($i === 2)
                                    <button class="btn chico p" type="button" data-texto="{{ $msgLeer }}"><x-ic n="copiar" c="sm" />Copiar primer mensaje</button>
                                    <form method="post" action="{{ route('proyecto.abrir', [$proyecto, 'terminal']) }}">@csrf<button class="btn chico" type="submit"><x-ic n="terminal" c="sm" />Abrir terminal</button></form>
                                @elseif ($primera)
                                    <button class="btn chico p" type="button" data-texto="{{ $msgConstruir }}"><x-ic n="copiar" c="sm" />Copiar mensaje para construir</button>
                                    <form method="post" action="{{ route('proyecto.abrir', [$proyecto, 'terminal']) }}">@csrf<button class="btn chico" type="submit"><x-ic n="terminal" c="sm" />Abrir terminal</button></form>
                                @endif
                            </div>
                            @if ($i >= 2)<p class="nota">Claude Code y OpenCode reparten el trabajo con sus subagentes; en Codex o Cursor abre una segunda terminal para el lector de Figma.</p>@endif
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
@endif
