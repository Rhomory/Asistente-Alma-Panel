@php $esColor = $t->tipo === 'color' && \App\Support\PromptGuia::esColor($t->valor); @endphp
<div class="token {{ $t->incluido ? '' : 'apagado' }}">
    <form method="post" action="{{ route('token.incluir', $t) }}">@csrf
        <input type="hidden" name="incluido" value="{{ $t->incluido ? 0 : 1 }}">
        <input type="checkbox" class="switch" @checked($t->incluido) onchange="this.form.submit()" aria-label="{{ $t->incluido ? 'No usar' : 'Usar' }} el token {{ $t->valor }}">
    </form>
    @if ($esColor)<span class="sw" style="background: {{ $t->valor }}"></span>@endif
    <div class="txt">
        <b class="{{ $esColor ? 'mono' : '' }}">{{ $t->valor }}</b>
        <small>{{ $t->nota ?: ucfirst($t->tipo) }} · {{ $t->origen === 'detectado' ? 'del Figma' : 'manual' }}</small>
    </div>
    @if ($t->tipo !== 'color')
        <details>
            <summary title="Editar"><x-ic n="lapiz" c="sm" /></summary>
            <form method="post" action="{{ route('token.actualizar', $t) }}">@csrf @method('PUT')
                <div class="campo"><label>Valor</label><input type="text" name="valor" value="{{ $t->valor }}" required maxlength="120"></div>
                <div class="campo"><label>Nota</label><input type="text" name="nota" value="{{ $t->nota }}" maxlength="160"></div>
                <div><button class="btn p chico" type="submit">Guardar</button></div>
            </form>
        </details>
    @endif
    <form method="post" action="{{ route('token.eliminar', $t) }}" onsubmit="return confirm('¿Eliminar este token?')">@csrf @method('DELETE')
        <button class="quitar" type="submit" title="Eliminar"><x-ic n="x" c="sm" /></button>
    </form>
</div>
