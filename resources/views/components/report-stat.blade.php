@props(['label', 'value', 'unit' => '', 'hint' => null, 'change' => null])
<div class="min-w-0 rounded-2xl border border-orange-100 bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
    <p class="mt-3 break-words text-2xl font-semibold tracking-tight text-brand-ink tabular-nums sm:text-3xl">
        @if($value === null) Sin datos @else {{ $value }} @if($unit)<span class="text-base font-medium text-slate-500">{{ $unit }}</span>@endif @endif
    </p>
    @if($hint)<p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $hint }}</p>@endif
    @if($change)
        <p @class(['mt-3 text-xs font-semibold tabular-nums', 'text-emerald-700' => $change['diferencia'] > 0, 'text-brand-red' => $change['diferencia'] < 0, 'text-slate-500' => $change['diferencia'] == 0])>
            @if($change['porcentaje'] === null)
                {{ $change['diferencia'] == 0 ? 'Sin actividad en ambos períodos' : 'Sin base porcentual anterior' }}
            @else
                {{ $change['porcentaje'] > 0 ? '+' : '' }}{{ number_format($change['porcentaje'], 1) }}% frente al período anterior
            @endif
        </p>
    @endif
</div>
