@extends('layouts.app')
@section('titulo', 'Registro')

@section('contenido')
    <h1>Registro de tiempos</h1>
    <p class="sub">Cada fila la escribe la consola al terminar una sección. Este registro alimenta la evaluación beneficio/costo (Capítulo VI).</p>

    <form method="get" class="filtros">
        <select name="proyecto_id">
            <option value="">Todos los proyectos</option>
            @foreach ($proyectos as $p)
                <option value="{{ $p->id }}" @selected(($filtro['proyecto_id'] ?? '') == $p->id)>{{ $p->nombre }}</option>
            @endforeach
        </select>
        <input type="text" name="q" placeholder="Buscar sección…" value="{{ $filtro['q'] ?? '' }}">
        <button class="btn p" type="submit">Filtrar</button>
        <a class="btn s" href="{{ route('registro.export', request()->query()) }}">Exportar CSV</a>
    </form>

    <div class="card">
        <table>
            <thead><tr><th>Fecha</th><th>Proyecto</th><th>Página</th><th>Sección</th><th class="n">Min</th><th>Ejecutó</th><th class="n">Correcc.</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($secciones as $s)
                <tr>
                    <td class="mut">{{ optional($s->inicio)->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $s->pagina->proyecto->nombre }}</td>
                    <td>{{ $s->pagina->nombre }}</td>
                    <td>{{ $s->nombre }}</td>
                    <td class="n">{{ $s->minutos }}</td>
                    <td><span class="tag {{ $s->ejecuto }}">{{ $s->ejecuto }}</span></td>
                    <td class="n">{{ $s->correcciones }}</td>
                    <td>{{ $s->aprobada ? '✓ aprobada' : 'en revisión' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="vacio">Sin registros con ese filtro.</td></tr>
            @endforelse
            </tbody>
        </table>
        {{ $secciones->links() }}
    </div>
@endsection
