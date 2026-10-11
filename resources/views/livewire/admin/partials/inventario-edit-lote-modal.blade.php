<x-admin-modal property="mostrarEdicionLote" close="cerrarEdicionLote" title="Editar lote de inventario" :wide="true">
    <form wire:submit="guardarEdicionLote">
        <div class="mb-4 rounded-xl bg-blue-50 p-3 text-xs text-brand-blue">
            Editando lote: <strong>{{ $codigoLoteEdicion }}</strong>. Los cambios de costos, márgenes y cantidades registrarán un movimiento de ajuste para mantener la auditoría.
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <x-admin-field label="Cantidad inicial *" name="cantidadInicialEdicion">
                <input wire:model.live.debounce.300ms="cantidadInicialEdicion" type="number" min="1" max="1000000" step="1" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <x-admin-field label="Cantidad disponible *" name="cantidadDisponibleEdicion" :hint="$unidadesSalidasEdicion > 0 ? 'Calculado automáticamente: Inicial menos '.$unidadesSalidasEdicion.' salida(s) por ventas, exhibición o daño.' : 'Calculado automáticamente: Sin salidas previas registradas; coincide con la cantidad inicial.'">
                <input wire:model.blur="cantidadDisponibleEdicion" type="number" min="0" max="1000000" step="1" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <div class="mt-1.5 flex items-center justify-between text-xs">
                    <span class="text-slate-500">
                        @if ($unidadesSalidasEdicion > 0)
                            <span class="font-medium text-brand-orange">{{ $unidadesSalidasEdicion }} salida(s) registrada(s)</span>
                        @else
                            <span class="font-medium text-emerald-600">0 salidas registradas</span>
                        @endif
                    </span>
                    <button type="button" wire:click="recalcularDisponibleEdicion" title="Recalcular existencias según cantidad inicial y salidas" class="font-semibold text-brand-blue hover:underline">
                        <x-lucide-refresh-cw /> Autocompletar
                    </button>
                </div>
            </x-admin-field>

            <x-admin-field label="Fecha de ingreso *" name="fechaIngresoEdicion">
                <input wire:model.blur="fechaIngresoEdicion" type="date" max="{{ now()->format('Y-m-d') }}" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <x-admin-field label="Costo unitario (Bs) *" name="costoUnitarioEdicion">
                <input wire:model.live.debounce.450ms="costoUnitarioEdicion" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <x-admin-field label="Margen sobre venta (%) *" name="margenEdicion">
                <input wire:model.live.debounce.450ms="margenEdicion" type="number" min="0.01" max="99.99" step="0.01" inputmode="decimal" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <div class="rounded-2xl border border-orange-100 bg-orange-50 p-4" aria-live="polite">
                <p class="text-xs font-semibold text-brand-orange">Precio de venta recalculado</p>
                <p class="mt-2 text-2xl font-semibold text-brand-ink">{{ $precioCalculadoEdicion ? 'Bs '.$precioCalculadoEdicion['precio_venta'] : 'Por calcular' }}</p>
                @if ($precioCalculadoEdicion)
                    <p class="mt-1 text-xs text-slate-600">Ganancia bruta: Bs {{ $precioCalculadoEdicion['ganancia_unitaria'] }}/unidad</p>
                @endif
            </div>

            <x-admin-field label="Proveedor (opcional)" name="proveedorIdEdicion">
                <select wire:model.blur="proveedorIdEdicion" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="">Sin proveedor</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor['id'] }}">{{ $proveedor['empresa'] }}</option>
                    @endforeach
                </select>
            </x-admin-field>

            <x-admin-field label="Vencimiento (si corresponde)" name="fechaVencimientoEdicion">
                <input wire:model.blur="fechaVencimientoEdicion" type="date" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <div class="lg:col-span-3">
                <x-admin-field label="Motivo de la corrección *" name="motivoEdicion" hint="Explica por qué se ajustan los datos o existencias del lote.">
                    <input wire:model.blur="motivoEdicion" type="text" maxlength="255" placeholder="Ej: Corrección de costo por factura rectificada" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                </x-admin-field>
            </div>
        </div>

        <footer class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-white pt-4">
            <button type="button" wire:click="cerrarEdicionLote" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancelar</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="guardarEdicionLote" class="min-h-11 rounded-xl bg-brand-blue px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                <span wire:loading.remove wire:target="guardarEdicionLote">Guardar cambios</span>
                <span wire:loading wire:target="guardarEdicionLote">Guardando…</span>
            </button>
        </footer>
    </form>
</x-admin-modal>

