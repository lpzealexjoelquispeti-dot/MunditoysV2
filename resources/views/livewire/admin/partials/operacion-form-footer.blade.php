@if ($registroId)
    <x-admin-field label="Estado *" name="estado"><select wire:model.live="estado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="1">Activo</option><option value="0">Inactivo</option></select></x-admin-field>
@else
    <p class="rounded-xl bg-blue-50 p-3 text-sm text-brand-blue">Se creará activo automáticamente.</p>
@endif
<footer class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-4">
    <button type="button" wire:click="cerrarFormulario" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancelar</button>
    <button type="submit" wire:loading.attr="disabled" wire:target="{{ $targetGuardar ?? 'guardar' }}" class="min-h-11 rounded-xl bg-brand-red px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="{{ $targetGuardar ?? 'guardar' }}">Guardar {{ $singular }}</span><span wire:loading wire:target="{{ $targetGuardar ?? 'guardar' }}">Procesando…</span></button>
</footer>
