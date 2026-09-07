<x-admin-modal property="mostrarFormulario" close="cerrarFormulario" :title="$registroId ? 'Editar proveedor' : 'Registrar proveedor'">
    <form wire:submit="guardar" novalidate class="space-y-4">
        <x-admin-field label="Empresa o razón social *" name="empresa" hint="De 3 a 150 caracteres."><input wire:model.live.debounce.400ms="empresa" type="text" maxlength="150" autocomplete="organization" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
        <x-admin-field label="Nombre del contacto *" name="nombre_contacto" hint="Solo letras y espacios."><input wire:model.blur="nombre_contacto" type="text" maxlength="120" autocomplete="name" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin-field label="Teléfono *" name="telefono" hint="8 dígitos; +591 opcional."><input wire:model.blur="telefono" type="tel" maxlength="13" autocomplete="tel" placeholder="71234567" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
            <x-admin-field label="Correo electrónico (opcional)" name="email"><input wire:model.blur="email" type="email" maxlength="254" autocomplete="email" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
        </div>
        <x-admin-field label="Dirección *" name="direccion" hint="De 8 a 300 caracteres; admite numeración y puntuación de direcciones."><textarea wire:model.blur="direccion" rows="2" maxlength="300" autocomplete="street-address" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></textarea></x-admin-field>
        <x-admin-field label="NIT (opcional)" name="nit" hint="Entre 5 y 20 dígitos, sin separadores. Validación de formato; no verifica el registro ante Impuestos."><input wire:model.live.debounce.400ms="nit" type="text" inputmode="numeric" maxlength="20" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"></x-admin-field>
        <p class="text-xs leading-5 text-slate-500">El proveedor se selecciona al registrar cada lote, no como proveedor único del juguete. Su baja conserva las compras históricas.</p>
        @include('livewire.admin.partials.operacion-form-footer', ['singular' => 'proveedor'])
    </form>
</x-admin-modal>
