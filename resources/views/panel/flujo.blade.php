@extends('layouts.app')
@section('titulo', 'Cómo funciona')
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <b aria-current="page">Cómo funciona</b>@endsection

@php $alma = 'powershell -NoProfile -File "$HOME\.alma\alma.ps1"'; @endphp

@section('contenido')
    <div class="cabeza">
        <div>
            <h1>Cómo funciona el flujo de trabajo</h1>
            <p>El agente construye en la consola y registra; el panel supervisa y tú apruebas. Esta guía resume qué se hace una vez, qué se hace por cada proyecto y cómo es el ciclo diario.</p>
        </div>
        <a class="btn" href="{{ asset('docs/flujo-de-trabajo.pdf') }}" target="_blank" rel="noopener"><x-ic n="externo" />Versión PDF</a>
    </div>

    <section class="caja">
        <div class="caja-cab"><h2>Las piezas</h2></div>
        <div class="piezas">
            <div><span class="icono-suave lav"><x-ic n="robot" /></span><b>Agente</b><p>Claude Code, Codex u OpenCode, abierto en la carpeta del cliente (Windows). Lee el Figma y construye en Elementor, siempre en borrador.</p></div>
            <div><span class="icono-suave ora"><x-ic n="inicio" /></span><b>Panel</b><p>Esta web, corriendo en WSL Ubuntu. Muestra el avance en vivo, guarda tokens, conexiones y tiempos, y es donde se aprueba.</p></div>
            <div><span class="icono-suave"><x-ic n="figma" /></span><b>figwright</b><p>MCP que lee el diseño desde Figma de escritorio (con su plugin abierto).</p></div>
            <div><span class="icono-suave"><x-ic n="enchufe" /></span><b>MCP de Elementor</b><p>Uno por sitio, con el nombre del proyecto (ej. <code>elementor-cota</code>). Es el que construye en WordPress.</p></div>
            <div><span class="icono-suave ok"><x-ic n="terminal" /></span><b>Puente <code>alma.ps1</code></b><p>Lleva los comandos del agente (Windows) al panel (Ubuntu). Lo instala <code>scripts\instalar-windows.ps1</code>.</p></div>
        </div>
    </section>

    <div class="rejilla">
        <section class="caja">
            <div class="caja-cab"><h2>Una vez por equipo</h2></div>
            <div class="caja-cuerpo">
                <ol class="pasos">
                    <li><span><b>Panel en Ubuntu:</b> clonar, <code>composer install</code>, <code>.env</code>, <code>php artisan migrate</code>. Requiere PHP 8.3+ con sqlite3, mbstring, curl, xml y zip.</span></li>
                    <li><span><b>Lado Windows:</b> ejecutar <code>scripts\instalar-windows.ps1 -RegistrarFigwright</code>. Instala el puente, la skill para todos los agentes y registra figwright.</span></li>
                    <li><span><b>Figma de escritorio</b> con el plugin figwright (se importa desde su zip de GitHub y se actualiza a mano).</span></li>
                    <li><span><b>Comprobar:</b> <code>alma.ps1 diagnostico</code>. Todo debe salir en verde.</span></li>
                </ol>
            </div>
        </section>

        <section class="caja">
            <div class="caja-cab"><h2>Una vez por proyecto</h2></div>
            <div class="caja-cuerpo">
                <ol class="pasos">
                    <li><span><b>Crear el proyecto</b> en el panel y pegar el prompt de Elementor. El panel crea <code>AlmaProyectos\&lt;Proyecto&gt;</code> con <code>AGENTS.md</code>, <code>CLAUDE.md</code> y la configuración MCP de Claude Code, Codex, Cursor y OpenCode.</span></li>
                    <li><span><b>Credencial automática:</b> queda como variable de Windows <code>ALMA_&lt;PROYECTO&gt;_AUTH</code>; el panel no la guarda y la configuración solo la nombra.</span></li>
                    <li><span><b>“Abrir terminal aquí”</b> (o “Abrir en Cursor”) desde el proyecto y ejecutar el agente. La primera vez: Claude Code pide aprobar <code>.mcp.json</code> y Codex marcar la carpeta como de confianza.</span></li>
                    <li><span><b>El agente verifica la conexión</b> (figwright y el sitio) antes de leer nada.</span></li>
                </ol>
            </div>
        </section>
    </div>

    <section class="caja">
        <div class="caja-cab"><h2>El ciclo de construcción</h2><span class="nota derecha">Lo que pasa en cada página, de inicio a QA</span></div>
        <ol class="ciclo">
            <li>
                <span class="paso-n">1</span>
                <div><b>Leer el Figma</b><p>“Lee el Figma del proyecto y guárdalo en el panel.” Se guarda como <code>Design\v1</code> (lectura y capturas) con páginas, secciones con su ID de Figma y tokens. Si el Figma cambia y se relee, queda como v2 y el panel no cambia hasta que pulses “Usar esta versión”.</p>
                    <code>{{ $alma }} diseno:guardar "Cota" --archivo=…</code></div>
            </li>
            <li>
                <span class="paso-n">2</span>
                <div><b>Revisar en el panel</b><p>En el proyecto, el switch decide qué páginas se maquetan y qué tokens entran a la guía. Corrige aquí lo que no corresponda.</p></div>
            </li>
            <li>
                <span class="paso-n">3</span>
                <div><b>Construir sección por sección</b><p>“Construye la página Inicio siguiendo la guía.” El agente pide su guía (con los IDs de Figma), construye una sección en borrador y la registra.</p>
                    <code>{{ $alma }} guia:prompt "Cota" "Inicio"</code></div>
            </li>
            <li>
                <span class="paso-n">4</span>
                <div><b>Aprobar o pedir corrección</b><p>En la página, cada sección aparece “Por revisar”. Apruébala o escribe qué corregir: la corrección vuelve a la cola del agente, que la atiende primero.</p>
                    <code>{{ $alma }} registro:cola "Cota"</code></div>
            </li>
            <li>
                <span class="paso-n">5</span>
                <div><b>Solicitar QA</b><p>Con todas las secciones aprobadas, pega el link de Trello y copia el mensaje estándar para el canal. La página pasa a “QA solicitado”.</p></div>
            </li>
        </ol>
    </section>

    <div class="rejilla">
        <section class="caja">
            <div class="caja-cab"><h2>Cada día</h2></div>
            <div class="caja-cuerpo">
                <ol class="pasos">
                    <li><span>En Ubuntu: <code>cd ~/proyectos/asistente-alma-panel && php artisan serve</code> → http://127.0.0.1:8090.</span></li>
                    <li><span>Abre el agente en la carpeta del cliente y Figma con el plugin figwright.</span></li>
                    <li><span>Un chat nuevo no necesita contexto: el <code>AGENTS.md</code> se carga solo. Basta con “revisa la cola de correcciones y sigue con Nosotros”.</span></li>
                    <li><span>Deja el panel abierto al lado: se recarga solo con cada sección registrada.</span></li>
                </ol>
            </div>
        </section>

        <section class="caja">
            <div class="caja-cab"><h2>Qué significa cada estado</h2></div>
            <div class="caja-cuerpo estados">
                <div><span class="tag">Pendiente</span><p>La página existe, aún sin construir.</p></div>
                <div><span class="tag">Planificada</span><p>Sección en el plan, sin construir.</p></div>
                <div><span class="tag e-construida">Por revisar</span><p>El agente la construyó; falta tu revisión.</p></div>
                <div><span class="tag obra">En construcción / En corrección</span><p>El agente está trabajando o atendiendo una corrección.</p></div>
                <div><span class="tag ok">Aprobada</span><p>Revisada por el desarrollador.</p></div>
                <div><span class="tag qa">QA solicitado</span><p>Mensaje enviado al canal; espera control de calidad.</p></div>
            </div>
        </section>
    </div>

    <section class="caja">
        <div class="caja-cab"><h2>Reglas que no se rompen</h2></div>
        <div class="caja-cuerpo reglas">
            <p><x-ic n="escudo" c="sm" />Nada se publica sin tu aprobación: el agente trabaja siempre en borrador.</p>
            <p><x-ic n="capas" c="sm" />Una carpeta y un servidor MCP por proyecto; el agente solo atiende ese proyecto.</p>
            <p><x-ic n="lista" c="sm" />Cada proyecto tiene un nombre único: “Cota” y “cota” son el mismo. No se crean variantes (“Cota v2”, “cotav1”, “Cota copia”): comparten MCP y credencial parecidos y el agente puede trabajar en el equivocado. Si el Figma cambió, se relee y queda como Design\v2. Forzar una variante pide confirmación expresa.</p>
            <p><x-ic n="llave" c="sm" />Las contraseñas nunca van al panel, a archivos del proyecto ni al chat: viven en la configuración del agente o en variables de Windows.</p>
            <p><x-ic n="figma" c="sm" />El Figma se lee en modo económico y se guarda en el panel; al construir se usa el ID guardado de cada sección.</p>
            <p><x-ic n="alerta" c="sm" />Si algo falla, primero <code>alma.ps1 diagnostico</code>.</p>
        </div>
    </section>
@endsection
