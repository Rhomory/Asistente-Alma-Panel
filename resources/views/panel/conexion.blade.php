@extends('layouts.app')
@section('titulo', 'Conexión')

@section('contenido')
    <h1>Conexión del entorno</h1>
    <p class="sub">Estado de las herramientas del flujo (punto 4.3 del proyecto). La conexión real de los MCP vive en la configuración de la consola; aquí se supervisa.</p>

    <div class="kpis">
        @foreach ($servicios as $s)
            <div class="kpi gris srv">
                <div class="srv-cab">
                    <b>{{ $s['nombre'] }}</b>
                    <span class="tag estado-srv-{{ $s['estado'] === 'activo' ? 'ok' : 'demo' }}">{{ $s['estado'] }}</span>
                </div>
                <div class="l">{{ $s['detalle'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <h3>Sitios de staging por proyecto</h3>
        <table>
            <thead><tr><th>Proyecto</th><th>Archivo de Figma</th><th>Staging (WordPress)</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($proyectos as $p)
                <tr>
                    <td>{{ $p->nombre }}</td>
                    <td class="mut">{{ $p->archivo_figma ?? '—' }}</td>
                    <td class="mut">{{ $p->sitio_wp ?? '—' }}</td>
                    <td>
                        <span class="tag estado-srv-{{ $p->estado_staging === 'conectado' ? 'ok' : 'off' }}">{{ $p->estado_staging }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="vacio">Aún no hay proyectos. Crea el primero con el botón "Nuevo proyecto".</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="nota-pie">La comprobación del staging es una petición HTTP real con espera máxima de 2 s (se guarda en caché 1 minuto). Los dominios de demostración figuran como "sin conexión".</p>
    </div>

    <div class="card aviso">
        <b>Seguridad:</b> el asistente solo trabaja en borradores; nada se publica sin tu aprobación.
        Las credenciales (contraseñas de aplicación de WordPress, tokens de Figma) nunca se guardan en este panel ni en su base de datos.
    </div>
@endsection
