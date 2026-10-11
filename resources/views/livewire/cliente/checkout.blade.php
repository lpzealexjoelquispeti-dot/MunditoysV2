<div class="space-y-2 font-sans" data-checkout>
    <!-- CABECERA Y MIGAS -->
    <div class="flex items-center justify-between gap-4 pb-2">
        <h1 class="text-2xl font-bold text-slate-900">Finalizar compra</h1>
        <a href="{{ route('tienda.carrito') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-brand-blue" wire:navigate>
            <x-lucide-arrow-left /> Carrito
        </a>
    </div>

    @if($mensajeError)
        <div class="flex items-center justify-between rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="text-xl"><x-lucide-triangle-alert /></span>
                <span class="font-medium">{{ $mensajeError }}</span>
            </div>
            <button type="button" wire:click="$set('mensajeError', null)" class="text-xs font-semibold opacity-60 hover:opacity-100"><x-lucide-x /></button>
        </div>
    @endif

    <!-- INDICADOR VISUAL DE PASOS (WIZARD STEPS) -->
    <div class="rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="grid grid-cols-3 gap-2 text-center text-xs font-semibold">
            <!-- PASO 1 -->
            <button
                type="button"
                wire:click="irAPaso(1)"
                @class([
                    'flex flex-col sm:flex-row items-center justify-center gap-2 px-3 py-2 rounded-xl transition',
                    'bg-brand-blue text-white shadow-md' => $pasoActual === 1,
                    'bg-blue-50/60 text-brand-blue border border-blue-200 hover:bg-blue-100' => $pasoActual > 1,
                    'bg-slate-50 text-slate-400' => $pasoActual < 1,
                ])
            >
                <span class="grid size-6 place-items-center rounded-full text-[11px] {{ $pasoActual === 1 ? 'bg-white text-brand-blue' : ($pasoActual > 1 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600') }}">
                    @if ($pasoActual > 1) <x-lucide-check /> @else 1 @endif
                </span>
                <span class="truncate">Sucursal</span>
            </button>

            <!-- PASO 2 -->
            <button
                type="button"
                wire:click="irAPaso(2)"
                @class([
                    'flex flex-col sm:flex-row items-center justify-center gap-2 px-3 py-2 rounded-xl transition',
                    'bg-brand-blue text-white shadow-md' => $pasoActual === 2,
                    'bg-blue-50/60 text-brand-blue border border-blue-200 hover:bg-blue-100' => $pasoActual > 2,
                    'bg-slate-50 text-slate-400' => $pasoActual < 2,
                ])
            >
                <span class="grid size-6 place-items-center rounded-full text-[11px] {{ $pasoActual === 2 ? 'bg-white text-brand-blue' : ($pasoActual > 2 ? 'bg-emerald-500 text-white' : 'bg-slate-200 text-slate-600') }}">
                    @if ($pasoActual > 2) <x-lucide-check /> @else 2 @endif
                </span>
                <span class="truncate">Factura</span>
            </button>

            <!-- PASO 3 -->
            <button
                type="button"
                wire:click="irAPaso(3)"
                @class([
                    'flex flex-col sm:flex-row items-center justify-center gap-2 px-3 py-2 rounded-xl transition',
                    'bg-brand-blue text-white shadow-md' => $pasoActual === 3,
                    'bg-slate-50 text-slate-400' => $pasoActual < 3,
                ])
            >
                <span class="grid size-6 place-items-center rounded-full text-[11px] {{ $pasoActual === 3 ? 'bg-white text-brand-blue' : 'bg-slate-200 text-slate-600' }}">
                    3
                </span>
                <span class="truncate">Pago</span>
            </button>
        </div>
    </div>

    <!-- CONTENEDOR PRINCIPAL: PASO ACTIVO (7/12) Y RESUMEN LATERAL (5/12) -->
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 items-start">
        <!-- COLUMNA IZQUIERDA: CONTENIDO DEL PASO ACTIVO -->
        <div class="lg:col-span-8 space-y-3">
            <!-- ============================================== -->
            <!-- FASE / PASO 1: SUCURSAL, RECOJO Y MAPA LEAFLET -->
            <!-- ============================================== -->
            @if($pasoActual === 1)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-2 animate-in fade-in zoom-in-95 duration-200">
                    <!-- MODALIDADES DE ENTREGA -->
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <div class="rounded-xl border-2 border-brand-blue bg-blue-50/40 px-3 py-2 text-left">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-brand-ink"><x-lucide-store /> Recojo en Sucursal</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Gratis</span>
                            </div>
                                                    </div>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 px-3 py-2 text-left" aria-disabled="true">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-500"><x-lucide-truck /> Envío a Domicilio</span>
                                
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">Pronto</p>
                        </div>
                    </div>
                    <!-- SELECTOR DE SUCURSAL -->
                    <div class="grid gap-3 rounded-xl bg-slate-50/80 p-3 border border-slate-100 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                                Sucursal <span class="text-red-500">*</span>
                            </label>
                            <select
                                wire:model.live="sucursalId"
                                class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-900 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                            >
                                @foreach($sucursales as $suc)
                                    <option value="{{ $suc->id }}">
                                        {{ $suc->nombre }} ({{ $suc->ciudad ?? 'Bolivia' }} - {{ $suc->direccion }})
                                    </option>
                                @endforeach
                            </select>
                            @error('sucursalId') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <!-- MAPA INTERACTIVO LEAFLET Y ENLACE GOOGLE MAPS -->
                        <div
                            wire:ignore
                            x-data="{
                                mapInstance: null,
                                destruido: false,
                                errorMapa: '',
                                lat: @entangle('sucursalLat'),
                                lng: @entangle('sucursalLng'),
                                nombre: @entangle('sucursalNombre'),
                                direccion: @entangle('sucursalDireccion'),
                                async init() {
                                    this.$nextTick(async () => {
                                        if (!this.destruido && window.mundiToysIndicadorMapa && this.$refs.mapaElemento) {
                                            const instancia = await window.mundiToysIndicadorMapa(
                                                this.$refs.mapaElemento,
                                                this.lat,
                                                this.lng,
                                                this.nombre,
                                                this.direccion,
                                                mensaje => { if (!this.destruido) this.errorMapa = mensaje; }
                                            );
                                            if (this.destruido) {
                                                instancia?.destruir();
                                                return;
                                            }
                                            this.mapInstance = instancia;
                                            this.refrescar();
                                        }
                                    });
                                    this.$watch('lat', () => this.refrescar());
                                    this.$watch('lng', () => this.refrescar());
                                    this.$watch('nombre', () => this.refrescar());
                                    this.$watch('direccion', () => this.refrescar());
                                },
                                refrescar() {
                                    if (this.mapInstance) {
                                        this.mapInstance.actualizar(this.lat, this.lng, this.nombre, this.direccion);
                                    }
                                },
                                destroy() {
                                    this.destruido = true;
                                    this.mapInstance?.destruir();
                                    this.mapInstance = null;
                                }
                            }"
                            class="space-y-1"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-slate-700 flex items-center gap-1.5">
                                    <span><x-lucide-map-pin /></span> Ubicación
                                </span>
                                <a
                                    :href="`https://www.google.com/maps/search/?api=1&query=${lat},${lng}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-brand-blue shadow-xs hover:bg-slate-50 hover:text-blue-700 transition"
                                >
                                    Abrir mapa <x-lucide-arrow-up-right />
                                </a>
                            </div>

                            <!-- CONTENEDOR LEAFLET -->
                            <div class="relative h-28 w-full rounded-xl overflow-hidden border border-slate-200 shadow-inner z-0">
                                <div x-ref="mapaElemento" class="size-full z-0"></div>
                            </div>
                            <p x-show="errorMapa" x-text="errorMapa" x-cloak class="text-xs text-brand-red" role="status"></p>
                        </div>

                        <!-- DATOS DEL RETIRANTE -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-200 sm:col-span-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Quién retira <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="nombreRetira"
                                    placeholder="Ej. Juan Pérez Mamani"
                                    class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                                >
                                @error('nombreRetira') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    CI <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="ciRetira"
                                    placeholder="Ej. 8493021 LP"
                                    class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                                >
                                @error('ciRetira') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1">
                                    Celular <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="telefonoRetira"
                                    placeholder="Ej. 76543210"
                                    class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                                >
                                @error('telefonoRetira') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN DE AVANCE A FASE 2 -->
                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            wire:click="avanzarPaso1"
                            class="inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600 active:scale-98"
                        >
                            Continuar <x-lucide-arrow-right />
                        </button>
                    </div>
                </div>
            @endif

            <!-- ============================================== -->
            <!-- FASE / PASO 2: FACTURACIÓN ELECTRÓNICA OFICIAL -->
            <!-- ============================================== -->
            @if($pasoActual === 2)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-2 animate-in fade-in zoom-in-95 duration-200">
                    <!-- RESUMEN BREVE DE SUCURSAL ELEGIDA EN PASO 1 -->
                    <div class="flex items-center justify-between rounded-2xl bg-slate-50 border border-slate-100 p-4 text-xs">
                        <div class="flex items-center gap-2.5">
                            <span class="text-xl"><x-lucide-store /></span>
                            <div>
                                <div class="font-semibold text-brand-blue">{{ $sucursalSeleccionada?->nombre ?? 'Sucursal' }}</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            wire:click="retrocederPaso"
                            class="text-xs font-semibold text-brand-orange hover:underline"
                        >
                            Cambiar
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Nombre o razón social <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="facturaNombre"
                                placeholder="Nombre completo o Empresa"
                                class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                            >
                            @error('facturaNombre') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                NIT o CI <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="facturaNitCi"
                                placeholder="Ej. 1028374901 o CI"
                                class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                            >
                            @error('facturaNitCi') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Correo para la factura <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                wire:model="facturaEmail"
                                placeholder="tu-correo@ejemplo.com"
                                class="w-full rounded-xl border-slate-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                            >
                            @error('facturaEmail') <span class="text-xs text-red-600 font-semibold block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <!-- BOTONES DE NAVEGACIÓN -->
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="retrocederPaso"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                        >
                            <x-lucide-arrow-left /> Atrás
                        </button>

                        <button
                            type="button"
                            wire:click="avanzarPaso2"
                            class="inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600 active:scale-98"
                        >
                            Continuar <x-lucide-arrow-right />
                        </button>
                    </div>
                </div>
            @endif

            <!-- ============================================== -->
            <!-- FASE / PASO 3: PASARELA DE PAGO CON STRIPE     -->
            <!-- ============================================== -->
            @if($pasoActual === 3)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-2 animate-in fade-in zoom-in-95 duration-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-2">
                        <h2 class="text-lg font-semibold text-slate-900">Pago con tarjeta</h2>
                        <button
                            type="button"
                            wire:click="rellenarTarjetaPrueba"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-brand-blue/30 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-brand-blue hover:bg-blue-100 transition self-start sm:self-auto"
                        >
                            <x-lucide-zap /> Tarjeta de prueba
                        </button>
                    </div>

                    <!-- TARJETA VISUAL / FORMULARIO -->
                    <div class="space-y-4 rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-4 text-white shadow-xl">
                        <div class="flex items-center justify-between">
                            <x-lucide-credit-card class="text-xl text-slate-400" />
                            <div class="flex items-center gap-2">
                                <span class="rounded bg-blue-600 px-1.5 py-0.5 text-[10px] font-semibold">VISA</span>
                                <span class="rounded bg-orange-600 px-1.5 py-0.5 text-[10px] font-semibold">MC</span>
                                <span class="rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold">STRIPE</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-slate-300 mb-1">
                                Número de tarjeta <span class="text-orange-400">*</span>
                            </label>
                            <input
                                type="text"
                                wire:model="numeroTarjeta"
                                placeholder="4242 4242 4242 4242"
                                maxlength="20"
                                class="w-full font-mono rounded-xl border border-slate-700 bg-slate-800/90 px-3 py-2 text-sm text-white placeholder-slate-500 focus:border-brand-orange focus:ring-brand-orange"
                            >
                            @error('numeroTarjeta') <span class="text-xs text-red-400 font-semibold block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-300 mb-1">
                                    Titular <span class="text-orange-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    wire:model="nombreTarjeta"
                                    placeholder="Como aparece en la tarjeta"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-800/90 px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:border-brand-orange focus:ring-brand-orange"
                                >
                                @error('nombreTarjeta') <span class="text-xs text-red-400 font-semibold block mt-1">{{ $message }}</span> @enderror
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-300 mb-1">
                                        Vence <span class="text-orange-400">*</span>
                                    </label>
                                    <div class="flex items-center gap-1">
                                        <input
                                            type="text"
                                            wire:model="mesExpiracion"
                                            placeholder="MM"
                                            maxlength="2"
                                            class="w-full font-mono text-center rounded-xl border border-slate-700 bg-slate-800/90 px-1.5 py-2 text-xs text-white placeholder-slate-500 focus:border-brand-orange focus:ring-brand-orange"
                                        >
                                        <span class="text-slate-500">/</span>
                                        <input
                                            type="text"
                                            wire:model="anoExpiracion"
                                            placeholder="AA"
                                            maxlength="2"
                                            class="w-full font-mono text-center rounded-xl border border-slate-700 bg-slate-800/90 px-1.5 py-2 text-xs text-white placeholder-slate-500 focus:border-brand-orange focus:ring-brand-orange"
                                        >
                                    </div>
                                    @error('mesExpiracion') <span class="text-[10px] text-red-400 font-semibold block mt-1">{{ $message }}</span> @enderror
                                    @error('anoExpiracion') <span class="text-[10px] text-red-400 font-semibold block mt-1">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-300 mb-1">
                                        CVC <span class="text-orange-400">*</span>
                                    </label>
                                    <input
                                        type="password"
                                        wire:model="cvcTarjeta"
                                        placeholder="123"
                                        maxlength="4"
                                        class="w-full font-mono text-center rounded-xl border border-slate-700 bg-slate-800/90 px-2 py-2 text-xs text-white placeholder-slate-500 focus:border-brand-orange focus:ring-brand-orange"
                                    >
                                    @error('cvcTarjeta') <span class="text-[10px] text-red-400 font-semibold block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN FINAL DE CONFIRMACIÓN -->
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            wire:click="retrocederPaso"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                        >
                            <x-lucide-arrow-left /> Atrás
                        </button>

                        <button
                            type="button"
                            wire:click="procesarPago"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600 active:scale-98 disabled:opacity-50"
                        >
                            <span wire:loading.remove>
                                Pagar Bs. {{ number_format($total, 2) }}
                            </span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                Procesando…
                            </span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- COLUMNA LATERAL DERECHA: RESUMEN DE COMPRA FIJO (5/12) -->
        <div class="lg:col-span-4 space-y-3" x-data="{ paginaResumen: 0 }">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h3 class="font-semibold text-slate-900">Resumen</h3>

                <!-- MINI LISTA DE PRODUCTOS CON IMÁGENES CORREGIDAS -->
                <div class="divide-y divide-slate-100 space-y-2">
                    @foreach($items as $item)
                        @php
                            $prod = $item['producto'];
                            $imgObj = $prod->imagenes->firstWhere('principal', true) 
                                ?? $prod->imagenes->sortBy('orden')->first() 
                                ?? $prod->imagenes->first();
                            $rutaArchivo = $imgObj?->ruta_archivo;
                            $urlFoto = $rutaArchivo 
                                ? (str_starts_with($rutaArchivo, 'http') ? $rutaArchivo : asset('storage/' . ltrim($rutaArchivo, '/'))) 
                                : null;
                        @endphp
                        <div class="flex items-center gap-3 pt-2" wire:key="checkout-item-{{ $prod->id }}" x-show="paginaResumen === {{ intdiv($loop->index, 2) }}" @if($loop->index >= 2) x-cloak @endif>
                            <div class="size-12 rounded-xl border border-slate-100 bg-slate-50 p-1 shrink-0 flex items-center justify-center overflow-hidden">
                                @if($urlFoto)
                                    <img 
                                        src="{{ $urlFoto }}" 
                                        alt="{{ $prod->nombre }}" 
                                        class="size-full object-contain"
                                        onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"
                                    >
                                    <span class="text-xs hidden"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @else
                                    <span class="text-xs"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-semibold text-slate-800 truncate">{{ $prod->nombre }}</div>
                                <div class="text-[11px] text-slate-400">
                                    {{ $item['cantidad'] }}x Bs. {{ number_format($item['precio_unitario'], 2) }}
                                </div>
                            </div>
                            <div class="text-xs font-semibold text-slate-900">
                                Bs. {{ number_format($item['subtotal'], 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($items->count() > 2)
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <button type="button" x-on:click="paginaResumen--" x-bind:disabled="paginaResumen === 0" class="rounded-lg border border-slate-200 px-2 py-1 disabled:opacity-40"><x-lucide-arrow-left /> Anterior</button>
                        <span x-text="`${paginaResumen + 1} / {{ (int) ceil($items->count() / 2) }}`"></span>
                        <button type="button" x-on:click="paginaResumen++" x-bind:disabled="paginaResumen >= {{ (int) ceil($items->count() / 2) - 1 }}" class="rounded-lg border border-slate-200 px-2 py-1 disabled:opacity-40">Siguiente <x-lucide-arrow-right /></button>
                    </div>
                @endif

                <!-- TOTALES -->
                <div class="space-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span class="font-semibold text-slate-800">Bs. {{ number_format($total, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Recojo en sucursal</span>
                        <span class="font-semibold text-emerald-600">Gratis</span>
                    </div>

                    <div class="border-t border-slate-200 pt-3 flex items-baseline justify-between">
                        <span class="font-semibold text-slate-900">Total</span>
                        <span class="text-2xl font-bold text-brand-blue">Bs. {{ number_format($total, 2) }}</span>
                    </div>
                </div>

                <p class="flex items-center justify-center gap-1.5 text-xs text-slate-400"><x-lucide-lock /> Pago seguro con Stripe</p>
            </div>
        </div>
    </div>
</div>
