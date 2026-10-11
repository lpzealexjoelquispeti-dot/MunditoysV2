<x-admin-modal property="modalConfirmarAbierto" close="cerrarModalConfirmar" title="Confirmar Entrega en Domicilio">
    <form wire:submit="guardarConfirmacionEntrega" class="space-y-5">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4 text-xs text-emerald-900">
            <div class="flex items-center gap-2 font-semibold text-emerald-800 text-sm">
                <span><x-lucide-check /></span>
                <span>Cierre de Ciclo de Entrega</span>
            </div>
            <p class="mt-1 leading-relaxed">
                Al confirmar la entrega, el estado del despacho pasará a <strong>Entregado</strong>, registrando la fecha y hora exacta del cumplimiento en el sistema.
            </p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-700">
                Observaciones de Recepción (Opcional)
            </label>
            <textarea
                wire:model="observacionesEntrega"
                rows="3"
                maxlength="300"
                placeholder="Ej. Recibido por hermano con CI terminado en 456, paquete en perfecto estado..."
                class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-brand-ink placeholder:text-slate-400 focus:border-emerald-600 focus:ring-1 focus:ring-emerald-600"
            ></textarea>
            @error('observacionesEntrega') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <button
                type="button"
                wire:click="cerrarModalConfirmar"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-brand-ink shadow-2xs hover:bg-slate-50 transition"
            >
                Volver
            </button>
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 transition"
            >
                <span><x-lucide-check /></span>
                <span>Confirmar Entrega Exitosa</span>
            </button>
        </div>
    </form>
</x-admin-modal>
