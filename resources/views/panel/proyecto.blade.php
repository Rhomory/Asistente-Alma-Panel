@extends('layouts.app')
@section('titulo', $proyecto->nombre)

@section('contenido')
    <h1>{{ $proyecto->nombre }}</h1>
    <p class="sub">
        {{ $proyecto->cliente }} @if($proyecto->sitio_wp) · staging: {{ $proyecto->sitio_wp }} @endif
    </p>

    <div class="card">
        <h3>Páginas del proyecto</h3>
        <table>
            <thead><tr><th>Página</th><th>Estado</th><th class="n">Secciones</th><th class="n">Min reales</th><th class="n">vs línea base</th><th class="n">% asistente</th><th class="n">Correcc.</th><th style="width:120px"></th></tr></thead>
            <tbody>
            @forelse ($paginas as $rp)
                @php $trabajo = $rp->min_asistente + $rp->min_dev; @endphp
                <tr>
                    <td><b>{{ $rp->nombre }}</b></td>
                    <td><span class="tag estado-{{ $rp->estado }}">{{ str_replace('_', ' ', $rp->estado) }}</span></td>
                    <td class="n">{{ $rp->secciones_reg }}</td>
                    <td class="n">{{ $rp->min_total ?: '—' }}</td>
                    <td class="n">{{ $rp->min_total ? '▼ ' . round(100 - 100 * $rp->min_total / $rp->linea_base_min) . ' %' : '—' }}</td>
                    <td class="n">{{ $trabajo > 0 ? round(100 * $rp->min_asistente / $trabajo) . ' %' : '—' }}</td>
                    <td class="n">{{ $rp->correcciones }}</td>
                    <td><a class="btn s chico" href="{{ route('pagina', $rp->id) }}">Abrir flujo →</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="vacio">Este proyecto aún no tiene páginas: agrégalas aquí abajo.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="dos-col">
        <div class="card form-card">
            <h3>Agregar página al proyecto</h3>
            <form method="post" action="{{ route('pagina.guardar', $proyecto) }}" class="form-inline">
                @csrf
                <div class="campo">
                    <label for="nombre">Nombre de la página *</label>
                    <input id="nombre" name="nombre" type="text" value="{{ old('nombre') }}" placeholder="Ej.: Contacto" required maxlength="120">
                    @error('nombre')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="campo corto">
                    <label for="secciones_total">Secciones previstas</label>
                    <input id="secciones_total" name="secciones_total" type="number" min="0" max="50" value="{{ old('secciones_total') }}" placeholder="7">
                </div>
                <div class="acciones"><button class="btn p" type="submit">Agregar página</button></div>
            </form>
            <p class="nota-pie">La página nace "pendiente"; su plan se arma en "Abrir flujo" y la consola registra la construcción.</p>
        </div>

        <div class="card">
            <h3>Tokens del diseño (P2 · lectura del diseño)</h3>
            @if ($proyecto->tokens->isEmpty())
                <p class="vacio">Sin tokens registrados: anota aquí los colores, tipografías y espaciados que la guía técnica convertirá en estilos globales de Elementor.</p>
            @else
                <div class="fichas-token">
                    @foreach ($proyecto->tokens as $t)
                        <div class="ficha-token">
                            @if ($t->tipo === 'color')<span class="sw" style="background: {{ $t->valor }}"></span>@endif
                            <div>
                                <b>{{ $t->valor }}</b>
                                <small>{{ ucfirst($t->tipo) }}{{ $t->nota ? ' · ' . $t->nota : '' }}</small>
                            </div>
                            <form method="post" action="{{ route('token.eliminar', $t) }}" onsubmit="return confirm('¿Eliminar este token?')">@csrf @method('DELETE')
                                <button class="quitar" type="submit" title="Eliminar">×</button>
                            </form>
                        </div>
                    @endforeach
                </div>
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
