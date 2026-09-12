<div class="space-y-6">
    <!-- MIGA DE PAN Y ENCABEZADO -->
    <div class="flex flex-col gap-2 border-b border-orange-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <nav class="flex items-center gap-2 text-xs font-semibold text-slate-400">
                <a href="{{ route('welcome') }}" class="hover:text-brand-orange transition">Inicio</a>
                <span>/</span>
                <span class="text-slate-700">Catálogo de Juguetes</span>
            </nav>
            <p class="mt-2 text-xs font-bold uppercase tracking-[.18em] text-brand-orange">
                Catálogo MundiToys
            </p>
            <h1 class="mt-0.5 text-2xl sm:text-3xl font-bold text-brand-ink">
                Catálogo de Juguetes
            </h1>
            <p class="text-xs sm:text-sm text-slate-500">
                Explora nuestra colección con filtros por categoría, marca, edad, precio y modelos 3D interactivos.
            </p>
        </div>

        <!-- INDICADOR RÁPIDO DE RESULTADOS -->
        <div class="flex items-center gap-2">
            <span class="rounded-full bg-brand-orange/10 px-3.5 py-1.5 text-xs font-bold text-brand-orange">
                {{ $productos->total() }} {{ $productos->total() === 1 ? 'juguete encontrado' : 'juguetes encontrados' }}
            </span>
        </div>
    </div>

    <!-- CUERPO PRINCIPAL: FILTROS + PRODUCTOS -->
    <div class="grid gap-8 lg:grid-cols-[280px_1fr]">

        <!-- PANEL LATERAL DE FILTROS -->
        <aside class="space-y-6">
            <div class="rounded-3xl border border-slate-200/90 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <span>🌪️</span>
                        <span>Filtros</span>
                    </div>
                    <button
                        type="button"
                        wire:click="limpiarFiltros"
                        class="text-xs font-semibold text-brand-orange hover:underline"
                        title="Restablecer todos los filtros"
                    >
                        Limpiar todo
                    </button>
                </div>

                <!-- 1. BUSCADOR POR TEXTO -->
                <div class="mt-4">
                    <label for="buscar_input" class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1.5">
                        Buscar palabra clave
                    </label>
                    <div class="relative">
                        <input
                            id="buscar_input"
                            type="search"
                            wire:model.live.debounce.300ms="buscar"
                            placeholder="Nombre, código o juguete..."
                            class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                        >
                        <span class="absolute left-3 top-2.5 text-xs text-slate-400">🔍</span>
                    </div>
                </div>

                <!-- 2. FILTRO DE MODELO 3D -->
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label class="flex items-center gap-3 cursor-pointer rounded-xl bg-blue-50/60 p-2.5 border border-blue-100 hover:bg-blue-50 transition">
                        <input
                            type="checkbox"
                            wire:model.live="solo3D"
                            class="size-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"
                        >
                        <div>
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <span>🧊</span> Solo con Visor 3D
                            </span>
                            <span class="text-[10px] text-slate-500 block">Gemelos digitales interactivos</span>
                        </div>
                    </label>
                </div>

                <!-- 3. FILTRO POR CATEGORÍA -->
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">
                        Categoría
                    </label>
                    <div class="space-y-1 max-h-48 overflow-y-auto pr-1">
                        <button
                            type="button"
                            wire:click="$set('categoriaId', '')"
                            @class([
                                'w-full flex items-center justify-between rounded-xl px-3 py-1.5 text-xs font-semibold text-left transition',
                                'bg-brand-orange text-white' => $categoriaId === '',
                                'text-slate-600 hover:bg-slate-100' => $categoriaId !== '',
                            ])
                        >
                            <span>Todas las categorías</span>
                        </button>
                        @foreach($categorias as $cat)
                            <button
                                type="button"
                                wire:click="$set('categoriaId', '{{ $cat->id }}')"
                                @class([
                                    'w-full flex items-center justify-between rounded-xl px-3 py-1.5 text-xs font-semibold text-left transition',
                                    'bg-brand-orange text-white' => (string) $categoriaId === (string) $cat->id,
                                    'text-slate-600 hover:bg-slate-100' => (string) $categoriaId !== (string) $cat->id,
                                ])
                            >
                                <span class="truncate">{{ $cat->nombre }}</span>
                                <span class="text-[10px] opacity-75">({{ $cat->productos_count }})</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- 4. FILTRO POR MARCA -->
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label for="marca_select" class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-1.5">
                        Marca
                    </label>
                    <select
                        id="marca_select"
                        wire:model.live="marcaId"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                    >
                        <option value="">Todas las marcas</option>
                        @foreach($marcas as $marca)
                            <option value="{{ $marca->id }}">{{ $marca->nombre }} ({{ $marca->productos_count }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- 5. FILTRO POR RANGO DE EDAD -->
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">
                        Edad Recomendada
                    </label>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach([
                            '' => 'Todas',
                            '2' => 'Hasta 2 años',
                            '4' => '3 a 5 años',
                            '6' => '6 a 8 años',
                            '10' => '9 a 12 años',
                            '14' => '+12 años',
                        ] as $valorEdad => $etiquetaEdad)
                            <button
                                type="button"
                                wire:click="$set('edad', '{{ $valorEdad }}')"
                                @class([
                                    'rounded-xl px-2.5 py-1.5 text-xs font-bold text-center border transition',
                                    'bg-slate-900 text-white border-slate-900 shadow-sm' => (string) $edad === (string) $valorEdad,
                                    'border-slate-200 text-slate-600 hover:bg-slate-50' => (string) $edad !== (string) $valorEdad,
                                ])
                            >
                                {{ $etiquetaEdad }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- 6. FILTRO POR RANGO DE PRECIO (BS.) -->
                <div class="mt-5 border-t border-slate-100 pt-4">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">
                        Rango de Precio (Bs.)
                    </label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input
                                type="number"
                                wire:model.live.debounce.400ms="precioMin"
                                placeholder="Mínimo"
                                min="0"
                                class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                        </div>
                        <span class="text-xs font-bold text-slate-400">-</span>
                        <div class="relative flex-1">
                            <input
                                type="number"
                                wire:model.live.debounce.400ms="precioMax"
                                placeholder="Máximo"
                                min="0"
                                class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- SECCIÓN DERECHA: BARRA DE ORDENACIÓN Y GRILLA DE PRODUCTOS -->
        <section class="space-y-4">
            <!-- BARRA DE ORDEN Y CHIPS ACTIVOS -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between rounded-2xl bg-white p-3.5 border border-slate-200/80 shadow-sm">
                <!-- CHIPS DE FILTROS ACTIVOS -->
                <div class="flex flex-wrap items-center gap-1.5">
                    @if($buscar)
                        <span class="inline-flex items-center gap-1 rounded-lg bg-orange-50 border border-orange-200 px-2.5 py-1 text-xs font-semibold text-brand-orange">
                            <span>"{{ $buscar }}"</span>
                            <button type="button" wire:click="$set('buscar', '')" class="hover:text-red-700">✕</button>
                        </span>
                    @endif

                    @if($categoriaId)
                        @php
                            $catActiva = $categorias->firstWhere('id', $categoriaId);
                        @endphp
                        @if($catActiva)
                            <span class="inline-flex items-center gap-1 rounded-lg bg-orange-50 border border-orange-200 px-2.5 py-1 text-xs font-semibold text-brand-orange">
                                <span>{{ $catActiva->nombre }}</span>
                                <button type="button" wire:click="$set('categoriaId', '')" class="hover:text-red-700">✕</button>
                            </span>
                        @endif
                    @endif

                    @if($marcaId)
                        @php
                            $marcaActiva = $marcas->firstWhere('id', $marcaId);
                        @endphp
                        @if($marcaActiva)
                            <span class="inline-flex items-center gap-1 rounded-lg bg-orange-50 border border-orange-200 px-2.5 py-1 text-xs font-semibold text-brand-orange">
                                <span>Marca: {{ $marcaActiva->nombre }}</span>
                                <button type="button" wire:click="$set('marcaId', '')" class="hover:text-red-700">✕</button>
                            </span>
                        @endif
                    @endif

                    @if($solo3D)
                        <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 border border-blue-200 px-2.5 py-1 text-xs font-semibold text-brand-blue">
                            <span>🧊 Solo con 3D</span>
                            <button type="button" wire:click="$set('solo3D', false)" class="hover:text-red-700">✕</button>
                        </span>
                    @endif

                    @if($precioMin || $precioMax)
                        <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                            <span>Bs. {{ $precioMin ?: 0 }} - {{ $precioMax ?: '∞' }}</span>
                            <button type="button" wire:click="$set('precioMin', ''); $set('precioMax', '')" class="hover:text-red-700">✕</button>
                        </span>
                    @endif
                </div>

                <!-- SELECTOR DE ORDEN -->
                <div class="flex items-center gap-2 self-end sm:self-auto shrink-0">
                    <label for="orden_select" class="text-xs font-semibold text-slate-500">Ordenar por:</label>
                    <select
                        id="orden_select"
                        wire:model.live="orden"
                        class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                    >
                        <option value="recientes">Más recientes</option>
                        <option value="precio_asc">Precio: Menor a Mayor</option>
                        <option value="precio_desc">Precio: Mayor a Menor</option>
                        <option value="nombre_asc">Nombre: A a la Z</option>
                    </select>
                </div>
            </div>

            <!-- GRILLA DE TARJETAS DE JUGUETES -->
            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($productos as $producto)
                    @php
                        $imagen = $producto->imagenes->sortBy('orden')->first()?->ruta_archivo;
                        $tiene3D = $producto->modelos3D->whereNotNull('modelo_glb_path')->isNotEmpty();
                        $precio = $producto->precio_venta !== null ? (float) $producto->precio_venta : null;
                    @endphp
                    <article class="group relative flex flex-col rounded-3xl border border-slate-200/90 bg-white p-4 shadow-sm hover:shadow-xl hover:border-brand-orange/50 transition duration-300">
                        <!-- ENLACE DE LA CARTA COMPLETA -->
                        <a
                            href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}"
                            class="absolute inset-0 z-10"
                            wire:navigate
                            aria-label="Ver detalles de {{ $producto->nombre }}"
                        ></a>

                        <!-- FOTOGRAFÍA (SOLO LA PRIMERA IMAGEN) Y BADGES -->
                        <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-100">
                            @if($imagen)
                                <img
                                    src="{{ asset('storage/' . ltrim($imagen, '/')) }}"
                                    alt="{{ $producto->nombre }}"
                                    class="size-full object-cover group-hover:scale-105 transition duration-500"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                >
                                <div style="display:none;" class="size-full place-items-center text-6xl">🧸</div>
                            @else
                                <div class="grid size-full place-items-center text-6xl">🧸</div>
                            @endif

                            <!-- BADGE DE MODELO 3D -->
                            @if($tiene3D)
                                <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-slate-900/85 px-2.5 py-0.5 text-[10px] font-bold text-white shadow-sm backdrop-blur-sm pointer-events-none">
                                    <span>🧊</span> 3D
                                </span>
                            @endif

                            <!-- EDAD RECOMENDADA -->
                            @if($producto->edad_recomendada)
                                <span class="absolute bottom-2.5 right-2.5 rounded-full bg-white/90 px-2.5 py-0.5 text-[10px] font-bold text-slate-700 shadow-sm backdrop-blur-sm pointer-events-none">
                                    +{{ $producto->edad_recomendada }} años
                                </span>
                            @endif
                        </div>

                        <!-- CUERPO DE LA TARJETA -->
                        <div class="mt-4 flex flex-1 flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-[.18em] text-brand-orange">
                                    <span>{{ $producto->categoria?->nombre ?? 'Juguete' }}</span>
                                    @if($producto->marca)
                                        <span class="text-slate-500 font-semibold normal-case tracking-normal">{{ $producto->marca->nombre }}</span>
                                    @endif
                                </div>
                                <h2 class="mt-1 text-sm font-bold text-brand-ink line-clamp-2 group-hover:text-brand-orange transition">
                                    {{ $producto->nombre }}
                                </h2>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Cód: <span class="font-mono font-bold text-brand-ink">{{ $producto->codigo_producto }}</span>
                                </p>
                            </div>

                            <!-- PRECIO DESTACADO E INFORMACIÓN -->
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                <div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Precio</span>
                                    @if($precio !== null)
                                        <span class="text-lg font-bold text-brand-orange">
                                            Bs. {{ number_format($precio, 2) }}
                                        </span>
                                    @else
                                        <span class="text-xs font-semibold text-slate-400">
                                            Precio por confirmar
                                        </span>
                                    @endif
                                </div>

                                <div class="relative z-20">
                                    <span class="inline-flex items-center gap-1 rounded-xl bg-orange-50 px-3 py-1.5 text-xs font-bold text-brand-orange group-hover:bg-brand-orange group-hover:text-white transition shadow-sm">
                                        Ver Info →
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-400">
                        <span class="text-5xl">🔍</span>
                        <h3 class="mt-3 text-base font-bold text-slate-700">No encontramos juguetes con esos criterios</h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Prueba ajustando los filtros de búsqueda, categoría o rango de precios.
                        </p>
                        <button
                            type="button"
                            wire:click="limpiarFiltros"
                            class="mt-4 rounded-xl bg-brand-orange px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-orange-600 transition"
                        >
                            Ver todos los juguetes
                        </button>
                    </div>
                @endforelse
            </div>

            <!-- PAGINACIÓN -->
            <div class="mt-6">
                {{ $productos->links() }}
            </div>
        </section>
    </div>
</div>
