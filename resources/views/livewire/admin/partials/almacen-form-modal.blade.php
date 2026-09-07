<x-admin-modal property="mostrarFormulario" close="cerrarFormulario" :title="$registroId ? 'Editar almacén' : 'Registrar almacén'">
    <form wire:submit="guardar" novalidate class="space-y-4">
        <x-admin-field label="Nombre *" name="nombre" hint="De 3 a 100 caracteres. Letras, números y espacios; ejemplo: Almacén 1."><input wire:model.live.debounce.400ms="nombre" type="text" maxlength="100" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
        <x-admin-field label="Sucursal *" name="sucursal_id" hint="Un almacén con historial de inventario no puede cambiar de sucursal."><select wire:model.live="sucursal_id" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Selecciona una sucursal</option>@foreach ($sucursales as $sucursal)<option value="{{ $sucursal['id'] }}" wire:key="opcion-sucursal-{{ $sucursal['id'] }}">{{ $sucursal['nombre'] }}{{ $sucursal['estado'] ? '' : ' (inactiva)' }}</option>@endforeach</select></x-admin-field>
        @if (!$sucursales)<p class="rounded-xl bg-orange-50 p-3 text-sm text-orange-800">Primero registra una sucursal activa.</p>@endif
        <x-admin-field label="Tipo *" name="tipo"><select wire:model.live="tipo" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="principal">Principal</option><option value="secundario">Secundario</option></select></x-admin-field>
        <p class="text-xs leading-5 text-slate-500">Dar de baja un almacén no elimina sus lotes. Si tiene existencias, primero deben registrarse sus salidas o traslados.</p>
        @include('livewire.admin.partials.operacion-form-footer', ['singular' => 'almacén'])
    </form>
</x-admin-modal>
