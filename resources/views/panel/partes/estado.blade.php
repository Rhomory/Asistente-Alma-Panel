@php
    $nombres = [
        'aprobada' => 'Aprobada', 'en_qc' => 'QA solicitado', 'construyendo' => 'En construcción',
        'construida' => 'Por revisar', 'pendiente' => 'Pendiente', 'planificada' => 'Planificada',
    ];
@endphp
<span class="tag e-{{ $estado }}">{{ $nombres[$estado] ?? ucfirst(str_replace('_', ' ', $estado)) }}</span>
