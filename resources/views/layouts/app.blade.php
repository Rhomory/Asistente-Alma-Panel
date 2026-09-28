<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Panel') · Asistente Alma</title>
    <link rel="stylesheet" href="{{ asset('css/panel.css') }}">
</head>
<body>
<div class="app">
    <aside>
        <div class="logo">Asistente Alma
            <small>Figma → WordPress/Elementor · MCP</small>
        </div>
        <nav>
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">Dashboard</a>
            <a href="{{ route('conexion') }}" class="{{ request()->routeIs('conexion') ? 'on' : '' }}">Conexión</a>
            <a href="{{ route('registro') }}" class="{{ request()->routeIs('registro') ? 'on' : '' }}">Registro</a>
            <div class="nav-sep">Proyectos</div>
            @foreach (\App\Models\Proyecto::orderBy('nombre')->get() as $p)
                <a href="{{ route('proyecto', $p) }}" class="{{ request()->fullUrlIs(route('proyecto', $p)) ? 'on' : '' }}">{{ $p->nombre }}</a>
            @endforeach
            <a href="{{ route('proyecto.nuevo') }}" class="nuevo {{ request()->routeIs('proyecto.nuevo') ? 'on' : '' }}">+ Nuevo proyecto</a>
        </nav>
        <div class="side-note">La consola construye y escribe el registro; el panel supervisa y administra el catálogo. Nada se publica sin aprobación.</div>
    </aside>
    <main>
        @if (session('ok'))
            <div class="flash-ok">{{ session('ok') }}</div>
        @endif
        @yield('contenido')
        <footer>Proyecto de Mejora · Alma Industria Creativa E.I.R.L. · datos de demostración del piloto</footer>
    </main>
</div>
@stack('scripts')
</body>
</html>
