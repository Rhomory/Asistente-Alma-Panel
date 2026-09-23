@extends('layouts.app')
@section('titulo', 'Dashboard')

@section('contenido')
    <h1>Dashboard del piloto</h1>
    <p class="sub">Avance del asistente frente a la línea base del proceso manual (480 min por página).</p>

    <div class="kpis">
        <div class="kpi lav">
            <div class="v">{{ intdiv($kpis['promedio_min'], 60) }} h {{ str_pad($kpis['promedio_min'] % 60, 2, '0', STR_PAD_LEFT) }} min</div>
            <div class="l">promedio por página terminada ({{ $kpis['paginas_medidas'] }} {{ $kpis['paginas_medidas'] === 1 ? 'página' : 'páginas' }})</div>
            @if ($kpis['promedio_min'] > 0)
                <div class="delta">▼ {{ round(100 - 100 * $kpis['promedio_min'] / $kpis['linea_base']) }} % frente a la línea base (8 h)</div>
            @endif
        </div>
        <div class="kpi ora">
            <div class="v">{{ $kpis['pct_asistente'] }} % / {{ 100 - $kpis['pct_asistente'] }} %</div>
            <div class="l">minutos asistente / desarrollador</div>
            <div class="stack"><i style="width: {{ $kpis['pct_asistente'] }}%"></i></div>
        </div>
        <div class="kpi gris">
            <div class="v">{{ str_replace('.', ',', (string) $kpis['correcciones']) }}</div>
            <div class="l">correcciones promedio por página</div>
            <div class="delta ok">verificación por secciones activa ✓</div>
        </div>
    </div>

    <div class="charts">
        <div class="card">
            <h3>Minutos por página frente a la línea base</h3>
            <canvas id="chartPaginas" height="210"></canvas>
        </div>
        <div class="card">
            <h3>¿Quién ejecuta el trabajo?</h3>
            <canvas id="chartDonut" height="210"></canvas>
        </div>
    </div>

    <div class="card">
        <h3>Últimas secciones registradas</h3>
        <table>
            <thead><tr><th>Sección</th><th>Página</th><th>Proyecto</th><th class="n">Min</th><th>Ejecutó</th><th class="n">Correcc.</th><th>Estado</th></tr></thead>
            <tbody>
            @forelse ($ultimas as $s)
                <tr>
                    <td>{{ $s->nombre }}</td>
                    <td>{{ $s->pagina->nombre }}</td>
                    <td>{{ $s->pagina->proyecto->nombre }}</td>
                    <td class="n">{{ $s->minutos }}</td>
                    <td><span class="tag {{ $s->ejecuto }}">{{ $s->ejecuto }}</span></td>
                    <td class="n">{{ $s->correcciones }}</td>
                    <td>{{ $s->aprobada ? '✓ aprobada' : 'en revisión' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="vacio">Aún no hay secciones registradas. Usa <code>php artisan registro:add</code> o importa la plantilla CSV.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.min.js') }}"></script>
<script>
const C = { ora: '#F2915F', orad: '#C9622B', lav: '#B9A8E0', ink: '#171717', gris: '#8A8578' };
Chart.defaults.font.family = "Poppins, 'Segoe UI', Arial, sans-serif";
Chart.defaults.animation = false; // los datos llegan por recarga; el movimiento no comunica estado

new Chart(document.getElementById('chartPaginas'), {
    data: {
        labels: @json($reportes->map(fn ($r) => $r->nombre . ' · ' . preg_replace('/^(Sitio|Portal)\s+/u', '', $r->proyecto))),
        datasets: [
            { type: 'bar', label: 'Minutos reales', data: @json($reportes->pluck('min_total')), backgroundColor: C.ora, borderRadius: 4, maxBarThickness: 56 },
            { type: 'line', label: 'Línea base (480 min)', data: @json($reportes->map(fn () => 480)), borderColor: C.gris, borderDash: [7, 6], borderWidth: 2, pointRadius: 0, fill: false },
        ],
    },
    options: {
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, suggestedMax: 500 }, x: { ticks: { maxRotation: 20, minRotation: 0 } } },
    },
});
new Chart(document.getElementById('chartDonut'), {
    type: 'doughnut',
    data: {
        labels: ['Asistente', 'Desarrollador'],
        datasets: [{ data: [@json($donut['asistente']), @json($donut['dev'])], backgroundColor: [C.ora, C.lav], borderWidth: 0 }],
    },
    options: { plugins: { legend: { position: 'bottom' } }, cutout: '62%' },
});
</script>
@endpush
