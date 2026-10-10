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
            // Menú lateral plegado (solo iconos) o completo; se recuerda por equipo.
            document.documentElement.dataset.menu = localStorage.getItem('alma-menu') === 'corto' ? 'corto' : 'largo';
        } catch (e) {}
    </script>
    <link rel="preload" href="{{ asset('fonts/poppins-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poppins-500.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poppins-600.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/poppins-700.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}?v={{ filemtime(public_path('css/panel.css')) }}">
    <script src="{{ asset('js/motion.js') }}?v=14.0.0"></script>
</head>
<body>
<div class="app">
    <aside class="side">
        <div class="side-cab">
            <a class="marca" href="{{ route('dashboard') }}" title="Asistente Alma">
                <span class="marca-logo"><x-ic n="chispa" c="lg" /></span>
                <span class="txt"><b>Asistente Alma</b><small>Panel de operaciones</small></span>
            </a>
            <button class="plegar" type="button" id="plegar" aria-controls="menu-lateral" title="Plegar o desplegar el menú" aria-label="Plegar o desplegar el menú"><x-ic n="panel" /></button>
        </div>
        <div class="side-scroll" id="menu-lateral">
            <nav class="nav">
                <a href="{{ route('dashboard') }}" title="Panel" class="c-lav {{ request()->routeIs('dashboard') ? 'on' : '' }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif><x-ic n="inicio" /><span class="txt">Panel</span>
                    @if ($navCola)<span class="cuenta alerta" title="Correcciones en cola">{{ $navCola }}</span>@endif</a>
                <a href="{{ route('registro') }}" title="Registro de cambios" class="c-ok {{ request()->routeIs('registro') ? 'on' : '' }}" @if (request()->routeIs('registro')) aria-current="page" @endif><x-ic n="lista" /><span class="txt">Registro de cambios</span></a>
                <a href="{{ route('conexion') }}" title="Conexión" class="c-warn {{ request()->routeIs('conexion') ? 'on' : '' }}" @if (request()->routeIs('conexion')) aria-current="page" @endif><x-ic n="enchufe" /><span class="txt">Conexión</span></a>
                <a href="{{ route('flujo') }}" title="Cómo funciona" class="c-lav {{ request()->routeIs('flujo') ? 'on' : '' }}" @if (request()->routeIs('flujo')) aria-current="page" @endif><x-ic n="libro" /><span class="txt">Cómo funciona</span></a>
            </nav>
            <div class="nav-grupo"><span class="txt">Proyectos</span></div>
            <nav class="nav">
                @php $proyectoActual = request()->route('proyecto') ?? request()->route('pagina')?->proyecto; @endphp
                @foreach ($navProyectos as $p)
                    @php
                        [$est, $tit] = $p->p_total === 0 ? ['', 'Sin páginas'] : ($p->p_listas === $p->p_total ? ['lista', 'Páginas aprobadas'] : ($p->p_obra ? ['obra', 'En construcción'] : ['', 'Por empezar']));
                        $ini = collect(preg_split('/\s+/', \Illuminate\Support\Str::ascii($p->nombre)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                    @endphp
                    <a href="{{ route('proyecto', $p) }}" title="{{ $p->nombre }} · {{ $tit }}" class="proy-nav {{ $proyectoActual?->id === $p->id ? 'on' : '' }}" @if ($proyectoActual?->id === $p->id) aria-current="page" @endif><x-ic n="capas" /><span class="ini" aria-hidden="true">{{ $ini }}</span><span class="txt">{{ $p->nombre }}</span>
                        <i class="estado-p {{ $est }}" aria-label="{{ $tit }}"></i></a>
                @endforeach
                <a href="{{ route('proyecto.nuevo') }}" title="Nuevo proyecto" class="c-ora {{ request()->routeIs('proyecto.nuevo') ? 'on' : '' }}" @if (request()->routeIs('proyecto.nuevo')) aria-current="page" @endif><x-ic n="mas" /><span class="txt">Nuevo proyecto</span></a>
            </nav>
            <div class="side-pie"><b><x-ic n="escudo" c="sm" />Nada se publica solo</b>El asistente construye en borrador y registra; tú apruebas cada sección antes de pedir QA.</div>
        </div>
        <div class="side-fin">
            <button class="tema" type="button" id="tema" title="Cambiar a tema claro u oscuro" aria-label="Cambiar tema">
                <x-ic n="sol" /><x-ic n="luna" /><span class="txt">Cambiar tema</span>
            </button>
            {{-- Solo visual por ahora: las cuentas y el inicio de sesión llegarán después. --}}
            <div class="usuario" title="Cuenta (el inicio de sesión llega más adelante)">
                <span class="avatar" aria-hidden="true">R</span>
                <span class="txt"><b>Mi cuenta</b><small>Sesión local</small></span>
            </div>
        </div>
    </aside>

    <div class="cuerpo">
        <header class="barra">
            <nav class="miga" aria-label="Ruta">@hasSection('migas') @yield('migas') @else <b aria-current="page"><x-ic n="inicio" c="sm" />Panel</b> @endif</nav>
            <div class="asistente-pill {{ $asistente['activo'] ? 'activo' : '' }}" title="{{ $asistente['hace'] ? 'Último registro de la consola ' . $asistente['hace'] : 'La consola aún no registra secciones' }}">
                <i class="pulso"></i>
                @if ($asistente['activo'])
                    Asistente construyendo <span>· {{ $asistente['detalle'] }}</span>
                @else
                    Asistente en espera @if ($asistente['hace'])<span>· último registro {{ $asistente['hace'] }}</span>@endif
                @endif
            </div>
            {{-- Botones redondos de la barra (concepto A2): crear, buscar y avisos. --}}
            <div class="barra-botones">
                <a class="redondo" href="{{ route('proyecto.nuevo') }}" title="Nuevo proyecto" aria-label="Nuevo proyecto"><x-ic n="mas" /></a>
                {{-- La lupa despliega el buscador hacia la izquierda; atajo: tecla "/". --}}
                <form class="expande" data-expande id="buscar" method="get" action="{{ route('registro') }}" role="search">
                    <input type="search" name="q" placeholder="Buscar una sección…" aria-label="Buscar una sección en el registro" autocomplete="off">
                    <button class="redondo" type="submit" title="Buscar en el registro (/)" aria-label="Buscar en el registro" aria-keyshortcuts="/"><x-ic n="lupa" /></button>
                </form>
                <a class="redondo" href="{{ route('dashboard') }}#atencion" title="{{ $navCola ? $navCola . ' ' . ($navCola === 1 ? 'corrección' : 'correcciones') . ' en cola' : 'Sin correcciones en cola' }} · el panel se actualiza en vivo" aria-label="Avisos{{ $navCola ? ': ' . $navCola . ' en cola' : '' }}"><x-ic n="campana" />@if ($navCola)<i class="aviso-punto"></i>@endif</a>
            </div>
        </header>

        <main class="contenido">
            @yield('contenido')
        </main>
        <footer class="pie">Asistente Alma · Alma Industria Creativa E.I.R.L. — Arequipa</footer>
    </div>
</div>

@if (session('ok'))
    <div class="toast" id="toast" role="status"><x-ic n="check" />{{ session('ok') }}</div>
@endif

<div class="aviso-vivo" id="aviso-vivo" hidden role="status">
    <x-ic n="refrescar" /> La consola registró cambios nuevos.
    <button class="btn p chico" type="button" onclick="try { sessionStorage.setItem('alma-sin-entrada', '1'); } catch (e) {} location.reload()">Ver cambios</button>
</div>

<script>
(function () {
    // Tema
    document.getElementById('tema').addEventListener('click', function () {
        const nuevo = document.documentElement.dataset.theme === 'claro' ? 'oscuro' : 'claro';
        const aplicar = () => {
            document.documentElement.dataset.theme = nuevo;
            try { localStorage.setItem('alma-tema', nuevo); } catch (e) {}
        };
        // Fundido suave entre temas; sin soporte o con "reducir movimiento", al instante.
        if (!document.startViewTransition || matchMedia('(prefers-reduced-motion: reduce)').matches) return aplicar();
        try { document.startViewTransition({ update: aplicar, types: ['tema'] }); }
        catch (e) { document.startViewTransition(aplicar); }
    });

    // Buscadores que se despliegan desde la lupa: el primer clic abre, el siguiente busca;
    // Escape (o salir con el campo vacío) lo vuelve a cerrar.
    document.querySelectorAll('[data-expande]').forEach(function (caja) {
        const campo = caja.querySelector('input'), boton = caja.querySelector('button');
        const abrir = (enfocar) => { caja.classList.add('abierto'); campo.tabIndex = 0; boton.setAttribute('aria-expanded', 'true'); if (enfocar) setTimeout(() => campo.focus(), 30); };
        const cerrar = () => { caja.classList.remove('abierto'); campo.tabIndex = -1; boton.setAttribute('aria-expanded', 'false'); };
        campo.value.trim() ? abrir(false) : cerrar();
        boton.addEventListener('click', function (ev) {
            if (!caja.classList.contains('abierto')) { ev.preventDefault(); abrir(true); }
            else if (!campo.value.trim()) { ev.preventDefault(); cerrar(); }
        });
        campo.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') { campo.value = ''; cerrar(); boton.focus(); } });
        caja.addEventListener('focusout', function (ev) { if (!caja.contains(ev.relatedTarget) && !campo.value.trim()) cerrar(); });
    });
    document.addEventListener('keydown', function (ev) {
        const buscar = document.getElementById('buscar');
        if (ev.key !== '/' || !buscar || ev.target.closest('input, textarea, select, [contenteditable]')) return;
        ev.preventDefault();
        buscar.classList.contains('abierto') ? buscar.querySelector('input').focus() : buscar.querySelector('button').click();
    });

    // Menú plegable: cambia al instante (el menú nunca se anima) y se recuerda.
    const plegar = document.getElementById('plegar');
    const marcarPlegado = () => plegar.setAttribute('aria-expanded', document.documentElement.dataset.menu === 'corto' ? 'false' : 'true');
    marcarPlegado();
    plegar.addEventListener('click', function () {
        const nuevo = document.documentElement.dataset.menu === 'corto' ? 'largo' : 'corto';
        const aplicar = () => {
            document.documentElement.dataset.menu = nuevo;
            try { localStorage.setItem('alma-menu', nuevo); } catch (e) {}
            marcarPlegado();
        };
        // Con View Transitions el riel cambia de ancho suavemente; sin soporte o con "reducir movimiento", al instante.
        if (!document.startViewTransition || matchMedia('(prefers-reduced-motion: reduce)').matches) return aplicar();
        try { document.startViewTransition({ update: aplicar, types: ['menu'] }); }
        catch (e) { document.startViewTransition(aplicar); }
    });

    // Alt+↑ sube un nivel (al destino del botón .btn-subir); Alt+← sigue siendo el historial del navegador.
    document.addEventListener('keydown', function (ev) {
        const subir = document.querySelector('.btn-subir');
        if (subir && ev.altKey && ev.key === 'ArrowUp') { ev.preventDefault(); location.href = subir.href; }
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

    // Entrada de la vista, estilo dashboard, con Motion (resortes sin rebote).
    // No se repite en las recargas automáticas ni con "reducir movimiento"; sin Motion, todo queda quieto y visible.
    const M = window.Motion;
    let sinEntrada = !M || matchMedia('(prefers-reduced-motion: reduce)').matches;
    try { if (sessionStorage.getItem('alma-sin-entrada')) { sinEntrada = true; sessionStorage.removeItem('alma-sin-entrada'); } } catch (e) {}
    // La cascada solo al abrir el panel; al moverse entre páginas basta el fundido (sin saltos).
    try { if (sessionStorage.getItem('alma-visto')) sinEntrada = true; sessionStorage.setItem('alma-visto', '1'); } catch (e) {}
    if (!sinEntrada) {
        const { animate, stagger } = M;
        const suave = { type: 'spring', bounce: 0, visualDuration: 0.5 };
        // 1. Bloques en cascada, en orden de lectura (cabecera, resumen, cajas).
        const bloques = [...document.querySelectorAll('.contenido > .cabeza, .contenido > .caja, .contenido > form, .contenido .rejilla > .caja, .contenido .rejilla > .col > .caja, .contenido .guia > .col > .caja, .contenido .guia > .caja')].slice(0, 10);
        if (bloques.length) animate(bloques, { opacity: [0, 1], y: [10, 0] }, { ...suave, delay: stagger(0.06) });
        // 2. Filas de cada lista, una tras otra (máx. 12 por lista).
        document.querySelectorAll('.contenido .caja').forEach(caja => {
            const filas = [...caja.querySelectorAll(':scope .pag:not(.cab), :scope .sec, :scope .pendiente, :scope .evento, :scope .proy, :scope .tabla tbody tr, :scope .token, :scope .cmd, :scope .ciclo > li, :scope .piezas > div, :scope .estados > div, :scope .reglas > p')].slice(0, 12);
            if (filas.length) animate(filas, { opacity: [0, 1] }, { duration: 0.4, ease: 'easeOut', delay: stagger(0.035, { startDelay: 0.18 }) });
        });
        // 3. Barras de avance que crecen.
        const barras = [...document.querySelectorAll('.contenido .barra-prog i, .contenido .seg i')];
        if (barras.length) animate(barras, { scaleX: [0, 1] }, { type: 'spring', bounce: 0, visualDuration: 0.8, delay: stagger(0.025, { startDelay: 0.25 }) });
        // 4. Cifras que cuentan hasta su valor (formatos "187", "65 %", "0,6").
        document.querySelectorAll('.contenido .resumen b, .contenido .avance b').forEach(el => {
            const m = el.textContent.trim().match(/^(\d+)(?:,(\d+))?(\s*%)?$/);
            if (!m) return;
            const final = parseFloat(m[1] + '.' + (m[2] || '0')), dec = m[2] ? m[2].length : 0, sufijo = m[3] || '';
            if (!final) return;
            const formato = v => v.toFixed(dec).replace('.', ',') + sufijo;
            el.textContent = formato(0);
            animate(0, final, { duration: 0.9, delay: 0.25, ease: [0.22, 1, 0.36, 1], onUpdate: v => { el.textContent = formato(v); } });
        });
    }

    // Aviso flotante: entra con un resorte suave y se va solo a los 4,5 s (o al hacer clic).
    const toast = document.getElementById('toast');
    if (toast) {
        if (M && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
            M.animate(toast, { opacity: [0, 1], y: [16, 0] }, { type: 'spring', bounce: 0.15, visualDuration: 0.4 });
        }
        const quitar = () => {
            if (M) M.animate(toast, { opacity: 0, y: 10 }, { duration: 0.25, ease: 'easeIn' }).then(() => toast.remove());
            else toast.remove();
        };
        setTimeout(quitar, 4500);
        toast.addEventListener('click', quitar);
    }

    // Recarga en vivo: si la consola escribe algo, la vista se recarga y muestra lo nuevo.
    // Si estás escribiendo en un formulario, no se recarga: aparece un aviso.
    try { const y = sessionStorage.getItem('alma-scroll'); if (y) { scrollTo(0, +y); sessionStorage.removeItem('alma-scroll'); } } catch (e) {}
    let version = null;
    const editando = () => {
        const a = document.activeElement;
        if (a && a.matches('input:not([type=checkbox]), textarea, select')) return true;
        if (document.querySelector('details[open]:not(.config-agente)')) return true;
        return [...document.querySelectorAll('form input[type=text], form input[type=url], form textarea:not([readonly])')].some(i => i.value !== i.defaultValue);
    };
    async function revisar() {
        if (document.hidden) return;
        try {
            const r = await fetch('{{ route('estado.version') }}', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const { v } = await r.json();
            if (version && v !== version) {
                if (editando()) { document.getElementById('aviso-vivo').hidden = false; }
                else { try { sessionStorage.setItem('alma-scroll', scrollY); sessionStorage.setItem('alma-sin-entrada', '1'); } catch (e) {} location.reload(); return; }
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
