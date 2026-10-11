<x-admin-modal property="modalCancelarAbierto" close="cerrarModalCancelar" title="Cancelación y Registro de Incidencia">
    <form wire:submit="guardarCancelacion" class="space-y-5">
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900">
            <div class="flex items-center gap-2 font-semibold text-amber-800 text-sm">
                <span><x-lucide-triangle-alert /></span>
                <span>Notificación Automática a Administradores</span>
            </div>
            <p class="mt-1 leading-relaxed">
                Por política de control de calidad, al cancelar este despacho se enviará una <strong>notificación de alerta inmediata a los Administradores</strong> indicando la venta afectada, fecha/hora y la justificación ingresada.
            </p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-semibold text-slate-700">
                Motivo Detallado de la Cancelación *
            </label>
            <textarea
                wire:model="motivoCancelacion"
                rows="4"
                maxlength="500"
                placeholder="Indica con claridad la razón por la cual no se puede cumplir la entrega (ej. Cliente ausente en el domicilio tras llamada telefónica, vía bloqueada por corte de tránsito, avería en motocicleta de reparto, etc.). Mínimo 10 caracteres..."
                class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-brand-ink placeholder:text-slate-400 focus:border-brand-red focus:ring-1 focus:ring-brand-red"
            ></textarea>
            <div class="mt-1 flex items-center justify-between text-xs text-slate-400">
                <span>Mínimo 10 caracteres obligatorios.</span>
                <span>{{ strlen($motivoCancelacion) }} / 500</span>
            </div>
            @error('motivoCancelacion') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <button
                type="button"
                wire:click="cerrarModalCancelar"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-brand-ink shadow-2xs hover:bg-slate-50 transition"
            >
                Volver
            </button>
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand-red px-5 py-2.5 text-xs font-semibold text-white shadow-2xs hover:bg-brand-red-dark transition"
            >
                <span><x-lucide-x /></span>
                <span>Registrar Cancelación y Notificar</span>
            </button>
        </div>
    </form>
</x-admin-modal>
