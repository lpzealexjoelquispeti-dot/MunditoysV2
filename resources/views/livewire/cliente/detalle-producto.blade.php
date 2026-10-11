<div
    x-data="detalleJuguete"
    x-init="iniciarAnimacionCarrito()"
    class="space-y-12 pb-16"
>
    <!-- MIGA DE PAN -->
    <nav class="flex items-center gap-2 text-xs font-semibold text-slate-400">
        <a href="{{ route('welcome') }}" class="hover:text-brand-orange transition" wire:navigate>Inicio</a>
        <span>/</span>
        <a href="{{ route('tienda.catalogo') }}" class="hover:text-brand-orange transition" wire:navigate>Catálogo</a>
        <span>/</span>
        @if($producto->categoria)
            <a href="{{ route('tienda.catalogo', ['categoria' => $producto->categoria_id]) }}" class="hover:text-brand-orange transition" wire:navigate>
                {{ $producto->categoria->nombre }}
            </a>
            <span>/</span>
        @endif
        <span class="text-slate-700 font-semibold truncate max-w-[200px] sm:max-w-xs">{{ $producto->nombre }}</span>
    </nav>

    <!-- BLOQUE PRINCIPAL: 3 DIVS LADO A LADO (CARRUSEL DE FOTOS 35% | MODELO 3D 35% | DATOS Y COMPRA 30%) -->
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,3.5fr)_minmax(0,3.5fr)_minmax(0,3fr)] gap-6 items-start">

        <!-- DIV 1: CARRUSEL DE FOTOS (35% DEL ANCHO) -->
        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200/90 bg-white p-4 shadow-sm">
            <!-- FOTO PRINCIPAL ACTIVA -->
            <div class="relative aspect-square w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-inner flex items-center justify-center p-3">
                @if($fotoSeleccionada)
                    <img
                        id="foto-principal-juguete"
                        src="{{ asset('storage/' . ltrim($fotoSeleccionada, '/')) }}"
                        alt="{{ $producto->nombre }}"
                        class="size-full object-contain transition-all duration-300 hover:scale-105"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                    >
                    <div style="display:none;" class="size-full place-items-center text-7xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                @else
                    <div class="grid size-full place-items-center text-7xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                @endif

                <!-- BADGES EN LA FOTO -->
                <div class="absolute top-3 left-3 flex flex-col gap-1 pointer-events-none">
                    @if($producto->categoria)
                        <span class="rounded-full bg-white/95 px-2.5 py-0.5 text-[10px] font-semibold text-brand-orange shadow-sm backdrop-blur-sm">
                            {{ $producto->categoria->nombre }}
                        </span>
                    @endif
                </div>

                @if($producto->edad_recomendada)
                    <span class="absolute bottom-3 right-3 rounded-full bg-brand-ink/90 px-2.5 py-0.5 text-[10px] font-semibold text-white shadow-sm backdrop-blur-sm pointer-events-none">
                        +{{ $producto->edad_recomendada }} años
                    </span>
                @endif
            </div>

            <!-- MINIATURAS INTERCAMBIABLES -->
            <div class="flex items-center gap-2 overflow-x-auto pb-1 pt-0.5">
                @foreach($producto->imagenes as $img)
                    <button
                        type="button"
                        wire:click="seleccionarFoto('{{ $img->ruta_archivo }}')"
                        @class([
                            'relative size-14 shrink-0 overflow-hidden rounded-xl border-2 transition p-1 bg-slate-50',
                            'border-brand-orange shadow-sm ring-2 ring-brand-orange/20 scale-105' => $fotoSeleccionada === $img->ruta_archivo,
                            'border-slate-200 hover:border-slate-400 opacity-70 hover:opacity-100' => $fotoSeleccionada !== $img->ruta_archivo,
                        ])
                        title="Ver esta imagen"
                    >
                        <img
                            src="{{ asset('storage/' . ltrim($img->ruta_archivo, '/')) }}"
                            alt="Miniatura"
                            class="size-full object-contain"
                            onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                        >
                        <span style="display:none;" class="size-full place-items-center text-xs"><x-lucide-toy-brick class="text-slate-300" /></span>
                    </button>
                @endforeach
            </div>
        </div>

        <!-- DIV 2: MODELO 3D INTERACTIVO (35% DEL ANCHO) -->
        <div class="flex flex-col gap-3 rounded-3xl border border-blue-100 bg-white p-4 shadow-sm">
            <!-- CONTENEDOR 3D SIEMPRE VISIBLE -->
            <div class="relative aspect-square w-full overflow-hidden rounded-2xl border border-slate-200 bg-slate-900 flex items-center justify-center">
                @if($modeloGlb && $modeloGlb->modelo_glb_path)
                    <div class="size-full" wire:ignore>
                        <model-viewer
                            src="{{ asset('storage/' . ltrim($modeloGlb->modelo_glb_path, '/')) }}"
                            alt="Modelo 3D de {{ $producto->nombre }}"
                            auto-rotate
                            rotation-per-second="25deg"
                            camera-controls
                            touch-action="pan-y"
                            shadow-intensity="1.5"
                            shadow-softness="0.8"
                            environment-image="neutral"
                            exposure="1.0"
                            class="size-full w-full h-full bg-slate-900"
                            style="width: 100%; height: 100%; --poster-color: #0f172a;"
                        >
                        </model-viewer>
                    </div>
                    <!-- INDICADOR DE CONTROLES FLOTANTE -->
                    <span class="pointer-events-none absolute bottom-3 left-1/2 inline-flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-slate-950/70 px-3 py-1 text-xs text-slate-200 backdrop-blur">
                        <x-lucide-rotate-3d /> Arrastra para girar
                    </span>
                @else
                    <div class="flex flex-col items-center justify-center p-6 text-center text-slate-500">
                        <x-lucide-box class="text-5xl" />
                        <p class="mt-2 text-sm">Vista 3D próximamente</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- DIV 3: ESPECIFICACIONES, PRECIO Y COMPRA (30% RESTANTE) -->
        <div class="flex flex-col justify-between rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm space-y-4">
            <!-- CABECERA DE DATOS -->
            <div>
                <p class="text-sm text-slate-500">{{ $producto->marca?->nombre ?? 'MundiToys' }}</p>

                <h1 class="mt-0.5 text-2xl font-bold leading-tight text-brand-ink sm:text-3xl">
                    {{ $producto->nombre }}
                </h1>

                <div class="mt-2 flex items-center gap-3">
                    <span class="font-mono text-xs text-slate-400">{{ $producto->codigo_producto }}</span>

                    <!-- PUNTUACIÓN DE ESTRELLAS -->
                    <div class="flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200/80">
                        <span><x-lucide-star class="fill-current" /></span>
                        <span>{{ $promedioCalificacion > 0 ? number_format($promedioCalificacion, 1) : 'Nuevo' }}</span>
                        @if($totalResenas > 0)
                            <span class="text-amber-500 font-normal">({{ $totalResenas }})</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- PRECIO DESTACADO -->
            @if($producto->precio_venta !== null)
                <p class="text-3xl font-bold text-brand-orange sm:text-4xl">Bs. {{ number_format((float) $producto->precio_venta, 2) }}</p>
            @else
                <p class="text-base font-semibold text-slate-400">Precio por confirmar</p>
            @endif

            <!-- ESPECIFICACIONES CON EL MISMO FORMATO DL/DT/DD DEL ADMIN -->
            <dl class="grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                    <dt class="text-[10px] font-semibold text-slate-400">Edad recomendada</dt>
                    <dd class="mt-0.5 font-semibold text-brand-ink">
                        {{ $producto->edad_recomendada ? '+'.$producto->edad_recomendada.' años' : 'Todas' }}
                    </dd>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                    <dt class="text-[10px] font-semibold text-slate-400">Dimensiones</dt>
                    <dd class="mt-0.5 font-semibold text-brand-ink">
                        {{ $producto->dimensiones ? $producto->dimensiones.' cm' : 'Estándar' }}
                    </dd>
                </div>

                <div class="col-span-2 rounded-xl border border-slate-100 bg-slate-50 p-2.5">
                    <dt class="sr-only">Disponibilidad</dt>
                    <dd class="font-semibold">
                        @if($stock > 0)
                            <span class="text-emerald-600"><x-lucide-check /> {{ $stock }} disponibles</span>
                        @else
                            <span class="text-amber-600"><x-lucide-triangle-alert /> En reposición</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if($producto->descripcion)
                <p class="line-clamp-3 text-sm leading-6 text-slate-600">{{ $producto->descripcion }}</p>
            @endif

            <!-- CONTADOR Y BOTONES DE ACCIÓN -->
            <div class="border-t border-slate-100 pt-4 space-y-3">
                <!-- SELECTOR DE CANTIDAD -->
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-600">Cantidad</span>
                    <div class="flex items-center rounded-xl border border-slate-200 bg-white p-0.5 shadow-sm">
                        <button
                            type="button"
                            wire:click="decrementar"
                            class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition font-semibold disabled:opacity-40"
                            @disabled($cantidad <= 1)
                        >
                            -
                        </button>
                        <span class="w-9 text-center text-xs font-bold text-slate-800">
                            {{ $cantidad }}
                        </span>
                        <button
                            type="button"
                            wire:click="incrementar({{ $stock > 0 ? $stock : 99 }})"
                            class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition font-semibold disabled:opacity-40"
                            @disabled($stock > 0 && $cantidad >= $stock)
                        >
                            +
                        </button>
                    </div>
                </div>

                <!-- BOTÓN COMPRAR AHORA -->
                <button
                    type="button"
                    wire:click="comprarAhora"
                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-blue px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-blue-dark transition"
                >
                    <x-lucide-zap /> Comprar ahora
                </button>

                <!-- BOTÓN AÑADIR AL CARRITO (CON ANIMACIÓN) -->
                <button
                    id="btn-agregar-carrito"
                    type="button"
                    wire:click="agregarAlCarrito"
                    class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-orange px-5 py-3.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-600 transition"
                >
                    <x-lucide-shopping-cart /> Añadir al carrito
                </button>
            </div>
        </div>
    </div>

    <!-- SECCIÓN: RESEÑAS Y CALIFICACIONES DEL JUGUETE -->
    <section id="resenas-juguete" class="rounded-3xl border border-slate-200/90 bg-white p-6 sm:p-10 shadow-sm space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-6">
            <h2 class="text-2xl font-bold text-brand-ink">Reseñas</h2>

            <!-- RESUMEN PROMEDIO DE ESTRELLAS -->
            <div class="flex items-center gap-4 bg-orange-50/80 rounded-2xl p-4 border border-orange-100">
                <div class="text-4xl font-semibold text-brand-orange">
                    {{ $promedioCalificacion > 0 ? number_format($promedioCalificacion, 1) : '—' }}
                </div>
                <div>
                    <div class="flex text-amber-400 text-sm">
                        @for($i = 1; $i <= 5; $i++)
                            <x-lucide-star @class(['fill-current' => $i <= round($promedioCalificacion)]) />
                        @endfor
                    </div>
                    <span class="text-xs text-slate-500 font-semibold block mt-0.5">
                        {{ $totalResenas }} {{ $totalResenas === 1 ? 'opinión' : 'opiniones' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- LISTADO DE RESEÑAS -->
        @if(session('resena_guardada')) <p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('resena_guardada') }}</p> @endif
        @error('resena') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
        @if($puedeResenar)
            <form wire:submit="guardarResena" class="space-y-3 rounded-2xl border border-slate-200 p-4">
                <h3 class="font-semibold">Tu reseña</h3>
                <label for="calificacion-resena" class="block text-sm">Calificación</label>
                <select id="calificacion-resena" wire:model="calificacion" class="rounded-xl border-slate-200 text-sm">
                    <option value="">Selecciona una calificación</option>
                    @foreach([5 => 'Excelente', 4 => 'Muy bueno', 3 => 'Bueno', 2 => 'Regular', 1 => 'Malo'] as $valor => $texto)<option value="{{ $valor }}">{{ $valor }} — {{ $texto }}</option>@endforeach
                </select>
                @error('calificacion') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                <label for="comentario-resena" class="block text-sm">Comentario</label>
                <textarea id="comentario-resena" wire:model="comentario" rows="3" maxlength="2000" class="w-full rounded-xl border-slate-200 text-sm"></textarea>
                @error('comentario') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Publicar reseña</button>
            </form>
        @endif
        <div class="space-y-4">
            @forelse($producto->resenas as $resena)
                <article class="rounded-2xl border border-slate-100 bg-slate-50/50 p-5 transition hover:bg-slate-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="grid size-9 place-items-center rounded-full bg-brand-blue text-xs font-semibold text-white">
                                {{ Str::substr($resena->usuario?->name ?? 'C', 0, 2) }}
                            </div>
                            <div>
                                <h4 class="text-xs font-semibold text-brand-ink">
                                    {{ $resena->usuario?->name ?? 'Cliente verificado' }}
                                </h4>
                                <span class="text-[10px] text-slate-400">
                                    {{ $resena->created_at ? $resena->created_at->format('d/m/Y') : 'Compra reciente' }}
                                </span>
                            </div>
                        </div>

                        <!-- ESTRELLAS -->
                        <div class="flex text-amber-400 text-xs">
                            @for($i = 1; $i <= 5; $i++)
                                <x-lucide-star @class(['fill-current' => $i <= $resena->calificacion]) />
                            @endfor
                        </div>
                    </div>

                    @if($resena->comentario)
                        <p class="mt-3 text-xs sm:text-sm text-slate-600 leading-relaxed">
                            "{{ $resena->comentario }}"
                        </p>
                    @endif
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-slate-400">
                    <x-lucide-star class="mx-auto text-4xl text-slate-300" />
                    <p class="mt-2 text-sm">Aún no hay reseñas</p>
                </div>
            @endforelse
        </div>
    </section>

    <!-- SECCIÓN: PODRÍA GUSTARTE (PRODUCTOS RELACIONADOS) CON HOVER 3D -->
    @if($relacionados->isNotEmpty())
        <section class="space-y-6">
            <div class="flex items-center justify-between">
                <h2 class="text-2xl font-bold text-brand-ink">Podría gustarte</h2>
                <a href="{{ route('tienda.catalogo') }}" class="text-sm font-semibold text-brand-orange hover:underline" wire:navigate>Ver catálogo</a>
            </div>

            <!-- GRILLA DE PRODUCTOS RELACIONADOS -->
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($relacionados as $rel)
                    @php
                        $relImagen = $rel->imagenes->sortBy('orden')->first()?->ruta_archivo;
                        $relModelo3D = $rel->modelos3D->whereNotNull('modelo_glb_path')->first();
                    @endphp

                    <!-- TARJETA CON HOVER 3D MEDIANTE ALPINE.JS -->
                    <article
                        x-data="{ hover3d: false, timerHover: null }"
                        class="group relative flex flex-col rounded-3xl border border-slate-200/90 bg-white p-4 shadow-sm hover:shadow-xl hover:border-brand-orange/50 transition duration-300"
                        @mouseenter="timerHover = setTimeout(() => { hover3d = true }, 250)"
                        @mouseleave="clearTimeout(timerHover); hover3d = false"
                    >
                        <!-- FOTO PRINCIPAL -->
                        <a
                            href="{{ route('tienda.producto.detalle', ['codigo' => $rel->codigo_producto]) }}"
                            class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-100 block"
                            wire:navigate
                        >
                            @if($relImagen)
                                <img
                                    src="{{ asset('storage/' . ltrim($relImagen, '/')) }}"
                                    alt="{{ $rel->nombre }}"
                                    class="size-full object-cover group-hover:scale-105 transition duration-500"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                >
                                <div style="display:none;" class="size-full place-items-center text-5xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                            @else
                                <div class="grid size-full place-items-center text-5xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                            @endif

                            @if($relModelo3D)
                                <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-slate-900/85 px-2 py-0.5 text-[10px] font-semibold text-white shadow-sm backdrop-blur-sm">
                                    <span><x-lucide-box /></span> 3D
                                </span>
                            @endif
                        </a>

                        <!-- MINIMODAL FLOTANTE 3D EN HOVER (SI EL JUGUETE TIENE MODELADO 3D) -->
                        @if($relModelo3D)
                            <div
                                x-show="hover3d"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                x-cloak
                                class="absolute bottom-full left-1/2 -translate-x-1/2 mb-3 z-40 w-64 rounded-3xl border border-slate-200 bg-white/95 backdrop-blur-md p-3 shadow-2xl pointer-events-none"
                            >
                                <div class="aspect-square w-full rounded-2xl bg-slate-50 overflow-hidden border border-slate-100">
                                    <model-viewer
                                        src="{{ asset('storage/' . $relModelo3D->modelo_glb_path) }}"
                                        alt="Vista previa 3D de {{ $rel->nombre }}"
                                        auto-rotate
                                        rotation-per-second="40deg"
                                        shadow-intensity="0.8"
                                        class="size-full"
                                    ></model-viewer>
                                </div>
                            </div>
                        @endif

                        <!-- INFORMACIÓN DE LA TARJETA -->
                        <div class="mt-3 flex flex-1 flex-col justify-between">
                            <div>
                                <span class="text-[10px] font-semibold text-slate-400 block">
                                    {{ $rel->categoria?->nombre ?? 'Juguete' }}
                                </span>
                                <h3 class="mt-1 text-xs sm:text-sm font-semibold text-slate-900 line-clamp-2 group-hover:text-brand-orange transition">
                                    <a href="{{ route('tienda.producto.detalle', ['codigo' => $rel->codigo_producto]) }}" wire:navigate>
                                        {{ $rel->nombre }}
                                    </a>
                                </h3>
                            </div>

                            <p class="mt-2 font-bold text-brand-orange">
                                {{ $rel->precio_venta !== null ? 'Bs. '.number_format((float) $rel->precio_venta, 2) : 'Precio por confirmar' }}
                            </p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
