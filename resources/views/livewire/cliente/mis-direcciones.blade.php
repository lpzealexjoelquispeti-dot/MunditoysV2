<div class="mx-auto max-w-6xl">
    <!-- ENCABEZADO -->
    <div class="rounded-[2rem] bg-gradient-to-r from-brand-blue via-brand-blue to-blue-800 p-6 sm:p-10 text-white shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-orange-300 backdrop-blur-sm">
                    <span>📍</span> Mis Direcciones de Entrega
                </div>
                <h1 class="mt-3 text-3xl sm:text-4xl font-black tracking-tight">Lugares de Entrega</h1>
                <p class="mt-2 max-w-xl text-sm text-blue-100 leading-relaxed">
                    Registra hasta 3 direcciones en la ciudad de La Paz donde quieras que lleguen tus juguetes favoritos. Si deseas añadir una nueva dirección y alcanzaste el límite, debes eliminar una antigua para evitar confusiones.
                </p>
            </div>

            <!-- CONTADOR DE DIRECCIONES (MÁXIMO 3) -->
            <div class="flex flex-col items-center sm:items-end gap-3 rounded-2xl bg-white/10 p-5 backdrop-blur-md border border-white/15">
                <span class="text-xs font-semibold text-blue-100">Espacios de entrega utilizados:</span>
                <div class="flex items-center gap-2">
                    @for ($i = 1; $i <= $limiteMaximo; $i++)
                        <div class="size-9 rounded-xl flex items-center justify-center font-black text-sm transition-all {{ $i <= $totalDirecciones ? 'bg-brand-orange text-white shadow-md shadow-orange-950/20 scale-105' : 'bg-white/20 text-white/40 border border-white/20' }}">
                            {{ $i }}
                        </div>
                    @endfor
                </div>
                <span class="text-xs font-bold {{ $totalDirecciones >= $limiteMaximo ? 'text-amber-300' : 'text-emerald-300' }}">
                    {{ $totalDirecciones }} de {{ $limiteMaximo }} direcciones registradas
                </span>
            </div>
        </div>
    </div>

    <!-- FEEDBACK / ALERTAS -->
    @if ($mensajeFeedback)
        <div class="mt-6 flex items-center justify-between rounded-2xl p-4 {{ $tipoFeedback === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
            <div class="flex items-center gap-3">
                <span class="text-xl">{{ $tipoFeedback === 'error' ? '⚠️' : '✅' }}</span>
                <p class="text-sm font-semibold">{{ $mensajeFeedback }}</p>
            </div>
            <button wire:click="$set('mensajeFeedback', null)" class="text-xs font-bold hover:opacity-75">✕</button>
        </div>
    @endif

    <!-- ALERTA DE LÍMITE ALCANZADO -->
    @if (! $puedeAgregar)
        <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5 text-amber-900 shadow-xs flex items-start gap-3.5">
            <span class="text-2xl">💡</span>
            <div class="text-sm">
                <p class="font-bold">Límite máximo de 3 direcciones alcanzado</p>
                <p class="text-amber-800 mt-0.5">
                    Para registrar una nueva dirección de entrega, primero debes eliminar alguna de tus direcciones anteriores. De esta forma tus envíos llegarán siempre al lugar correcto sin confusiones.
                </p>
            </div>
        </div>
    @endif

    <!-- BARRA DE ACCIÓN SUPERIOR -->
    <div class="mt-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-black text-slate-800">Tus Direcciones Registradas</h2>
            <p class="text-xs text-slate-500">Selecciona tu dirección preferida para envíos rápidos de tus juguetes.</p>
        </div>

        <div>
            @if ($puedeAgregar)
                <button
                    wire:click="abrirModalCrear"
                    class="inline-flex items-center gap-2 rounded-full bg-brand-orange px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-orange-600 transition"
                >
                    <span>➕</span>
                    <span>Nueva Dirección</span>
                </button>
            @else
                <button
                    disabled
                    title="Debes eliminar una dirección para poder registrar otra."
                    class="inline-flex items-center gap-2 rounded-full bg-slate-200 px-5 py-2.5 text-sm font-bold text-slate-400 cursor-not-allowed"
                >
                    <span>🔒</span>
                    <span>Límite de 3 alcanzado</span>
                </button>
            @endif
        </div>
    </div>

    <!-- LISTADO DE DIRECCIONES EN TARJETAS -->
    <div class="mt-6">
        @if ($direcciones->isEmpty())
            <div class="rounded-3xl border-2 border-dashed border-slate-200 bg-white p-10 sm:p-14 text-center">
                <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-orange-100 text-4xl text-brand-orange shadow-inner">
                    📍
                </div>
                <h3 class="mt-5 text-xl font-black text-slate-800">Aún no tienes direcciones registradas</h3>
                <p class="mt-2 text-sm text-slate-500 max-w-md mx-auto">
                    Guarda los lugares donde recibes tus juguetes (tu casa, oficina, o casa de familiares en La Paz) para agilizar tus compras.
                </p>
                <div class="mt-6">
                    <button
                        wire:click="abrirModalCrear"
                        class="inline-flex items-center gap-2 rounded-full bg-brand-blue px-6 py-3 text-sm font-bold text-white shadow-md hover:bg-blue-700 transition"
                    >
                        <span>🏠</span>
                        <span>Registrar mi primera dirección</span>
                    </button>
                </div>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($direcciones as $dir)
                    <div class="group relative flex flex-col justify-between rounded-3xl border {{ $dir->principal ? 'border-brand-orange/60 bg-gradient-to-b from-orange-50/30 to-white shadow-md shadow-orange-500/5 ring-2 ring-brand-orange/20' : 'border-slate-200 bg-white hover:border-slate-300 shadow-xs' }} p-6 transition">
                        <!-- HEADER TARJETA -->
                        <div>
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="grid size-10 place-items-center rounded-2xl {{ $dir->principal ? 'bg-brand-orange text-white' : 'bg-slate-100 text-slate-700' }} text-lg shadow-xs">
                                        @if (str_contains(strtolower($dir->nombre_referencia ?? ''), 'casa'))
                                            🏠
                                        @elseif (str_contains(strtolower($dir->nombre_referencia ?? ''), 'oficina') || str_contains(strtolower($dir->nombre_referencia ?? ''), 'trabajo'))
                                            🏢
                                        @else
                                            📍
                                        @endif
                                    </span>
                                    <div>
                                        <h3 class="font-black text-slate-900 text-base leading-tight">
                                            {{ $dir->nombre_referencia ?: 'Lugar de entrega' }}
                                        </h3>
                                        <span class="text-xs font-semibold text-slate-500">
                                            {{ $dir->zona ? $dir->zona.', ' : '' }}{{ $dir->ciudad ?? 'La Paz' }}
                                        </span>
                                    </div>
                                </div>

                                @if ($dir->principal)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-orange px-2.5 py-1 text-[11px] font-bold text-white shadow-xs">
                                        <span>★</span> Principal
                                    </span>
                                @endif
                            </div>

                            <!-- CUERPO DE LA DIRECCIÓN -->
                            <div class="mt-4 space-y-2 text-xs text-slate-600 border-t border-slate-100 pt-3">
                                <p class="font-medium text-slate-800 leading-relaxed">
                                    <span class="font-bold text-slate-500">Dirección:</span> {{ $dir->direccion_completa }}
                                </p>

                                @if (! empty($dir->ubicacion_jsonb['detalles']))
                                    <p class="text-slate-500 italic bg-slate-50 p-2 rounded-xl">
                                        <span class="font-bold text-slate-600 not-italic">Referencia:</span> {{ $dir->ubicacion_jsonb['detalles'] }}
                                    </p>
                                @endif

                                <div class="inline-flex items-center gap-1 text-[11px] font-mono text-slate-400 pt-1">
                                    <span>🌐 Coordenadas:</span>
                                    <span>{{ number_format((float) $dir->latitud, 4) }}, {{ number_format((float) $dir->longitud, 4) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- ACCIONES TARJETA -->
                        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                            <div>
                                @if (! $dir->principal)
                                    <button
                                        wire:click="marcarPrincipal({{ $dir->id }})"
                                        class="text-xs font-bold text-brand-blue hover:text-brand-orange transition"
                                        title="Establecer como dirección principal de entrega"
                                    >
                                        Hacer principal
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-1">
                                <button
                                    wire:click="abrirModalEditar({{ $dir->id }})"
                                    class="inline-flex items-center gap-1 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition"
                                    title="Editar dirección"
                                >
                                    <span>✏️</span>
                                    <span>Editar</span>
                                </button>
                                <button
                                    wire:click="confirmarEliminar({{ $dir->id }})"
                                    class="inline-flex items-center gap-1 rounded-xl bg-red-50 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-100 transition"
                                    title="Eliminar dirección"
                                >
                                    <span>🗑️</span>
                                    <span>Eliminar</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- MODAL DE CREACIÓN / EDICIÓN -->
    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto">
            <div class="relative w-full max-w-2xl rounded-3xl bg-white shadow-2xl overflow-hidden my-8">
                <!-- HEADER MODAL -->
                <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-9 place-items-center rounded-xl bg-brand-orange text-white font-bold">
                            {{ $modoEdicion ? '✏️' : '📍' }}
                        </span>
                        <div>
                            <h3 class="text-base font-black text-slate-900">
                                {{ $modoEdicion ? 'Editar Dirección de Entrega' : 'Registrar Nueva Dirección' }}
                            </h3>
                            <p class="text-xs text-slate-500">MundiToys La Paz · Cobertura urbana municipal</p>
                        </div>
                    </div>
                    <button wire:click="cerrarModal" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200 hover:text-slate-700 transition">
                        ✕
                    </button>
                </div>

                <!-- CUERPO MODAL -->
                <form wire:submit="guardar" class="p-6 space-y-5" novalidate>
                    <!-- DATOS BÁSICOS -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Nombre de Referencia <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="nombreReferencia"
                                type="text"
                                placeholder="Ej.: Mi Casa, Oficina, Casa de Papá"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('nombreReferencia') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('nombreReferencia') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Zona o Barrio <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="zona"
                                type="text"
                                placeholder="Ej.: Sopocachi, Miraflores, Calacoto"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('zona') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('zona') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Dirección Completa y Número de Puerta <span class="text-red-500">*</span>
                            </label>
                            <input
                                wire:model.live.debounce.300ms="direccionCompleta"
                                type="text"
                                placeholder="Ej.: Av. 6 de Agosto #2450, Edif. Los Pinos, Dpto 4B"
                                class="mt-1.5 w-full rounded-xl border px-3.5 py-2.5 text-sm @error('direccionCompleta') border-red-400 bg-red-50 @else border-slate-200 @enderror focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('direccionCompleta') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Indicaciones para la Entrega (Opcional)
                            </label>
                            <input
                                wire:model.live.debounce.300ms="detallesAdicionales"
                                type="text"
                                placeholder="Ej.: Portón café frente a la plaza, tocar timbre blanco"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                            @error('detallesAdicionales') <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- MAPA INTERACTIVO Y GEOLOCALIZACIÓN -->
                    <div
                        x-data="window.mundiToysDireccionMap($wire, { latitud: '{{ $latitud }}', longitud: '{{ $longitud }}' })"
                        x-init="init()"
                        x-destroy="destruir()"
                        class="rounded-2xl border border-orange-100 bg-orange-50/40 p-4 space-y-3"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                                    <span>🗺️</span> Ubicación Exacta en La Paz
                                </h4>
                                <p class="text-[11px] text-slate-500">
                                    Haz clic en el mapa o arrastra el pin para fijar tu entrega.
                                </p>
                            </div>

                            <!-- BOTÓN SOLICITAR PERMISO / GPS -->
                            <button
                                type="button"
                                @click="usarUbicacionActual()"
                                :disabled="obteniendoGps"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3.5 py-2 text-xs font-bold text-brand-blue border border-blue-200 shadow-xs hover:bg-blue-50 transition shrink-0"
                            >
                                <span x-show="!obteniendoGps">📍</span>
                                <span x-show="obteniendoGps" class="animate-spin text-brand-orange">⚙️</span>
                                <span x-text="obteniendoGps ? 'Obteniendo GPS...' : 'Usar mi ubicación actual'"></span>
                            </button>
                        </div>

                        <!-- CONTENEDOR MAPA LEAFLET -->
                        <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                            <div x-ref="mapaDireccion" class="h-64 w-full z-10"></div>

                            <div x-show="cargando" class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 backdrop-blur-xs">
                                <span class="text-xs font-bold text-slate-600 animate-pulse">Cargando mapa de La Paz...</span>
                            </div>
                        </div>

                        <!-- ERROR DEL MAPA / VALIDACIÓN FUERA DE LA PAZ -->
                        <div x-show="error" x-transition class="rounded-xl bg-red-50 p-2.5 text-xs text-red-700 font-semibold border border-red-200">
                            <span x-text="error"></span>
                        </div>

                        @error('latitud')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('longitud')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror

                        <!-- VISUALIZADOR DE COORDENADAS -->
                        <div class="flex items-center justify-between text-[11px] text-slate-500">
                            <span>Ciudad: <strong>La Paz</strong></span>
                            <span>Coordenadas fijadas: <strong class="font-mono">{{ number_format((float) $latitud, 5) }}, {{ number_format((float) $longitud, 5) }}</strong></span>
                        </div>
                    </div>

                    <!-- CHECKBOX PRINCIPAL -->
                    <div class="flex items-center gap-2.5 pt-1">
                        <input
                            wire:model.live="principal"
                            id="chk-principal"
                            type="checkbox"
                            class="size-4.5 rounded border-slate-300 text-brand-orange focus:ring-brand-orange"
                        >
                        <label for="chk-principal" class="text-xs font-bold text-slate-700 cursor-pointer">
                            Establecer como mi dirección principal de entrega
                        </label>
                    </div>

                    <!-- BOTONES MODAL -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="cerrarModal"
                            class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:target="guardar"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-orange px-6 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-orange-600 transition"
                        >
                            <span wire:loading.remove wire:target="guardar">💾 Guardar Dirección</span>
                            <span wire:loading wire:target="guardar">Guardando...</span>
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
                    🗑️
                </div>
                <h3 class="text-lg font-black text-slate-900">¿Eliminar esta dirección?</h3>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Al eliminarla liberarás un espacio en tu cuenta para que puedas registrar una nueva dirección de entrega cuando lo necesites.
                </p>

                <div class="flex items-center justify-center gap-3 pt-2">
                    <button
                        wire:click="cancelarEliminar"
                        class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition"
                    >
                        No, mantener
                    </button>
                    <button
                        wire:click="eliminar"
                        class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-red-700 transition"
                    >
                        Sí, eliminar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

