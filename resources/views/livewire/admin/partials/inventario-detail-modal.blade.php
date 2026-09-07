<x-admin-modal property="mostrarDetalle" close="cerrarDetalle" title="Lotes e historial del inventario" :wide="true">
    @if ($detalle)
        <h3 class="text-xl font-bold text-brand-ink">{{ $detalle['producto'] }}</h3><p class="mt-1 text-sm text-slate-500">{{ $detalle['sucursal'] }} · {{ $detalle['almacen'] }} · {{ $detalle['archivado'] ? 'Archivado' : 'Activo' }}</p>
        <div class="my-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-3">
                <p class="rounded-xl bg-blue-50 px-4 py-3 text-sm text-brand-blue">Existencias: <strong>{{ $detalle['stock'] }}</strong></p>
                <p class="rounded-xl bg-orange-50 px-4 py-3 text-sm text-brand-orange">Mínimo: <strong>{{ $detalle['stock_minimo'] }}</strong></p>
            </div>
            @if (!$detalle['archivado'] && $detalle['stock'] > 0)
                <button type="button" wire:click="abrirDescuento({{ $detalle['id'] }})" class="rounded-xl bg-brand-orange px-4 py-3 text-xs font-bold text-white shadow-sm hover:opacity-90">
                    📤 Descontar existencias (FIFO / Exhibición)
                </button>
            @endif
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="p-3">Lote / ingreso</th>
                        <th class="p-3">Proveedor</th>
                        <th class="p-3">Inicial / disponible</th>
                        <th class="p-3">Costo Bs</th>
                        <th class="p-3">Margen</th>
                        <th class="p-3">Venta Bs</th>
                        <th class="p-3">Vencimiento</th>
                        <th class="p-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($detalle['lotes'] as $lote)
                        <tr wire:key="detalle-lote-{{ $lote['id'] }}" class="hover:bg-slate-50/60">
                            <td class="p-3"><strong class="break-all">{{ $lote['codigo'] ?? 'Lote histórico' }}</strong><span class="mt-1 block text-slate-500">{{ $lote['fecha'] }} · {{ $lote['estado'] }}</span></td>
                            <td class="p-3">{{ $lote['proveedor'] }}</td>
                            <td class="p-3"><span class="font-semibold">{{ $lote['disponible'] }}</span> / {{ $lote['inicial'] }}</td>
                            <td class="p-3">{{ $lote['costo'] }}</td>
                            <td class="p-3">{{ $lote['margen'] !== null ? $lote['margen'].' %' : 'Sin asignar' }}</td>
                            <td class="p-3 font-bold text-brand-ink">{{ $lote['precio'] ?? 'Sin asignar' }}</td>
                            <td class="p-3">{{ $lote['vencimiento'] ?? 'No corresponde' }}</td>
                            <td class="p-3 text-right">
                                @if (!$detalle['archivado'])
                                    <button type="button" wire:click="abrirEdicionLote({{ $lote['id'] }})" class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-semibold text-slate-700 hover:bg-slate-200">
                                        ✏️ Editar
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-6 text-center text-slate-500">Este inventario todavía no tiene lotes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <h3 class="mb-3 mt-6 font-bold text-brand-ink">Movimientos registrados</h3>
        <div class="space-y-2">
            @php $hayMovimientos = false; @endphp
            @foreach ($detalle['lotes'] as $lote)
                @foreach ($lote['movimientos'] as $indice => $movimiento)
                    @php $hayMovimientos = true; @endphp
                    <div wire:key="mov-{{ $lote['id'] }}-{{ $indice }}" class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs leading-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                @if ($movimiento['tipo'] === 'entrada')
                                    <span class="rounded-md bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800">📥 Entrada</span>
                                @elseif ($movimiento['tipo'] === 'salida')
                                    <span class="rounded-md bg-rose-100 px-2 py-0.5 font-bold text-rose-800">📤 Salida</span>
                                @elseif ($movimiento['tipo'] === 'ajuste_precio')
                                    <span class="rounded-md bg-purple-100 px-2 py-0.5 font-bold text-purple-800">🏷️ Ajuste de Precio</span>
                                @else
                                    <span class="rounded-md bg-amber-100 px-2 py-0.5 font-bold text-amber-800">✏️ Ajuste</span>
                                @endif
                                <strong class="ml-1.5 text-brand-ink">{{ $movimiento['cantidad'] }} unidades</strong> · {{ $lote['codigo'] }}
                            </div>
                            <span class="text-[11px] text-slate-500">{{ $movimiento['fecha'] }}</span>
                        </div>
                        @if (!empty($movimiento['motivo']))
                            <p class="mt-1 font-semibold text-slate-700">{{ $movimiento['motivo'] }}</p>
                        @endif
                        <p class="mt-0.5 text-slate-500">De {{ $movimiento['anterior'] }} a {{ $movimiento['nueva'] }} · Registrado por: {{ $movimiento['usuario'] }}</p>
                    </div>
                @endforeach
            @endforeach
            @if (!$hayMovimientos)
                <p class="p-3 text-center text-xs text-slate-500">No hay movimientos registrados.</p>
            @endif
        </div>
        @if ($detalle['historial'])<h3 class="mb-3 mt-6 font-bold text-brand-ink">Historial de archivado y restauración</h3><div class="space-y-2">@foreach ($detalle['historial'] as $indice => $evento)<p wire:key="historial-{{ $indice }}" class="rounded-xl bg-blue-50 p-3 text-xs leading-5"><strong>{{ ucfirst($evento['accion']) }}</strong> · {{ $evento['fecha'] }} · Usuario #{{ $evento['usuario_id'] }}<br>{{ $evento['motivo'] }}</p>@endforeach</div>@endif
    @endif
</x-admin-modal>
