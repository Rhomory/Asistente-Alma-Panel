<!doctype html>
<html lang="es" data-theme="oscuro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · Asistente Alma</title>
    <script>
        // Tema antes de pintar, para no parpadear. Oscuro por defecto.
        try {
            const q = new URLSearchParams(location.search).get('tema');   // ?tema=claro|oscuro fija la preferencia
            if (q === 'claro' || q === 'oscuro') localStorage.setItem('alma-tema', q);
            document.documentElement.dataset.theme = localStorage.getItem('alma-tema') || 'oscuro';
        } catch (e) {}
    </script>
    <link rel="preload" href="{{ asset('fonts/poppins-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poppins-600.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
<div class="app">
    <aside class="side">
        <a class="marca" href="{{ route('dashboard') }}">
            <span class="marca-logo"><x-ic n="chispa" c="lg" /></span>
            <span><b>Asistente Alma</b><small>Panel de operaciones</small></span>
        </a>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}"><x-ic n="inicio" /><span class="txt">Inicio</span>
                @if ($navCola)<span class="cuenta alerta" title="Correcciones en cola">{{ $navCola }}</span>@endif</a>
            <a href="{{ route('registro') }}" class="{{ request()->routeIs('registro') ? 'on' : '' }}"><x-ic n="lista" /><span class="txt">Registro de secciones</span></a>
            <a href="{{ route('conexion') }}" class="{{ request()->routeIs('conexion') ? 'on' : '' }}"><x-ic n="enchufe" /><span class="txt">Conexión</span></a>
        </nav>
        <div class="nav-grupo">Proyectos</div>
        <nav class="nav">
            @php $proyectoActual = request()->route('proyecto') ?? request()->route('pagina')?->proyecto; @endphp
            @foreach ($navProyectos as $p)
                <a href="{{ route('proyecto', $p) }}" class="{{ $proyectoActual?->id === $p->id ? 'on' : '' }}"><x-ic n="capas" /><span class="txt">{{ $p->nombre }}</span></a>
            @endforeach
            <a href="{{ route('proyecto.nuevo') }}" class="{{ request()->routeIs('proyecto.nuevo') ? 'on' : '' }}"><x-ic n="mas" /><span class="txt">Nuevo proyecto</span></a>
        </nav>
        <div class="side-pie"><b><x-ic n="escudo" c="sm" />Nada se publica solo</b>El asistente construye en borrador y registra; tú apruebas cada sección antes de pedir QA.</div>
    </aside>

    <div class="cuerpo">
        <header class="barra">
            <div class="miga">@hasSection('migas') @yield('migas') @else <b>Inicio</b> @endif</div>
            <div class="asistente-pill {{ $asistente['activo'] ? 'activo' : '' }}" title="{{ $asistente['hace'] ? 'Último registro de la consola ' . $asistente['hace'] : 'La consola aún no registra secciones' }}">
                <i class="pulso"></i>
                @if ($asistente['activo'])
                    Asistente construyendo <span>· {{ $asistente['detalle'] }}</span>
                @else
                    Asistente en espera @if ($asistente['hace'])<span>· último registro {{ $asistente['hace'] }}</span>@endif
                @endif
            </div>
            <span class="vivo" title="El panel se recarga solo cuando la consola registra algo"><i class="pulso"></i>En vivo</span>
            <button class="tema" type="button" id="tema" title="Cambiar a tema claro u oscuro" aria-label="Cambiar tema">
                <x-ic n="sol" /><x-ic n="luna" />
            </button>
        </header>

        <main class="contenido">
            @if (session('ok'))
                <div class="flash" role="status"><x-ic n="check" />{{ session('ok') }}</div>
            @endif
            @yield('contenido')
        </main>
        <footer class="pie">Asistente Alma · Alma Industria Creativa E.I.R.L. — Arequipa</footer>
    </div>
</div>

<div class="aviso-vivo" id="aviso-vivo" hidden role="status">
    <x-ic n="refrescar" /> La consola registró cambios nuevos.
    <button class="btn p chico" type="button" onclick="location.reload()">Ver cambios</button>
</div>

<script>
(function () {
    // Tema
    document.getElementById('tema').addEventListener('click', function () {
        const nuevo = document.documentElement.dataset.theme === 'claro' ? 'oscuro' : 'claro';
        document.documentElement.dataset.theme = nuevo;
        try { localStorage.setItem('alma-tema', nuevo); } catch (e) {}
    });

    // Copiar al portapapeles: cualquier botón con data-copiar="#id" o data-texto="…"
    document.addEventListener('click', async function (ev) {
        const b = ev.target.closest('[data-copiar], [data-texto]');
        if (!b) return;
        const origen = b.dataset.copiar ? document.querySelector(b.dataset.copiar) : null;
        const texto = b.dataset.texto ?? (origen ? (origen.value ?? origen.textContent) : '');
        try { await navigator.clipboard.writeText(texto); }
        catch (e) { const t = document.createElement('textarea'); t.value = texto; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
        b.classList.add('hecho'); setTimeout(() => b.classList.remove('hecho'), 1400);
        b.dispatchEvent(new CustomEvent('copiado', { bubbles: true }));
    });

    // Recarga en vivo: si la consola escribe algo, la vista se recarga y muestra lo nuevo.
    // Si estás escribiendo en un formulario, no se recarga: aparece un aviso.
    try { const y = sessionStorage.getItem('alma-scroll'); if (y) { scrollTo(0, +y); sessionStorage.removeItem('alma-scroll'); } } catch (e) {}
    let version = null;
    const editando = () => {
        const a = document.activeElement;
        if (a && a.matches('input:not([type=checkbox]), textarea, select')) return true;
        if (document.querySelector('details[open]')) return true;
        return [...document.querySelectorAll('form input[type=text], form input[type=url], form textarea:not([readonly])')].some(i => i.value !== i.defaultValue);
    };
    async function revisar() {
        if (document.hidden) return;
        try {
            const r = await fetch('{{ route('estado.version') }}', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const { v } = await r.json();
            if (version && v !== version) {
                if (editando()) { document.getElementById('aviso-vivo').hidden = false; }
                else { try { sessionStorage.setItem('alma-scroll', scrollY); } catch (e) {} location.reload(); return; }
            }
            version = v;
        } catch (e) {}
    }
    revisar();
    setInterval(revisar, 4000);
    document.addEventListener('visibilitychange', revisar);
})();
</script>
@stack('scripts')
</body>
</html>
