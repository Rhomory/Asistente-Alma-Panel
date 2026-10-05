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

    <section class="caja avance">
        <span><b>{{ $resumen['aprobadas'] }} de {{ $resumen['total'] }}</b> aprobadas</span>
        <div class="barra-prog">
            <i class="a" style="width:{{ 100 * ($porEstado['aprobada'] ?? 0) / $total }}%"></i>
            <i class="c" style="width:{{ 100 * ($porEstado['construida'] ?? 0) / $total }}%"></i>
            <i class="r" style="width:{{ 100 * ($porEstado['construyendo'] ?? 0) / $total }}%"></i>
        </div>
        <span><b>{{ $resumen['real'] }}</b> min trabajados @if ($resumen['estimado']) · plan {{ $resumen['estimado'] }} min @endif</span>
        <span><b>{{ $trabajo ? round(100 * $pagina->secciones->sum('min_asistente') / $trabajo) : 0 }} %</b> hecho por el asistente</span>
    </section>

    <div class="rejilla flujo">
        <section class="caja">
            <div class="caja-cab"><h2>Secciones</h2><span class="cifra">{{ $resumen['total'] }}</span></div>
            @forelse ($pagina->secciones as $i => $s)
                @php
                    $paso = ['aprobada' => 'a', 'construida' => 'c', 'construyendo' => 'r'][$s->estado] ?? '';
                    $hecha = $s->estado !== 'planificada';
                @endphp
                <div class="sec {{ $hecha ? '' : 'plan' }}">
                    <span class="paso {{ $paso }}">@if ($s->estado === 'aprobada')<x-ic n="check" c="sm" />@else{{ $i + 1 }}@endif</span>
                    <div>
                        <div class="nom"><b>{{ $s->nombre }}</b>
                            @if ($s->estado === 'construyendo')<span class="tag obra">En corrección</span>@else @include('panel.partes.estado', ['estado' => $s->estado]) @endif
                        </div>
                        <div class="det">
                            {{ $s->widget_plan ?? 'Widget sin definir' }}@if ($s->figma_id) <span title="ID en Figma">· Figma {{ $s->figma_id }}</span>@endif ·
                            @if ($hecha)
                                <em>{{ $s->minutos }} min</em>@if ($s->min_estimado) de {{ $s->min_estimado }} estimados @endif
                                · asistente {{ $s->min_asistente }}@if ($s->min_dev) · dev {{ $s->min_dev }}@endif
                                @if ($s->correcciones) · {{ $s->correcciones }} {{ $s->correcciones === 1 ? 'corrección' : 'correcciones' }}@endif
                            @else
                                {{ $s->min_estimado ? $s->min_estimado . ' min estimados' : 'sin estimado' }}
                            @endif
                        </div>
                        @if (isset($pendientes[$s->id]))
                            <div class="cola"><i class="pulso"></i>En cola para el asistente <span>· “{{ $pendientes[$s->id]->last()->detalle }}”</span></div>
                        @endif
                    </div>
                    <div class="btns">
                        @if ($s->estado === 'construida')
                            <form method="post" action="{{ route('seccion.aprobar', $s) }}" class="inline">@csrf
                                <button class="btn chico ok" type="submit"><x-ic n="check" c="sm" />Aprobar</button>
                            </form>
                        @endif
                        @if (in_array($s->estado, ['construida', 'aprobada']))
                            <button class="btn chico {{ $s->estado === 'aprobada' ? 'fant' : '' }}" type="button" onclick="const f=document.getElementById('corr-{{ $s->id }}'); f.hidden=!f.hidden; if(!f.hidden) f.querySelector('input').focus()">Pedir corrección</button>
                        @endif
                        @if ($s->estado === 'planificada')
                            <form method="post" action="{{ route('seccion.plan.eliminar', $s) }}" class="inline" onsubmit="return confirm('¿Quitar esta sección del plan?')">@csrf @method('DELETE')
                                <button class="btn chico fant" type="submit">Quitar</button>
                            </form>
                        @endif
                    </div>
                    @if (in_array($s->estado, ['construida', 'aprobada']))
                        <form method="post" action="{{ route('seccion.correccion', $s) }}" class="corr-form" id="corr-{{ $s->id }}" hidden>@csrf
                            <input type="text" name="detalle" maxlength="500" placeholder="Ej.: el título debe usar el color primario" required aria-label="Qué debe corregirse en {{ $s->nombre }}">
                            <button class="btn p" type="submit">Enviar a la cola</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="caja-cuerpo">
                    @if ($pagina->secciones_total > 0)
                        <div class="aviso-suave"><x-ic n="figma" />Figma detectó {{ $pagina->secciones_total }} secciones en esta página, pero aún no tienen nombre en el panel. Vuelve a leer el Figma con la skill actualizada (carga cada sección con su ID) o aplica el plan estándar.</div>
                    @else
                        <div class="aviso-suave"><x-ic n="lista" />Esta página aún no tiene plan. Aplica el plan estándar de la guía o agrega secciones a mano.</div>
                    @endif
                </div>
            @endforelse
            @error('detalle')<p class="error caja-cuerpo">{{ $message }}</p>@enderror
        </section>

        <div class="col">
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