</div>

<!-- SCRIPT ALPINE.JS PARA LA ANIMACIÓN HACIA EL CARRITO -->
@script
<script>
    Alpine.data('detalleJuguete', () => ({
        iniciarAnimacionCarrito() {
            window.addEventListener('animar-al-carrito', (event) => {
                const datos = event.detail[0] || event.detail;
                const boton = document.getElementById('btn-agregar-carrito');
                const destino = document.getElementById('navbar-carrito-btn') || document.getElementById('navbar-carrito-badge');

                if (!boton || !destino) return;

                const rectOrigen = boton.getBoundingClientRect();
                const rectDestino = destino.getBoundingClientRect();

                // Crear elemento clon volador
                const volador = document.createElement('div');
                volador.style.position = 'fixed';
                volador.style.zIndex = '9999';
                volador.style.left = `${rectOrigen.left + rectOrigen.width / 2 - 20}px`;
                volador.style.top = `${rectOrigen.top + rectOrigen.height / 2 - 20}px`;
                volador.style.width = '40px';
                volador.style.height = '40px';
                volador.style.borderRadius = '9999px';
                volador.style.backgroundColor = '#f97316';
                volador.style.color = '#ffffff';
                volador.style.display = 'grid';
                volador.style.placeItems = 'center';
                volador.style.fontSize = '20px';
                volador.style.boxShadow = '0 10px 25px rgba(249, 115, 22, 0.5)';
                volador.style.transition = 'all 0.65s cubic-bezier(0.2, 0.8, 0.2, 1)';
                volador.style.pointerEvents = 'none';
                volador.innerHTML = @js(svg('lucide-toy-brick')->toHtml());

                document.body.appendChild(volador);

                // Forzar reflujo y animar hacia el carrito
                requestAnimationFrame(() => {
                    volador.style.left = `${rectDestino.left + rectDestino.width / 2 - 15}px`;
                    volador.style.top = `${rectDestino.top + rectDestino.height / 2 - 15}px`;
                    volador.style.width = '24px';
                    volador.style.height = '24px';
                    volador.style.fontSize = '12px';
                    volador.style.opacity = '0.3';
                    volador.style.transform = 'scale(0.5)';
                });

                // Al terminar el vuelo, hacer vibrar/pulsar el carrito
                setTimeout(() => {
                    volador.remove();
                    destino.classList.add('animate-bounce');
                    setTimeout(() => {
                        destino.classList.remove('animate-bounce');
                    }, 800);
                }, 650);
            });
        }
    }));
</script>
@endscript
