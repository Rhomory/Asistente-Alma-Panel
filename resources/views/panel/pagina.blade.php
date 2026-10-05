@extends('layouts.app')
@section('titulo', $pagina->proyecto->nombre . ' · ' . $pagina->nombre)
@section('migas')<a href="{{ route('dashboard') }}">Inicio</a> / <a href="{{ route('proyecto', $pagina->proyecto) }}">{{ $pagina->proyecto->nombre }}</a> / <b>{{ $pagina->nombre }}</b>@endsection

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
        <div>
            <a class="volver" href="{{ route('proyecto', $pagina->proyecto) }}"><x-ic n="atras" c="sm" />{{ $pagina->proyecto->nombre }}</a>
            <h1>{{ $pagina->nombre }} @include('panel.partes.estado', ['estado' => $pagina->estado])</h1>
            <p>El asistente construye cada sección desde la consola; aquí revisas, apruebas o le pides una corrección.</p>
        </div>
        <div class="acciones">
            <a class="btn" href="{{ route('proyecto.guia', [$pagina->proyecto, 'pagina' => $pagina->id]) }}"><x-ic n="libro" />Guía y prompt</a>
            @if ($pagina->proyecto->sitio_wp)
                <a class="btn" href="{{ $pagina->proyecto->sitio_wp }}" target="_blank" rel="noopener"><x-ic n="externo" />Ver en el sitio</a>
            @endif
        </div>
    </div>

    <section class="caja avance">
        <span><b>{{ $resumen['aprobadas'] }} de {{ $resumen['total'] }}</b> aprobadas</span>
        <div class="barra-prog">
            <i class="a" style="width:{{ 100 * ($porEstado['aprobada'] ?? 0) / $total }}%"></i>
            <i class="c" style="width:{{ 100 * ($porEstado['construida'] ?? 0) / $total }}%"></i>
            <i class="r" style="width:{{ 100 * ($porEstado['construyendo'] ?? 0) / $total }}%"></i>
        </div>
        <span><b>{{ $resumen['real'] }}</b> de {{ $resumen['estimado'] }} min estimados</span>
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
                            {{ $s->widget_plan ?? 'Widget sin definir' }} ·
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
                    <div class="aviso-suave"><x-ic n="lista" />Esta página aún no tiene plan. Aplica el plan estándar de la guía o agrega secciones a mano.</div>
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
                            <input id="trello_url" name="trello_url" type="url" value="{{ old('trello_url', $pagina->trello_url) }}" placeholder="https://trello.com/c/…" maxlength="255"></div>
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

                    @if ($resumen['total'] === 0 || $resumen['aprobadas'] !== $resumen['total'])
                        <div class="aviso"><x-ic n="alerta" />
                            <span>Faltan {{ $resumen['total'] - $resumen['aprobadas'] }} {{ $resumen['total'] - $resumen['aprobadas'] === 1 ? 'sección' : 'secciones' }} por aprobar. Puedes copiar el mensaje ahora; la página pasa a “QA solicitado” cuando estén todas aprobadas.</span></div>
                    @endif

                    <div class="acciones">
                        <button class="btn p" type="button" id="qa-copiar" data-url="{{ route('pagina.qa', $pagina) }}"><x-ic n="copiar" />Copiar mensaje</button>
                        <span class="listo" id="qa-ok" hidden><x-ic n="check" c="sm" />Copiado y anotado en la actividad</span>
                    </div>
                    <p class="nota">El sitio y el Figma salen de los datos del proyecto; solo agregas el Trello.</p>
                </div>
            </section>

            <section class="caja">
                <div class="caja-cab"><h2>Plan de construcción</h2></div>
                <div class="caja-cuerpo">
                    <form method="post" action="{{ route('pagina.plan.estandar', $pagina) }}">@csrf
                        <button class="btn" type="submit"><x-ic n="lista" />Aplicar plan estándar de la guía</button>
                    </form>
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
        const r = await fetch(this.dataset.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        });
        document.getElementById('qa-ok').hidden = false;
        // El registro en la actividad dispara la recarga en vivo; si la página quedó completa, cambia a "QA solicitado".
    });
    </script>
@endsection
