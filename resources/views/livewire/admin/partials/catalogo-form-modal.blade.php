<x-admin-modal property="mostrarFormulario" close="cerrarFormulario" :title="($registroId ? 'Editar ' : 'Nueva ').$singular">
    <form wire:submit="guardar" class="space-y-4" x-data="{ subiendo: false }" x-on:livewire-upload-start="subiendo = true" x-on:livewire-upload-finish="subiendo = false" x-on:livewire-upload-error="subiendo = false" x-on:livewire-upload-cancel="subiendo = false">
        <x-admin-field label="Nombre *" name="nombre"><input wire:model.live.debounce.400ms="nombre" type="text" maxlength="100" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
        <x-admin-field label="Descripción *" name="descripcion" hint="Entre 10 y 1.000 caracteres."><textarea wire:model.blur="descripcion" rows="3" maxlength="1000" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea></x-admin-field>
        @php($imagenCargada = $imagen instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
        <x-admin-field label="Imagen *" name="imagen" hint="JPG, PNG o WEBP; máximo 3 MB."><input x-ref="imagen" wire:model="imagen" type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border-2 border-dashed border-orange-200 bg-orange-50 p-3 text-sm" wire:key="imagen-{{ $registroId ?? 'nueva' }}"></x-admin-field>
        <p x-show="subiendo" role="status" class="text-sm text-brand-blue">Subiendo y validando imagen…</p>
        @if ($imagenCargada && !$errors->has('imagen') && $imagen->isPreviewable())
            <div class="rounded-2xl bg-slate-50 p-3"><img src="{{ $imagen->temporaryUrl() }}" alt="Vista previa de la imagen seleccionada" class="mx-auto h-36 object-contain"><button type="button" x-on:click="$refs.imagen.value = ''; subiendo = false" wire:click="quitarImagenNueva" class="mt-2 w-full text-sm font-semibold text-brand-red">Quitar selección</button></div>
        @elseif ($imagenActual)
            <img src="{{ asset('storage/'.$imagenActual) }}" alt="Imagen actual" class="mx-auto h-36 rounded-xl object-contain">
        @endif
        @if ($registroId)
            <x-admin-field label="Estado *" name="estado"><select wire:model.live="estado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="1">Activa</option><option value="0">Inactiva</option></select></x-admin-field>
        @else<p class="rounded-xl bg-blue-50 p-3 text-sm text-brand-blue">Se creará activa automáticamente.</p>@endif
        <footer class="flex justify-end gap-3 border-t border-slate-100 bg-white pt-4"><button type="button" wire:click="cerrarFormulario" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancelar</button><button type="submit" :disabled="subiendo" wire:loading.attr="disabled" wire:target="guardar,imagen" class="min-h-11 rounded-xl bg-brand-red px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Guardar {{ $singular }}</button></footer>
    </form>
</x-admin-modal>
