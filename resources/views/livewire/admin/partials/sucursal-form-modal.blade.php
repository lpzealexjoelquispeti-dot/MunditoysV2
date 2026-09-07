<x-admin-modal property="mostrarFormulario" close="cerrarFormulario" :title="$registroId ? 'Editar sucursal' : 'Registrar sucursal'" :wide="true">
    <form wire:submit="guardar" novalidate class="space-y-5">
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <x-admin-field label="Nombre *" name="nombre" hint="Entre 3 y 100 caracteres; letras, números y espacios."><input wire:model.live.debounce.400ms="nombre" type="text" maxlength="100" autocomplete="organization" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <x-admin-field label="Dirección *" name="direccion" hint="Entre 8 y 300 caracteres. Ejemplo: Av. Camacho, N.º 123."><textarea wire:model.blur="direccion" rows="3" maxlength="300" autocomplete="street-address" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea></x-admin-field>
                <x-admin-field label="Zona *" name="zona" hint="Letras, números y espacios."><input wire:model.blur="zona" type="text" maxlength="100" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><p class="mb-2 text-sm font-semibold text-brand-ink">Ciudad</p><p class="rounded-xl bg-blue-50 px-3 py-3 text-sm text-brand-blue">La Paz, Bolivia</p></div>
                    <x-admin-field label="Teléfono *" name="telefono" hint="8 dígitos; +591 opcional."><input wire:model.blur="telefono" type="tel" maxlength="13" placeholder="71234567" autocomplete="tel" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                </div>
            </div>
            <section>
                <h3 class="text-sm font-semibold text-brand-ink">Ubicación de la sucursal *</h3>
                <p class="mb-3 mt-1 text-xs leading-5 text-slate-500">Haz clic en el mapa o arrastra el marcador. Solo se permiten puntos dentro del municipio de La Paz; El Alto no está incluido.</p>
                @if ($mostrarFormulario)
                    <div x-data="window.mundiToysSucursalMap($wire, @js(['latitud' => $latitud, 'longitud' => $longitud]))" wire:key="mapa-sucursal-{{ $registroId ?? 'nueva' }}">
                        <div wire:ignore x-ref="mapa" class="relative z-0 h-72 w-full rounded-2xl border border-blue-100 bg-blue-50 sm:h-80" role="region" aria-label="Mapa para seleccionar la ubicación en La Paz"></div>
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-3"><p x-show="cargando || enviando" class="text-xs text-brand-blue" role="status" x-text="cargando ? 'Cargando mapa…' : 'Validando ubicación…'"></p><button type="button" x-on:click="usarCentro()" :disabled="cargando || enviando" class="rounded-lg px-3 py-2 text-xs font-semibold text-brand-blue disabled:opacity-50">Usar centro del mapa</button><button type="button" x-on:click="centrar()" class="ml-auto rounded-lg px-3 py-2 text-xs font-semibold text-brand-blue">Centrar mapa</button></div>
                        <p x-show="error" x-text="error" role="alert" class="mt-2 text-sm text-brand-red"></p>
                    </div>
                @endif
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <x-admin-field label="Latitud" name="latitud"><input wire:model="latitud" readonly type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"></x-admin-field>
                    <x-admin-field label="Longitud" name="longitud"><input wire:model="longitud" readonly type="text" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm"></x-admin-field>
                </div>
                <p class="mt-3 text-xs leading-5 text-slate-500">Límite cartográfico de referencia: GeoBolivia (2015), distribuido por <a href="https://www.geoboundaries.org/api/current/gbOpen/BOL/ADM3/" target="_blank" rel="noopener" class="underline">geoBoundaries</a>. No constituye una certificación catastral.</p>
            </section>
        </div>
        @include('livewire.admin.partials.operacion-form-footer', ['singular' => 'sucursal', 'targetGuardar' => 'guardar,seleccionarUbicacion'])
    </form>
</x-admin-modal>
