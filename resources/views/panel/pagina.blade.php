@extends('layouts.app')
@section('titulo', $pagina->proyecto->nombre . ' · ' . $pagina->nombre)

@section('contenido')
    @if ($enVivo)
        <meta http-equiv="refresh" content="15">
    @endif

    <p class="miga">
        <a href="{{ route('proyecto', $pagina->proyecto) }}">← {{ $pagina->proyecto->nombre }}</a>
        <span class="cliente-lateral">{{ $pagina->proyecto->cliente ?: $pagina->proyecto->nombre }}</span>
    </p>
    <div class="fila-titulo">
        <h1>{{ $pagina->nombre }} <span class="tag estado-{{ $pagina->estado }}">{{ str_replace('_', ' ', $pagina->estado) }}</span></h1>
        <div class="mini-kpis">
            <span><b>{{ $resumen['aprobadas'] }}/{{ $resumen['total'] }}</b> aprobadas</span>
            <span><b>{{ $resumen['estimado'] }}</b> min estimados</span>
            <span><b>{{ $resumen['real'] }}</b> min reales</span>
        </div>
    </div>
    <p class="sub">Plan y construcción supervisada. La consola construye cada sección (<code>registro:add</code>) y aquí se aprueba o se pide corrección.@if ($enVivo) Esta vista se actualiza sola cada 15 s.@endif</p>

    <div class="card">
        <table>
            <thead><tr><th style="width:26px"></th><th>Sección</th><th>Widget (guía técnica)</th><th class="n">Est.</th><th class="n">Real</th><th class="n">Asist.</th><th class="n">Dev</th><th class="n">Correcc.</th><th style="width:290px">Acciones</th></tr></thead>
            <tbody>
            @forelse ($pagina->secciones as $s)
                <tr>
                    <td class="c-estado">
                        @if ($s->estado === 'aprobada') <span class="dot ok" title="aprobada">✓</span>
                        @elseif ($s->estado === 'construida') <span class="dot rev" title="construida, por revisar">●</span>
                        @elseif ($s->estado === 'construyendo') <span class="dot run" title="en construcción/corrección">◐</span>
                        @else <span class="dot plan" title="planificada">○</span>
                        @endif
                    </td>
                    <td>
                        {{ $s->nombre }}
                        @if (isset($pendientes[$s->id]))
                            <div class="solicitud">En cola: "{{ $pendientes[$s->id]->last()->detalle }}"</div>
                        @endif
                    </td>
                    <td class="mut">{{ $s->widget_plan ?? '—' }}</td>
                    <td class="n mut">{{ $s->min_estimado ?? '—' }}</td>
                    <td class="n">{{ $s->estado === 'planificada' ? '—' : $s->minutos }}</td>
                    <td class="n">{{ $s->estado === 'planificada' ? '—' : $s->min_asistente }}</td>
                    <td class="n">{{ $s->estado === 'planificada' ? '—' : $s->min_dev }}</td>
                    <td class="n">{{ $s->correcciones }}</td>
                    <td>
                        @if ($s->estado === 'construida')
                            <form method="post" action="{{ route('seccion.aprobar', $s) }}" class="inline">@csrf
                                <button class="btn p chico" type="submit">Aprobar</button>
                            </form>
                        @endif
                        @if (in_array($s->estado, ['construida', 'aprobada']))
                            <details class="corr">
                                <summary class="btn s chico">Pedir corrección</summary>
                                <form method="post" action="{{ route('seccion.correccion', $s) }}">@csrf
                                    <input type="text" name="detalle" maxlength="500" placeholder='Ej.: "el título debe usar el color primario"' required>
                                    <button class="btn p chico" type="submit">Enviar a la cola</button>
                                </form>
                            </details>
                        @endif
                        @if ($s->estado === 'planificada')
                            <form method="post" action="{{ route('seccion.plan.eliminar', $s) }}" class="inline" onsubmit="return confirm('¿Quitar esta sección del plan?')">@csrf @method('DELETE')
                                <button class="btn s chico" type="submit">Quitar del plan</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="vacio">Esta página aún no tiene plan. Genera el plan estándar de la guía técnica o agrega secciones abajo.</td></tr>
            @endforelse
            </tbody>
        </table>
        @error('detalle')<p class="error">{{ $message }}</p>@enderror
    </div>

    <div class="dos-col">
        <div class="card form-card">
            <h3>Plan de construcción</h3>
            <form method="post" action="{{ route('pagina.plan.estandar', $pagina) }}" class="inline">@csrf
                <button class="btn s" type="submit">Aplicar plan estándar de la guía técnica</button>
            </form>
            <form method="post" action="{{ route('pagina.plan.agregar', $pagina) }}" class="form-inline" style="margin-top:14px">@csrf
                <div class="campo"><label for="pnombre">Sección *</label><input id="pnombre" name="nombre" required maxlength="120" placeholder="Ej.: Preguntas frecuentes"></div>
                <div class="campo"><label for="pwidget">Widget</label><input id="pwidget" name="widget_plan" maxlength="160" placeholder="Ej.: Accordion"></div>
                <div class="campo corto"><label for="pest">Est. (min)</label><input id="pest" name="min_estimado" type="number" min="1" max="480" placeholder="8"></div>
                <div class="acciones"><button class="btn p" type="submit">Agregar al plan</button></div>
            </form>
        </div>
        <div class="card">
            <h3>Solicitud de QA</h3>
            <p class="nota-pie" style="margin:0 0 6px">Mensaje estándar para el canal, armado con el sitio y el Figma del proyecto. Agrega el link de Trello de esta página y cópialo.</p>

            <form method="post" action="{{ route('pagina.trello', $pagina) }}" class="campo-linea">@csrf
                <div class="campo">
                    <label for="trello_url">Link de Trello</label>
                    <input id="trello_url" name="trello_url" type="url" value="{{ old('trello_url', $pagina->trello_url) }}" placeholder="https://trello.com/c/…" maxlength="255">
                </div>
                <button class="btn s" type="submit">Guardar</button>
            </form>
            @error('trello_url')<span class="error">{{ $message }}</span>@enderror

            <textarea id="qa-mensaje" class="qa-mensaje" readonly>{{ \App\Http\Controllers\FlujoController::mensajeQA($pagina) }}</textarea>

            @if ($resumen['total'] === 0 || $resumen['aprobadas'] !== $resumen['total'])
                <div class="qa-aviso">Aún hay secciones sin aprobar ({{ $resumen['aprobadas'] }}/{{ $resumen['total'] }}). Puedes copiar el mensaje, pero la página no cambiará a "QA solicitado" hasta aprobarlas todas.</div>
            @endif

            <div style="margin-top:10px">
                <button class="btn p" type="button" id="qa-copiar" data-url="{{ route('pagina.qa', $pagina) }}">Copiar mensaje</button>
                <span class="qa-ok" id="qa-ok" hidden>✓ Copiado y registrado en la actividad</span>
            </div>
            <p class="nota-pie">Las correcciones pedidas arriba quedan en la cola de trabajo de la consola (<code>registro:cola</code>); al reconstruirse la sección, se marcan atendidas automáticamente.</p>
        </div>
    </div>

    <script>
    document.getElementById('qa-copiar').addEventListener('click', async function () {
        const area = document.getElementById('qa-mensaje');
        const texto = area.value;
        try {
            await navigator.clipboard.writeText(texto);
        } catch (e) {
            area.select(); document.execCommand('copy');   // respaldo si el portapapeles no está disponible
        }
        const r = await fetch(this.dataset.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        });
        document.getElementById('qa-ok').hidden = false;
        if (r.ok) {
            const datos = await r.json();
            if (datos.completa && '{{ $pagina->estado }}' !== 'en_qc') { setTimeout(() => location.reload(), 1200); }
        }
    });
    </script>
@endsection
