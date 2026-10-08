@extends('layouts.app')
@section('titulo', $pagina->proyecto->nombre . ' · ' . $pagina->nombre)
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <a href="{{ route('proyecto', $pagina->proyecto) }}">{{ $pagina->proyecto->nombre }}</a> / <b aria-current="page">Página: {{ $pagina->nombre }}</b>@endsection

@section('contenido')
    @php
        $total = max($resumen['total'], 1);
        $porEstado = $pagina->secciones->countBy('estado');
        $trabajo = $pagina->secciones->sum('min_asistente') + $pagina->secciones->sum('min_dev');
        $mensaje = \App\Http\Controllers\FlujoController::mensajeQA($pagina);
        $mensajeHtml = preg_replace(
            ['#(https?://[^\s<]+)#', '/^@canal/', '/(\[falta[^\]]*\])/'],
            ['<span class="url">$1</span>', '<span class="canal">@canal</span>', '<span class="falta">$1</span>'],
            e($mensaje)
        );
    @endphp

    <div class="cabeza">
        <div class="titulo-con-padre">
            <a class="btn-subir" href="{{ route('proyecto', $pagina->proyecto) }}" aria-label="Volver a {{ $pagina->proyecto->nombre }}" title="Volver a {{ $pagina->proyecto->nombre }} (Alt+↑)" aria-keyshortcuts="Alt+ArrowUp"><x-ic n="atras" /></a>
            <div>
                <p class="contexto">Página de <a href="{{ route('proyecto', $pagina->proyecto) }}">{{ $pagina->proyecto->nombre }}</a></p>
                <h1>{{ $pagina->nombre }} @include('panel.partes.estado', ['estado' => $pagina->estado])</h1>
            </div>
            <p>El asistente construye cada sección desde la consola; aquí revisas, apruebas o le pides una corrección.</p>
            @php $urlPagina = $pagina->urlSitio(); @endphp
            <div class="enlaces">
                @if ($urlPagina)
                    <a class="enlace" href="{{ $urlPagina }}" target="_blank" rel="noopener"><x-ic n="globo" c="sm" /><b>{{ preg_replace('#^https?://#', '', $urlPagina) }}</b><x-ic n="externo" c="xs" /></a>
                @endif
                <details class="editar-url">
                    <summary class="btn chico fant"><x-ic n="lapiz" c="sm" />{{ $urlPagina ? 'Cambiar URL' : 'Agregar URL' }}</summary>
                    <form method="post" action="{{ route('pagina.url', $pagina) }}" class="form-linea">@csrf
                        <div class="campo"><label for="url">Ruta en el sitio</label>
                            <input id="url" name="url" type="text" value="{{ old('url', $pagina->url) }}" maxlength="255" placeholder="/{{ \Illuminate\Support\Str::slug($pagina->nombre) }}"></div>
                        <button class="btn" type="submit">Guardar</button>
                    </form>
                    <small class="nota">Vacío = se arma desde el nombre: “Inicio” es la raíz del sitio y el resto va como /nombre.</small>
                </details>
                @if ($pagina->figma_id)<span class="enlace" title="ID del marco en Figma, guardado como memoria del diseño"><x-ic n="figma" c="sm" />{{ $pagina->figma_id }}</span>@endif
            </div>
            @error('url')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('proyecto.guia', [$pagina->proyecto, 'pagina' => $pagina->id]) }}"><x-ic n="libro" />Guía y prompt</a>
        </div>
    </div>

    <div class="tablero-cab">
        @if ($hermanas->count() > 1)
            <nav class="pestanas" aria-label="Páginas del proyecto">
                @foreach ($hermanas as $h)
                    <a href="{{ route('pagina', $h) }}" class="{{ $h->id === $pagina->id ? 'on' : '' }}" @if ($h->id === $pagina->id) aria-current="page" @endif>{{ $h->nombre }}</a>
                @endforeach
            </nav>
        @endif
        <p class="tablero-resumen">
            <span><b>{{ $resumen['aprobadas'] }} de {{ $resumen['total'] }}</b> aprobadas</span>
            <span><b>{{ $resumen['real'] }}</b> min @if ($resumen['estimado'])de {{ $resumen['estimado'] }} planificados @endif</span>
            <span><b>{{ $trabajo ? round(100 * $pagina->secciones->sum('min_asistente') / $trabajo) : 0 }} %</b> asistente</span>
        </p>
    </div>

    {{-- Tablero de obra: una columna por estado, cada sección es una tarjeta. --}}
    @php
        $columnas = [
            'planificada'  => ['Planificada', 'plan'],
            'construyendo' => ['En construcción', 'obra'],
            'construida'   => ['Por revisar', 'rev'],
            'aprobada'     => ['Aprobada', 'ok'],
        ];
        $porColumna = $pagina->secciones->groupBy('estado');
    @endphp
    <section class="tablero" aria-label="Secciones por estado">
        @foreach ($columnas as $estado => [$titulo, $tono])
            @php $lista = $porColumna[$estado] ?? collect(); @endphp
            <div class="columna c-{{ $tono }}">
                <div class="columna-cab"><i class="punto"></i><h2>{{ $titulo }}</h2><span class="conteo {{ $lista->isNotEmpty() ? 'hay' : '' }}">{{ $lista->count() }}</span></div>
                <div class="columna-lista">
                    @foreach ($lista as $s)
                        <article class="tarjeta {{ $estado === 'planificada' ? 'plan' : '' }} {{ $estado === 'aprobada' ? 'lista' : '' }}">
                            @if ($estado === 'aprobada')
                                <span class="tilde"><x-ic n="check" c="sm" /></span>
                            @endif
                            <div class="tarjeta-txt">
                                <h3>{{ $s->nombre }}</h3>
                                @if ($estado === 'construyendo' && $s->correcciones)<span class="tag rev">En corrección</span>@endif
                                <p class="det">
                                    @if ($estado !== 'aprobada'){{ $s->widget_plan ?? 'Widget sin definir' }}@if ($s->figma_id) · Figma {{ $s->figma_id }}@endif<br>@endif
                                    @if ($estado === 'planificada')
                                        {{ $s->min_estimado ? $s->min_estimado . ' min estimados' : 'Sin construir' }}
                                    @else
                                        {{ $s->minutos }} min · asistente {{ $s->min_asistente }}@if ($s->min_dev) · dev {{ $s->min_dev }}@endif
                                        @if ($s->correcciones) · {{ $s->correcciones }} {{ $s->correcciones === 1 ? 'corrección' : 'correcciones' }}@endif
                                    @endif
                                </p>
                                @if (isset($pendientes[$s->id]))
                                    <div class="cola"><i class="pulso"></i><span>En cola para el asistente · “{{ $pendientes[$s->id]->last()->detalle }}”</span></div>
                                @endif
                            </div>
                            @if ($estado === 'construida')
                                <div class="btns">
                                    <form method="post" action="{{ route('seccion.aprobar', $s) }}" class="inline">@csrf
                                        <button class="btn chico p" type="submit"><x-ic n="check" c="sm" />Aprobar</button>
                                    </form>
                                    <button class="btn chico" type="button" onclick="const f=document.getElementById('corr-{{ $s->id }}'); f.hidden=!f.hidden; if(!f.hidden) f.querySelector('input').focus()">Pedir corrección</button>
                                </div>
                            @elseif ($estado === 'aprobada')
                                <button class="btn chico fant icono" type="button" title="Pedir corrección" aria-label="Pedir corrección en {{ $s->nombre }}" onclick="const f=document.getElementById('corr-{{ $s->id }}'); f.hidden=!f.hidden; if(!f.hidden) f.querySelector('input').focus()"><x-ic n="lapiz" c="sm" /></button>
                            @elseif ($estado === 'planificada')
                                <form method="post" action="{{ route('seccion.plan.eliminar', $s) }}" class="inline quitar" onsubmit="return confirm('¿Quitar esta sección del plan?')">@csrf @method('DELETE')
                                    <button class="btn chico fant" type="submit">Quitar</button>
                                </form>
                            @endif
                            @if (in_array($estado, ['construida', 'aprobada']))
                                <form method="post" action="{{ route('seccion.correccion', $s) }}" class="corr-form" id="corr-{{ $s->id }}" hidden>@csrf
                                    <input type="text" name="detalle" maxlength="500" placeholder="Qué debe corregirse" required aria-label="Qué debe corregirse en {{ $s->nombre }}">
                                    <button class="btn chico p" type="submit">Enviar a la cola</button>
                                </form>
                            @endif
                        </article>
                    @endforeach

                    @if ($lista->isEmpty())
                        @if ($estado === 'planificada' && $resumen['total'] === 0)
                            <div class="columna-vacia">
                                {{ $pagina->secciones_total > 0
                                    ? "Figma detectó {$pagina->secciones_total} secciones, pero aún no tienen nombre. Vuelve a leer el Figma o aplica el plan estándar."
                                    : 'Sin plan todavía. Aplica el plan estándar o agrega secciones abajo.' }}
                            </div>
                        @else
                            <div class="columna-vacia">{{ ['planificada' => 'Nada pendiente de construir.', 'construyendo' => 'El asistente no está trabajando aquí.', 'construida' => 'Nada por revisar.', 'aprobada' => 'Aún no hay secciones aprobadas.'][$estado] }}</div>
                        @endif
                    @endif
                </div>

                @if ($estado === 'aprobada')
                    <a class="tarjeta qa-mini" href="#qa">
                        <b>Solicitar QA</b>
                        <small>{{ $resumen['total'] && $resumen['aprobadas'] === $resumen['total'] ? 'Lista: copia el mensaje para el canal' : "Se habilita con {$resumen['total']} de {$resumen['total']} aprobadas" }}</small>
                        <span class="barra-prog"><i class="a" style="width:{{ 100 * $resumen['aprobadas'] / $total }}%"></i></span>
                        <em>{{ $resumen['aprobadas'] }} / {{ $resumen['total'] }}</em>
                    </a>
                @endif
            </div>
        @endforeach
    </section>
    @error('detalle')<p class="error">{{ $message }}</p>@enderror

    <div class="rejilla flujo">
            <section class="caja" id="qa">
                <div class="caja-cab"><span class="icono-suave ora chico"><x-ic n="mensaje" c="sm" /></span><h2>Solicitud de QA</h2></div>
                <div class="caja-cuerpo">
                    <form method="post" action="{{ route('pagina.trello', $pagina) }}" class="form-linea">@csrf
                        <div class="campo"><label for="trello_url">Tarjeta de Trello de esta página</label>
                            <input id="trello_url" name="trello_url" type="url" value="{{ old('trello_url', $pagina->trello_url) }}" placeholder="https://trello.com/c/…" maxlength="255" class="{{ $pagina->trello_url ? '' : 'falta' }}" @if (! $pagina->trello_url) aria-describedby="trello-falta" @endif>
                            @if (! $pagina->trello_url)<small id="trello-falta" class="falta-txt">Sin esto, el mensaje sale con “[falta el link de Trello]”.</small>@endif</div>
                        <button class="btn" type="submit">Guardar</button>
                    </form>
                    @error('trello_url')<span class="error">{{ $message }}</span>@enderror

                    <div class="burbuja">
                        <div style="flex:1;min-width:0">
                            <small>Vista previa del mensaje para el canal</small>
                            <div class="msg">{!! $mensajeHtml !!}</div>
                        </div>
                    </div>
                    <textarea id="qa-mensaje" hidden readonly>{{ $mensaje }}</textarea>

                    @php
                        $sinAprobar = $resumen['total'] - $resumen['aprobadas'];
                        $faltas = array_filter([
                            ! $pagina->trello_url ? 'el link de Trello de esta página' : null,
                            ! $pagina->proyecto->sitio_wp ? 'la URL del sitio (en el proyecto)' : null,
                            ! $pagina->proyecto->archivo_figma ? 'el archivo de Figma (en el proyecto)' : null,
                            $resumen['total'] === 0 ? 'secciones en el plan' : null,
                            $sinAprobar > 0 ? "aprobar {$sinAprobar} " . ($sinAprobar === 1 ? 'sección' : 'secciones') : null,
                        ]);
                    @endphp
                    @if ($faltas)
                        <div class="aviso"><x-ic n="alerta" />
                            <div>
                                <b>Aún no está listo para el canal.</b> Falta:
                                <ul class="faltas">@foreach ($faltas as $f)<li>{{ $f }}</li>@endforeach</ul>
                                La página pasa a “QA solicitado” solo cuando todas sus secciones están aprobadas.
                            </div>
                        </div>
                    @endif

                    <div class="acciones">
                        <button class="btn {{ $faltas ? '' : 'p' }}" type="button" id="qa-copiar" data-url="{{ route('pagina.qa', $pagina) }}"><x-ic n="copiar" />{{ $faltas ? 'Copiar de todos modos' : 'Copiar mensaje' }}</button>
                        <span class="listo" id="qa-ok" role="status" hidden><x-ic n="check" c="sm" /><span>Copiado y anotado en la actividad</span></span>
                    </div>
                    <p class="nota">El sitio y el Figma salen de los datos del proyecto; solo agregas el Trello.</p>
                </div>
            </section>

            <section class="caja">
                <div class="caja-cab"><h2>Plan de construcción</h2></div>
                <div class="caja-cuerpo">
                    @php
                        $hayConstruidas = $pagina->secciones->where('estado', '!=', 'planificada')->isNotEmpty();
                        $planificadas = $pagina->secciones->where('estado', 'planificada')->count();
                    @endphp
                    <div class="acciones">
                        <form method="post" action="{{ route('pagina.plan.estandar', $pagina) }}">@csrf
                            <button class="btn" type="submit" @disabled($hayConstruidas)><x-ic n="lista" />Aplicar plan estándar</button>
                        </form>
                        @if ($planificadas)
                            <form method="post" action="{{ route('pagina.plan.limpiar', $pagina) }}" onsubmit="return confirm('¿Quitar las {{ $planificadas }} secciones planificadas? Las construidas no se tocan.')">@csrf @method('DELETE')
                                <button class="btn fant" type="submit">Quitar las {{ $planificadas }} planificadas</button>
                            </form>
                        @endif
                    </div>
                    @if ($hayConstruidas)
                        <p class="nota">El plan estándar solo se aplica a páginas sin construir; aquí agrega solo las secciones que falten.</p>
                    @endif
                    @error('plan')<span class="error">{{ $message }}</span>@enderror
                    <form method="post" action="{{ route('pagina.plan.agregar', $pagina) }}" class="form-linea">@csrf
                        <div class="campo"><label for="pnombre">Sección</label><input id="pnombre" name="nombre" type="text" required maxlength="120" placeholder="Preguntas frecuentes"></div>
                        <div class="campo"><label for="pwidget">Widget</label><input id="pwidget" name="widget_plan" type="text" maxlength="160" placeholder="Accordion"></div>
                        <div class="campo corto"><label for="pest">Min</label><input id="pest" name="min_estimado" type="number" min="1" max="480" placeholder="8"></div>
                        <button class="btn p" type="submit">Agregar</button>
                    </form>
                    @error('nombre')<span class="error">{{ $message }}</span>@enderror
                </div>
            </section>
    </div>

    <script>
    document.getElementById('qa-copiar').addEventListener('click', async function () {
        const texto = document.getElementById('qa-mensaje').value;
        try { await navigator.clipboard.writeText(texto); }
        catch (e) { const t = document.createElement('textarea'); t.value = texto; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
        const aviso = document.getElementById('qa-ok');
        let anotado = false;
        try {
            const r = await fetch(this.dataset.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
            });
            anotado = r.ok;
        } catch (e) {}
        aviso.lastChild.textContent = anotado ? 'Copiado y anotado en la actividad' : 'Copiado, pero no se pudo anotar en la actividad';
        aviso.classList.toggle('falla', !anotado);
        aviso.hidden = false;
        // El registro en la actividad dispara la recarga en vivo; si la página quedó completa, cambia a "QA solicitado".
    });
    </script>
@endsection
