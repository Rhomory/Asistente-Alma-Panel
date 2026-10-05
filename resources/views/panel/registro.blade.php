@extends('layouts.app')
@section('titulo', 'Registro')
@section('migas')<a href="{{ route('dashboard') }}" class="raiz"><x-ic n="inicio" c="sm" />Panel</a> / <b aria-current="page">Registro de cambios</b>@endsection

@section('contenido')
    <div class="cabeza">
        <div>
            <h1>Registro de cambios</h1>
            <p>Cada fila la escribe la consola al terminar una sección. Este registro es la base de la medición de tiempos y del beneficio/costo.</p>
        </div>
        <a class="btn" href="{{ route('registro.export', request()->query()) }}"><x-ic n="lista" />Exportar CSV</a>
    </div>

    <section class="caja">
        <form method="get" class="caja-cab form-linea" style="align-items:center">
            <div class="campo" style="max-width:240px">
                <select name="proyecto_id" aria-label="Proyecto" onchange="this.form.submit()">
                    <option value="">Todos los proyectos</option>
                    @foreach ($proyectos as $p)
                        <option value="{{ $p->id }}" @selected(($filtro['proyecto_id'] ?? '') == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="campo" style="max-width:280px">
                <input type="search" name="q" placeholder="Buscar sección…" value="{{ $filtro['q'] ?? '' }}" aria-label="Buscar sección">
            </div>
            <button class="btn" type="submit">Filtrar</button>
            <span class="nota derecha">{{ $secciones->total() }} {{ $secciones->total() === 1 ? 'sección' : 'secciones' }}</span>
        </form>
        <div class="tabla-env">
            <table class="tabla">
                <thead><tr><th>Fecha</th><th>Proyecto</th><th>Página</th><th>Sección</th><th class="n">Min</th><th class="n">Asistente</th><th class="n">Dev</th><th class="n">Correcc.</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse ($secciones as $s)
                    <tr>
                        <td class="mut nw">{{ $s->inicio?->setTimezone('America/Lima')->format('d/m/Y') ?? '—' }}</td>
                        <td class="nw">{{ $s->pagina->proyecto->nombre }}</td>
                        <td class="nw"><a href="{{ route('pagina', $s->pagina_id) }}">{{ $s->pagina->nombre }}</a></td>
                        <td>{{ $s->nombre }}</td>
                        <td class="n">{{ $s->minutos }}</td>
                        <td class="n">{{ $s->min_asistente }}</td>
                        <td class="n">{{ $s->min_dev }}</td>
                        <td class="n">{{ $s->correcciones }}</td>
                        <td>@include('panel.partes.estado', ['estado' => $s->estado])</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="vacio">Sin registros con ese filtro.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($secciones->hasPages())
            <div class="paginacion">{{ $secciones->links() }}</div>
        @endif
    </section>
@endsection
