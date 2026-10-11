{{-- Diálogo de eliminación. En blanco: confirmar y listo. Con registros: escribir el nombre exacto. --}}
@php
    $perdidas = array_filter([
        'páginas'     => $conteo['paginas'],
        'secciones registradas' => $conteo['secciones'],
        'tokens de diseño' => $conteo['tokens'],
        'conexiones MCP' => $conteo['conexiones'],
        'eventos de actividad' => $conteo['eventos'],
    ]);
@endphp
<dialog class="dialogo" id="eliminar-proyecto" aria-labelledby="eliminar-titulo">
    <form method="post" action="{{ route('proyecto.eliminar', $proyecto) }}">
        @csrf @method('DELETE')
        <div class="dialogo-cab">
            <span class="icono-suave err"><x-ic n="{{ $conteo['en_blanco'] ? 'basura' : 'alerta' }}" c="lg" /></span>
            <div>
                <h2 id="eliminar-titulo">¿Eliminar {{ $proyecto->nombre }}?</h2>
                @if ($conteo['en_blanco'])
                    <p>Este proyecto está en blanco: no tiene páginas ni tokens. No se pierde ningún registro.</p>
                @else
                    <p>Este proyecto tiene registros. Al eliminarlo se borra <b>todo lo relacionado</b> y no se puede deshacer.</p>
                @endif
            </div>
        </div>

        @if (! $conteo['en_blanco'])
            <ul class="perdidas" aria-label="Se eliminará">
                @foreach ($perdidas as $que => $n)
                    <li><x-ic n="x" c="sm" /><b>{{ $n }}</b> {{ $que }}</li>
                @endforeach
            </ul>
            <div class="campo">
                <label for="confirmacion">Para confirmar, escribe el nombre del proyecto: <b class="mono">{{ $proyecto->nombre }}</b></label>
                <input id="confirmacion" name="confirmacion" type="text" autocomplete="off" spellcheck="false" data-esperado="{{ $proyecto->nombre }}"
                    oninput="document.getElementById('eliminar-ok').disabled = this.value.trim() !== this.dataset.esperado">
                @error('confirmacion')<span class="error">{{ $message }}</span>@enderror
            </div>
        @elseif ($conteo['conexiones'])
            <p class="nota">También se elimina su conexión MCP del panel.</p>
        @endif

        @php
            $carpetaRuta = \App\Support\CarpetaProyecto::ruta($proyecto);
            $hayCarpeta = $proyecto->carpeta && $carpetaRuta && is_dir($carpetaRuta);
        @endphp
        <fieldset class="opciones-eliminar">
            <legend>Al eliminar</legend>
            <label class="opcion"><input type="checkbox" name="respaldo" value="1" @checked(! $conteo['en_blanco'])>
                <span><b>Guardar un respaldo</b><small>Un archivo de pocos KB con páginas, secciones, tiempos, tokens y versiones del diseño (sin contraseñas ni capturas). Se puede restaurar después.</small></span></label>
            @if ($hayCarpeta)
                <label class="opcion"><input type="checkbox" name="borrar_carpeta" value="1" checked>
                    <span><b>Borrar también la carpeta</b><small>{{ \App\Support\CarpetaProyecto::rutaWindows($proyecto) }} (AGENTS.md, configuración y Design\vN con sus capturas).</small></span></label>
            @endif
            @if ($proyecto->conexion)
                <label class="opcion"><input type="checkbox" name="borrar_credencial" value="1" checked>
                    <span><b>Quitar su credencial de Windows</b><small>La variable {{ \App\Support\PromptElementor::variableAuth($proyecto->nombre) }} deja de existir.</small></span></label>
            @endif
        </fieldset>

        <div class="dialogo-pie">
            <button class="btn fant" type="button" onclick="this.closest('dialog').close()">Cancelar</button>
            <button class="btn peligro-lleno" type="submit" id="eliminar-ok" @disabled(! $conteo['en_blanco'])>
                <x-ic n="basura" />{{ $conteo['en_blanco'] ? 'Eliminar proyecto' : 'Eliminar todo' }}
            </button>
        </div>
    </form>
</dialog>
@error('confirmacion')
    <script>document.getElementById('eliminar-proyecto').showModal();</script>
@enderror
