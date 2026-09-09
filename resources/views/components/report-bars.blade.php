@props(['rows', 'labelKey' => 'nombre', 'valueKey' => 'valor', 'unit' => '', 'total' => null, 'color' => '#005ca8', 'secondaryKey' => null, 'secondaryLabel' => '', 'secondaryUnit' => '', 'empty' => 'Sin datos para los filtros seleccionados.'])
@php
    $filas = collect($rows);
    $maximo = max((float) $filas->max($valueKey), 0, $secondaryKey ? (float) $filas->max($secondaryKey) : 0);
    $minimo = min((float) $filas->min($valueKey), 0);
    $rango = max($maximo - $minimo, 1);
    $cero = abs($minimo) / $rango * 100;
@endphp
<div class="space-y-5" role="list" aria-label="Valores del gráfico">
    @forelse($filas as $fila)
        @php
            $valor = (float) $fila[$valueKey];
            $ancho = abs($valor) / $rango * 100;
            $inicio = $valor < 0 ? $cero - $ancho : $cero;
        @endphp
        <div role="listitem">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                <span class="min-w-0 break-words font-semibold text-brand-ink">{{ $fila[$labelKey] }}</span>
                <span class="font-bold text-brand-ink tabular-nums">{{ $unit }} {{ number_format($valor, $unit === 'Bs' ? 2 : 0) }}
                    @if($total !== null && $total > 0)<span class="font-normal text-slate-500"> · {{ number_format($valor / $total * 100, 1) }}%</span>@endif
                </span>
            </div>
            <div class="relative h-7 overflow-hidden rounded-md bg-slate-100" aria-hidden="true">
                <div class="absolute inset-y-0" style="left: {{ $inicio }}%; width: {{ $ancho }}%; background-color: {{ $valor < 0 ? '#e30613' : ($fila['color'] ?? $color) }}"></div>
                @if($minimo < 0)<div class="absolute inset-y-0 w-px bg-slate-500" style="left: {{ $cero }}%"></div>@endif
            </div>
            @if($secondaryKey)
                <div class="mt-1 h-2 rounded-sm bg-amber-100" aria-hidden="true">
                    <div class="h-full bg-amber-500" style="width: {{ (float) $fila[$secondaryKey] / $rango * 100 }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-500">{{ $secondaryLabel }}: {{ $secondaryUnit }} {{ number_format($fila[$secondaryKey], $secondaryUnit === 'Bs' ? 2 : 0) }}</p>
            @endif
            @if(isset($fila['detalle']))<p class="mt-1 text-xs text-slate-500">{{ $fila['detalle'] }}</p>@endif
        </div>
    @empty
        <div class="grid min-h-48 place-items-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">{{ $empty }}</div>
    @endforelse
</div>
@if($filas->isNotEmpty())
    <div class="mt-4 flex justify-between border-t border-slate-100 pt-2 text-xs text-slate-500 tabular-nums" aria-hidden="true">
        <span>{{ number_format($minimo, $unit === 'Bs' ? 2 : 0) }}</span>
        <span>Escala: {{ $unit ?: 'cantidad' }}</span>
        <span>{{ number_format($maximo, $unit === 'Bs' ? 2 : 0) }}</span>
    </div>
@endif
