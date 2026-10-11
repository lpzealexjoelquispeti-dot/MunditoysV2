<div class="mx-auto max-w-6xl space-y-6">
    <!-- ENCABEZADO -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Mis direcciones</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $totalDirecciones }} de {{ $limiteMaximo }} direcciones registradas</p>
        </div>

        @if ($puedeAgregar)
            <button wire:click="abrirModalCrear" class="inline-flex items-center gap-2 self-start rounded-full bg-brand-orange px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600 sm:self-auto">
                <x-lucide-plus /> Nueva dirección
            </button>
        @else
            <p class="inline-flex items-center gap-2 self-start rounded-full bg-amber-50 px-4 py-2 text-sm text-amber-800 sm:self-auto">
                <x-lucide-lock /> Elimina una para agregar otra
            </p>
        @endif
    </div>

    @if ($mensajeFeedback)
        <div @class(['flex items-center justify-between rounded-2xl p-4', 'bg-red-50 text-red-700' => $tipoFeedback === 'error', 'bg-emerald-50 text-emerald-800' => $tipoFeedback !== 'error'])>
            <p class="flex items-center gap-2 text-sm font-semibold">
                <x-dynamic-component :component="$tipoFeedback === 'error' ? 'lucide-triangle-alert' : 'lucide-circle-check'" />
                {{ $mensajeFeedback }}
            </p>
            <button wire:click="$set('mensajeFeedback', null)" class="hover:opacity-75" aria-label="Cerrar aviso"><x-lucide-x /></button>
        </div>
    @endif

    <!-- DIRECCIONES -->
    @if ($direcciones->isEmpty())
        <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white px-6 py-14 text-center">
            <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-orange-50 text-3xl text-brand-orange">
                <x-lucide-map-pin />
            </div>
            <p class="mt-4 font-semibold text-slate-800">Aún no tienes direcciones</p>
            <button wire:click="abrirModalCrear" class="mt-5 inline-flex items-center gap-2 rounded-full bg-brand-blue px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-blue-dark">
                <x-lucide-plus /> Agregar dirección
            </button>
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($direcciones as $dir)
                @php
                    $referencia = mb_strtolower($dir->nombre_referencia ?? '');
                    $iconoDireccion = str_contains($referencia, 'casa') ? 'lucide-house' : (str_contains($referencia, 'oficina') || str_contains($referencia, 'trabajo') ? 'lucide-building-2' : 'lucide-map-pin');
                @endphp
                <div @class(['flex flex-col justify-between rounded-3xl border bg-white p-5 transition', 'border-brand-orange/60 ring-2 ring-brand-orange/15' => $dir->principal, 'border-slate-200 hover:border-slate-300' => ! $dir->principal])>
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span @class(['grid size-10 place-items-center rounded-2xl text-lg', 'bg-brand-orange text-white' => $dir->principal, 'bg-slate-100 text-slate-600' => ! $dir->principal])>
                                    <x-dynamic-component :component="$iconoDireccion" />
                                </span>
                                <div>
                                    <h3 class="font-semibold leading-tight text-slate-900">{{ $dir->nombre_referencia ?: 'Lugar de entrega' }}</h3>
                                    <p class="text-sm text-slate-500">{{ $dir->zona ? $dir->zona.', ' : '' }}{{ $dir->ciudad ?? 'La Paz' }}</p>
                                </div>
                            </div>
                            @if ($dir->principal)
                                <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-brand-orange">
                                    <x-lucide-star class="fill-current" /> Principal
                                </span>
                            @endif
                        </div>

                        <p class="mt-4 text-sm text-slate-700">{{ $dir->direccion_completa }}</p>
                        @if (! empty($dir->ubicacion_jsonb['detalles']))
                            <p class="mt-1 text-sm text-slate-500">{{ $dir->ubicacion_jsonb['detalles'] }}</p>
                        @endif
                    </div>

                    <div class="mt-5 flex items-center justify-between gap-2 border-t border-slate-100 pt-4">
                        @if (! $dir->principal)
                            <button wire:click="marcarPrincipal({{ $dir->id }})" class="text-sm font-semibold text-brand-blue transition hover:text-brand-orange">
                                Hacer principal
                            </button>
                        @else
                            <span></span>
                        @endif

                        <div class="flex items-center gap-1">
                            <button wire:click="abrirModalEditar({{ $dir->id }})" class="grid size-9 place-items-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-800" aria-label="Editar" title="Editar">
                                <x-lucide-pencil />
                            </button>
                            <button wire:click="confirmarEliminar({{ $dir->id }})" class="grid size-9 place-items-center rounded-xl text-slate-500 transition hover:bg-red-50 hover:text-red-600" aria-label="Eliminar" title="Eliminar">
                                <x-lucide-trash-2 />
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- MODAL DE CREACIÓN / EDICIÓN -->
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto">
            <div class="relative w-full max-w-2xl rounded-3xl bg-white shadow-2xl overflow-hidden my-8">
                <!-- HEADER MODAL -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-lg font-bold text-slate-900">{{ $modoEdicion ? 'Editar dirección' : 'Nueva dirección' }}</h3>
                    <button wire:click="cerrarModal" class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar">
                        <x-lucide-x />
                    </button>
                </div>

                <!-- CUERPO MODAL -->
                <form wire:submit="guardar" class="p-6 space-y-5" novalidate>
                    <!-- DATOS BÁSICOS -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Nombre <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="nombreReferencia"
                                type="text"
                                placeholder="Casa, oficina…"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('nombreReferencia') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('nombreReferencia') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Zona <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="zona"
                                type="text"
                                placeholder="Sopocachi"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('zona') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('zona') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700">
                                Dirección <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="direccionCompleta"
                                type="text"
                                placeholder="Av. 6 de Agosto #2450, dpto. 4B"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('direccionCompleta') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('direccionCompleta') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                            @if ($direccionSugerida !== '' && $direccionCompleta === $direccionSugerida)
                                <p class="mt-1 text-xs text-brand-blue">Agrega tu número de puerta.</p>
                            @endif
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-semibold text-slate-700">
                                Referencia <span class="font-normal text-slate-400">(opcional)</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="detallesAdicionales"
                                type="text"
                                placeholder="Portón café, timbre blanco"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('detallesAdicionales') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- MAPA INTERACTIVO Y GEOLOCALIZACIÓN -->
                    <div
                        x-data="window.mundiToysDireccionMap($wire, { latitud: '{{ $latitud }}', longitud: '{{ $longitud }}' })"
                        class="rounded-2xl border border-orange-100 bg-orange-50/40 p-4 space-y-3"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-700">Marca el punto en el mapa</p>

                            <!-- BOTÓN SOLICITAR PERMISO / GPS -->
                            <button
                                type="button"
                                @click="usarUbicacionActual()"
                                :disabled="obteniendoGps"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-xs font-semibold text-brand-blue border border-blue-200 shadow-xs hover:bg-blue-50 transition shrink-0"
                            >
                                <span x-show="!obteniendoGps"><x-lucide-map-pin /></span>
                                <span x-show="obteniendoGps" class="animate-spin text-brand-orange"><x-lucide-loader-circle /></span>
                                <span x-text="obteniendoGps ? 'Buscando…' : 'Mi ubicación'"></span>
                            </button>
                        </div>

                        <!-- CONTENEDOR MAPA LEAFLET -->
                        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            <div x-ref="mapaDireccion" class="h-64 w-full z-10" wire:ignore></div>

                            <div x-show="cargando" class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 backdrop-blur-xs">
                                <span class="text-xs font-semibold text-slate-600 animate-pulse">Cargando mapa…</span>
                            </div>
                        </div>

                        <p wire:loading.delay wire:target="seleccionarUbicacion" class="text-xs font-semibold text-brand-blue animate-pulse">
                            Buscando la dirección…
                        </p>

                        <!-- ERROR DEL MAPA / VALIDACIÓN FUERA DE LA PAZ -->
                        <div x-show="error" x-transition class="rounded-xl bg-red-50 p-2.5 text-xs text-red-700 font-semibold border border-red-200">
                            <span x-text="error"></span>
                        </div>

                        @error('latitud')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('longitud')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror                    </div>

                    <!-- CHECKBOX PRINCIPAL -->
                    <div class="flex items-center gap-2.5 pt-1">
                        <input
                            wire:model.live="principal"
                            id="chk-principal"
                            type="checkbox"
                            class="size-4.5 rounded border-slate-300 text-brand-orange focus:ring-brand-orange"
                        >
                        <label for="chk-principal" class="text-xs font-semibold text-slate-700 cursor-pointer">
                            Usar como principal
                        </label>
                    </div>

                    <!-- BOTONES MODAL -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="cerrarModal"
                            class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardar"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-orange px-6 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-orange-600 transition"
                        >
                            <span wire:loading.remove wire:target="guardar">Guardar</span>
                            <span wire:loading wire:target="guardar">Guardando…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- MODAL CONFIRMAR ELIMINAR -->
    @if ($mostrarModalEliminar)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl text-center space-y-4">
                <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-red-100 text-3xl text-red-600">
                    <x-lucide-trash-2 />
                </div>
                <h3 class="text-lg font-bold text-slate-900">¿Eliminar esta dirección?</h3>

                <div class="flex items-center justify-center gap-3 pt-2">
                    <button
                        wire:click="cancelarEliminar"
                        class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="eliminar"
                        class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 transition"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

