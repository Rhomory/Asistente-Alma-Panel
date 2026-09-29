@extends('layouts.app')
@section('titulo', $proyecto->nombre)

@section('contenido')
    <div class="fila-titulo">
        <h1>{{ $proyecto->nombre }}</h1>
        <span class="cliente-lateral">{{ $proyecto->cliente ?: $proyecto->nombre }}</span>
    </div>
    <p class="sub">
        {{ $proyecto->cliente }} @if($proyecto->sitio_wp) · staging: {{ $proyecto->sitio_wp }} @endif
    </p>

    <div class="card">
        <h3>Páginas del proyecto</h3>
        <p class="nota-pie" style="margin:0 0 8px">El asistente registra las páginas al leer el diseño (<code>registro:paginas</code>); con el check decides cuáles se maquetan.</p>
        <table>
            <thead><tr><th style="width:86px">Maquetar</th><th>Página</th><th>Origen</th><th>Estado</th><th class="n">Secciones</th><th class="n">Min reales</th><th class="n">vs base</th><th class="n">% asist.</th><th style="width:120px"></th></tr></thead>
            <tbody>
            @forelse ($paginas as $rp)
                @php $trabajo = $rp->min_asistente + $rp->min_dev; @endphp
                <tr class="{{ $rp->incluida ? '' : 'excluida' }}">
                    <td class="c-estado">
                        <form method="post" action="{{ route('pagina.incluir', $rp->id) }}">@csrf
                            <input type="hidden" name="incluida" value="{{ $rp->incluida ? 0 : 1 }}">
                            <input type="checkbox" class="check-maquetar" @checked($rp->incluida) onchange="this.form.submit()" title="{{ $rp->incluida ? 'Quitar del alcance' : 'Incluir en el alcance' }}">
                        </form>
                    </td>
                    <td><b>{{ $rp->nombre }}</b></td>
                    <td><span class="tag origen-{{ $rp->origen }}">{{ $rp->origen }}</span></td>
                    <td>
                        @if ($rp->incluida)
                            <span class="tag estado-{{ $rp->estado }}">{{ str_replace('_', ' ', $rp->estado) }}</span>
                        @else
                            <span class="tag origen-manual">no se maqueta</span>
                        @endif
                    </td>
                    <td class="n">{{ $rp->secciones_reg }}</td>
                    <td class="n">{{ $rp->min_total ?: '—' }}</td>
                    <td class="n">{{ $rp->min_total ? '▼ ' . round(100 - 100 * $rp->min_total / $rp->linea_base_min) . ' %' : '—' }}</td>
                    <td class="n">{{ $trabajo > 0 ? round(100 * $rp->min_asistente / $trabajo) . ' %' : '—' }}</td>
                    <td>@if ($rp->incluida)<a class="btn s chico" href="{{ route('pagina', $rp->id) }}">Abrir flujo →</a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="vacio">Aún sin páginas: el asistente las detecta al leer el diseño (<code>registro:paginas</code>) o agrégalas manualmente abajo.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="dos-col">
        <div class="card form-card">
            <details class="plegado" {{ $errors->has('nombre') ? 'open' : '' }}>
                <summary><h3 style="display:inline">Agregar página manualmente</h3> <span class="mut-chico">(respaldo: lo habitual es que las detecte el asistente)</span></summary>
                <form method="post" action="{{ route('pagina.guardar', $proyecto) }}" class="form-inline" style="margin-top:12px">
                    @csrf
                    <div class="campo">
                        <label for="nombre">Nombre de la página *</label>
                        <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="Ej.: Aviso legal" required maxlength="120">
                        @error('nombre')<span class="error">{{ $message }}</span>@enderror
                    </div>
                    <div class="campo corto">
                        <label for="secciones_total">Secciones previstas</label>
                        <input id="secciones_total" name="secciones_total" type="number" min="0" max="50" value="{{ old('secciones_total') }}" placeholder="7">
                    </div>
                    <div class="acciones"><button class="btn p" type="submit">Agregar página</button></div>
                </form>
            </details>
            <p class="nota-pie">La página nace "pendiente"; su plan se arma en "Abrir flujo" y la consola registra la construcción.</p>
        </div>

        <div class="card">
            <h3>Tokens del diseño</h3>
            @if ($proyecto->tokens->isEmpty())
                <p class="vacio">Sin tokens registrados: anota aquí los colores, tipografías y espaciados que la guía técnica convertirá en estilos globales de Elementor.</p>
            @else
                <div class="fichas-token">
                    @foreach ($proyecto->tokens as $t)
                        <div class="ficha-token {{ $t->incluido ? '' : 'apagado' }}" title="Origen: {{ $t->origen }}{{ $t->incluido ? '' : ' · desactivado' }}">
                            <form method="post" action="{{ route('token.incluir', $t) }}">@csrf
                                <input type="hidden" name="incluido" value="{{ $t->incluido ? 0 : 1 }}">
                                <input type="checkbox" class="check-maquetar chico" @checked($t->incluido) onchange="this.form.submit()" title="{{ $t->incluido ? 'No usar este token' : 'Usar este token' }}">
                            </form>
                            @if ($t->tipo === 'color')<span class="sw" style="background: {{ $t->valor }}"></span>@endif
                            <div>
                                <b>{{ $t->valor }}</b>
                                <small>{{ ucfirst($t->tipo) }}{{ $t->nota ? ' · ' . $t->nota : '' }} · {{ $t->origen }}</small>
                            </div>
                            @if ($t->tipo !== 'color')
                                <details class="editar-token">
                                    <summary title="Editar">✎</summary>
                                    <form method="post" action="{{ route('token.actualizar', $t) }}">@csrf @method('PUT')
                                        <input name="valor" value="{{ $t->valor }}" required maxlength="120">
                                        <input name="nota" value="{{ $t->nota }}" maxlength="160" placeholder="nota">
                                        <button class="btn p chico" type="submit">Guardar</button>
                                    </form>
                                </details>
                            @endif
                            <form method="post" action="{{ route('token.eliminar', $t) }}" onsubmit="return confirm('¿Eliminar este token?')">@csrf @method('DELETE')
                                <button class="quitar" type="submit" title="{{ $t->tipo === 'color' ? 'Los colores no se editan: elimínalo y registra el correcto' : 'Eliminar' }}">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <p class="nota-pie" style="margin-top:8px">El check decide qué tokens se aplican como estilos globales en la construcción. Las tipografías y espaciados se editan con ✎; los colores no se editan — se elimina el token y se registra el color correcto (evita valores "corregidos a ojo").</p>
            @endif
            <form method="post" action="{{ route('token.guardar', $proyecto) }}" class="form-inline" style="margin-top:12px">
                @csrf
                <div class="campo corto">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo" class="como-input">
                        <option value="color">Color</option>
                        <option value="tipografia">Tipografía</option>
                        <option value="espaciado">Espaciado</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="campo">
                    <label for="valor">Valor *</label>
                    <input id="valor" name="valor" required maxlength="120" placeholder="#7A3E2E · Poppins (títulos) · 8 px">
                    @error('valor')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="campo">
                    <label for="nota">Nota</label>
                    <input id="nota" name="nota" maxlength="160" placeholder="primario / cuerpo / contenedor…">
                </div>
                <div class="acciones"><button class="btn p" type="submit">Agregar token</button></div>
            </form>
        </div>
    </div>
@endsection
