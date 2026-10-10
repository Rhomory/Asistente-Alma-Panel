@extends('layouts.app')
@section('titulo', 'Nuevo proyecto')
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <b aria-current="page">Nuevo proyecto</b>@endsection

@section('contenido')
    <div class="cabeza">
        <div>
            <h1>Nuevo proyecto</h1>
            <p>Registra el proyecto y su conexión una sola vez. Las páginas y los tokens los carga el asistente al leer el Figma; las secciones las escribe la consola.</p>
        </div>
    </div>

    <form method="post" action="{{ route('proyecto.guardar') }}" class="rejilla">@csrf
        <section class="caja">
            <div class="caja-cab"><span class="icono-suave chico"><x-ic n="capas" c="sm" /></span><h2>Datos del proyecto</h2></div>
            <div class="caja-cuerpo" style="display:flex;flex-direction:column;gap:14px">
                <div class="campo"><label for="nombre">Nombre del proyecto</label>
                    <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="Ej.: Sitio Clínica del Sur" required maxlength="120"
                        oninput="const n=document.getElementById('nombre_mcp'); if(!n.dataset.tocado) n.value = this.value ? 'elementor-' + this.value.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'') : ''">
                    <small>Debe ser único. No uses versiones como “v2” o “copia”: los cambios de diseño se guardan como Design\v2 dentro del mismo proyecto.</small>
                    @error('nombre')<span class="error">{{ $message }}</span>@enderror</div>
                @error('variante')
                    <div class="aviso variante" role="alert"><x-ic n="alerta" />
                        <div>
                            <b>Este nombre parece una variante de otro proyecto</b>
                            <p>{{ $message }}</p>
                            <label class="forzar"><input type="checkbox" name="forzar_variante" value="1"> Es un proyecto distinto de verdad: crearlo igual</label>
                        </div>
                    </div>
                @enderror
                <div class="campo"><label for="cliente">Cliente</label>
                    <input id="cliente" name="cliente" type="text" value="{{ old('cliente') }}" placeholder="Razón social · ciudad, sin datos personales" maxlength="160">
                    @error('cliente')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="campo"><label for="archivo_figma">Archivo de Figma</label>
                    <input id="archivo_figma" name="archivo_figma" type="text" value="{{ old('archivo_figma') }}" placeholder="https://www.figma.com/design/…" maxlength="255">
                    <small>Enlace del archivo o su nombre. Nunca pegues tokens de acceso.</small>
                    @error('archivo_figma')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="campo"><label for="sitio_wp">Sitio WordPress (staging)</label>
                    <input id="sitio_wp" name="sitio_wp" type="url" value="{{ old('sitio_wp') }}" placeholder="https://proyecto-staging.tudominio.pe" maxlength="200">
                    <small>Si lo dejas vacío y pegas el prompt de Elementor, se toma de ahí.</small>
                    @error('sitio_wp')<span class="error">{{ $message }}</span>@enderror</div>
                <div class="acciones">
                    <button class="btn p" type="submit">Crear proyecto</button>
                    <a class="btn fant" href="{{ route('dashboard') }}">Cancelar</a>
                </div>
            </div>
        </section>

        <section class="caja">
            <div class="caja-cab"><span class="icono-suave ora chico"><x-ic n="enchufe" c="sm" /></span><h2>Conexión MCP de Elementor</h2><span class="nota derecha">Opcional, puedes hacerlo después</span></div>
            <div class="caja-cuerpo" style="display:flex;flex-direction:column;gap:14px">
                <ol class="pasos">
                    <li><span>En el WordPress del cliente: <b>Elementor › Elementor MCP</b> › activar › tu agente (ej. <b>Claude Code</b>) › <b>Generate Prompt</b>.</span></li>
                    <li><span>Pégalo solo aquí: el panel arma la configuración de Claude Code, Codex, Cursor y OpenCode en la carpeta del proyecto y guarda la credencial en Windows.</span></li>
                </ol>
                <div class="campo"><label for="prompt_mcp">Prompt de conexión de Elementor</label>
                    <textarea id="prompt_mcp" name="prompt_mcp" rows="7" maxlength="8000" placeholder="Pega aquí el texto que generó Elementor. La contraseña se oculta antes de guardar."></textarea>
                    <small>Se guarda una copia sin contraseña ni tokens; la completa solo vive en la consola.</small></div>
                <div class="form-fila">
                    <div class="campo"><label for="nombre_mcp">Nombre del servidor MCP</label>
                        <input id="nombre_mcp" name="nombre_mcp" type="text" value="{{ old('nombre_mcp') }}" maxlength="80" placeholder="elementor-mi-proyecto" oninput="this.dataset.tocado=1">
                        @error('nombre_mcp')<span class="error">{{ $message }}</span>@enderror</div>
                    <div class="campo"><label for="usuario_wp">Usuario de WordPress</label>
                        <input id="usuario_wp" name="usuario_wp" type="text" value="{{ old('usuario_wp') }}" maxlength="80" placeholder="Opcional"></div>
                    <div class="campo"><label for="app_password">Contraseña de aplicación</label>
                        <input id="app_password" name="app_password" type="password" autocomplete="new-password" maxlength="80" placeholder="Opcional si pegaste el prompt">
                        <small>Va directo a Windows como variable de usuario; el panel no la guarda.</small></div>
                </div>
                @error('sitio_url')<span class="error">{{ $message }}</span>@enderror
            </div>
        </section>
    </form>
@endsection
