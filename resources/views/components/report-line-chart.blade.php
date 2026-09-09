@props(['points', 'series', 'unit' => 'Bs', 'label' => 'Evolución del período'])
@php
    $valores = [];
    foreach ($points as $punto) {
        foreach ($series as $serie) {
            $valores[] = (float) $punto[$serie['key']];
        }
    }
    $minimo = min(0, ...($valores ?: [0]));
    $maximo = max(1, ...($valores ?: [0]));
    $rango = $maximo - $minimo;
    $cantidad = count($points);
    $paso = max(1, (int) ceil(($cantidad - 1) / 6));
    $hayActividad = count(array_filter($valores, fn ($valor) => $valor != 0)) > 0;
@endphp
@if(! $hayActividad)
    <div class="grid min-h-80 place-items-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center">
        <div><p class="font-semibold text-brand-ink">Sin actividad en este período</p><p class="mt-2 text-sm text-slate-500">Prueba otro rango de fechas o ajusta los filtros.</p></div>
    </div>
@else
    <div class="overflow-x-auto pb-2">
        <svg viewBox="0 0 920 360" class="h-[360px] w-full min-w-[640px]" role="img" aria-label="{{ $label }}. Eje vertical en {{ $unit }}. Los valores exactos están en la tabla desplegable.">
            <title>{{ $label }}</title>
            <desc>Series independientes, con escala común y períodos sin actividad incluidos.</desc>
            @for($i = 0; $i <= 4; $i++)
                @php
                    $y = 24 + $i * 65;
                    $valorEje = $maximo - $rango * $i / 4;
                @endphp
                <line x1="90" y1="{{ $y }}" x2="900" y2="{{ $y }}" stroke="#e2e8f0" stroke-dasharray="4 4" />
                <text x="78" y="{{ $y + 4 }}" text-anchor="end" fill="#64748b" font-size="12">{{ number_format($valorEje, $unit === 'Bs' ? 2 : (floor($valorEje) == $valorEje ? 0 : 1)) }}</text>
            @endfor
            <line x1="90" y1="{{ 24 + $maximo / $rango * 260 }}" x2="900" y2="{{ 24 + $maximo / $rango * 260 }}" stroke="#94a3b8" />
            @foreach($series as $serie)
                @php
                    $coordenadas = [];
                    foreach ($points as $indice => $punto) {
                        $x = $cantidad === 1 ? 495 : 90 + $indice / ($cantidad - 1) * 810;
                        $y = 24 + ($maximo - $punto[$serie['key']]) / $rango * 260;
                        $coordenadas[] = $x.','.$y;
                    }
                @endphp
                <polyline points="{{ implode(' ', $coordenadas) }}" fill="none" stroke="{{ $serie['color'] }}" stroke-width="3" stroke-linejoin="round" stroke-dasharray="{{ $loop->index === 1 ? '8 4' : 'none' }}" />
                @foreach($points as $indice => $punto)
                    <circle cx="{{ $cantidad === 1 ? 495 : 90 + $indice / ($cantidad - 1) * 810 }}" cy="{{ 24 + ($maximo - $punto[$serie['key']]) / $rango * 260 }}" r="4" fill="white" stroke="{{ $serie['color'] }}" stroke-width="2">
                        <title>{{ $punto['desde'] }} al {{ $punto['hasta'] }} · {{ $serie['label'] }}: {{ $unit }} {{ number_format($punto[$serie['key']], $unit === 'Bs' ? 2 : 0) }}</title>
                    </circle>
                @endforeach
            @endforeach
            @foreach($points as $indice => $punto)
                @if($indice % $paso === 0 || $loop->last)
                    <text x="{{ $cantidad === 1 ? 495 : 90 + $indice / ($cantidad - 1) * 810 }}" y="312" text-anchor="{{ $loop->last ? 'end' : ($loop->first ? 'start' : 'middle') }}" fill="#64748b" font-size="12">{{ $punto['fecha'] }}</text>
                @endif
            @endforeach
            <text x="90" y="345" fill="#64748b" font-size="12">{{ $unit }} · Cada punto representa el total de su intervalo</text>
        </svg>
    </div>
@endif
<div class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-sm">
    @foreach($series as $serie)
        <span class="flex items-center gap-2 text-slate-600"><span class="w-6 border-t-[3px] {{ $loop->index === 1 ? 'border-dashed' : '' }}" style="border-color: {{ $serie['color'] }}"></span>{{ $serie['label'] }}</span>
    @endforeach
</div>
<details class="mt-5 border-t border-slate-100 pt-4">
    <summary class="cursor-pointer text-sm font-semibold text-brand-blue focus-visible:outline-2 focus-visible:outline-brand-blue">Ver valores exactos del gráfico</summary>
    @php
        $columnas = ['desde' => 'Desde', 'hasta' => 'Hasta'];
        foreach ($series as $serie) {
            $columnas[$serie['key']] = $serie['label'].' ('.$unit.')';
        }
    @endphp
    <div class="mt-3"><x-report-table :columns="$columnas" :rows="$points" :money="$unit === 'Bs' ? array_column($series, 'key') : []" :caption="$label" /></div>
</details>
