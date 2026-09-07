<x-admin-modal property="mostrarDescuento" close="cerrarDescuento" title="Descontar existencias de inventario (FIFO)">
    <form wire:submit="guardarDescuento">
        <div class="mb-4 rounded-xl border border-orange-200 bg-orange-50 p-4 text-xs text-orange-950 leading-relaxed">
            <p class="font-bold mb-1">Estrategia FIFO (First-In, First-Out):</p>
            Las unidades solicitadas se descontarán automáticamente comenzando por el <strong>lote más antiguo</strong> que posea existencias activas. Si la cantidad supera las existencias de ese lote, se completará con el siguiente lote en orden cronológico.
        </div>

        <div class="space-y-4">
            <x-admin-field label="Cantidad de unidades a descontar *" name="cantidadDescuento" hint="Cantidad entera a retirar del stock disponible.">
                <input wire:model.blur="cantidadDescuento" type="number" min="1" max="1000000" step="1" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </x-admin-field>

            <x-admin-field label="Motivo de la salida *" name="motivoDescuento" hint="Indica la razón de la salida de stock.">
                <select wire:model.blur="motivoDescuento" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm">
                    <option value="Salida para exhibición en vitrina">Salida para exhibición en vitrina</option>
                    <option value="Muestra para demostración / cliente">Muestra para demostración / cliente</option>
                    <option value="Producto dañado / merma de almacén">Producto dañado / merma de almacén</option>
                    <option value="Ajuste por inventario físico">Ajuste por inventario físico</option>
                    <option value="Uso interno operativo">Uso interno operativo</option>
                </select>
            </x-admin-field>
        </div>

        <footer class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-white pt-4">
            <button type="button" wire:click="cerrarDescuento" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancelar</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="guardarDescuento" class="min-h-11 rounded-xl bg-brand-orange px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:opacity-90 disabled:opacity-50">
                <span wire:loading.remove wire:target="guardarDescuento">Confirmar salida FIFO</span>
                <span wire:loading wire:target="guardarDescuento">Procesando…</span>
            </button>
        </footer>
    </form>
</x-admin-modal>

