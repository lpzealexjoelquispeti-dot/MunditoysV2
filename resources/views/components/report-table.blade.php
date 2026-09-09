@props(['columns', 'rows', 'money' => [], 'percent' => [], 'caption' => 'Detalle del reporte'])
<div class="overflow-x-auto">
    <table class="w-full text-left text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead class="border-y border-slate-200 bg-slate-50 text-xs font-semibold text-slate-600">
            <tr>@foreach($columns as $key => $label)<th scope="col" class="whitespace-nowrap px-4 py-3">{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($rows as $row)
                <tr class="hover:bg-orange-50/40">
                    @foreach($columns as $key => $label)
                        @php($value = data_get($row, $key))
                        <td class="px-4 py-3 text-slate-700 tabular-nums">
                            @if($value === null) —
                            @elseif(in_array($key, $money)) Bs {{ number_format((float) $value, 2) }}
                            @elseif(in_array($key, $percent)) {{ number_format((float) $value, 1) }}%
                            @else {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="px-4 py-10 text-center text-slate-500">Sin registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
