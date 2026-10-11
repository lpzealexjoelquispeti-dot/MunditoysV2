<div class="space-y-6">
    <!-- ENCABEZADO -->
    <div class="flex items-end justify-between gap-4">
        <div>
            <nav class="flex items-center gap-2 text-sm text-slate-400" aria-label="Migas de pan">
                <a href="{{ route('welcome') }}" class="transition hover:text-brand-orange">Inicio</a>
                <span>/</span>
                <span class="text-slate-600">Catálogo</span>
            </nav>
            <h1 class="mt-1 text-2xl font-bold text-brand-ink sm:text-3xl">Catálogo de Juguetes</h1>
        </div>
        <p class="shrink-0 text-sm text-slate-500">{{ $productos->total() }} {{ $productos->total() === 1 ? 'juguete' : 'juguetes' }}</p>
    </div>

    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
        <!-- FILTROS -->
        <aside>
            <div class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="flex items-center gap-2 font-semibold text-slate-900"><x-lucide-funnel /> Filtros</p>
                    <button type="button" wire:click="limpiarFiltros" class="text-sm font-semibold text-brand-orange hover:underline">Limpiar</button>
                </div>

                <label class="relative block">
                    <span class="sr-only">Buscar juguete</span>
                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="buscar"
                        placeholder="Buscar juguete"
                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange"
                    >
                </label>

                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-blue-100 bg-blue-50/60 p-3 transition hover:bg-blue-50">
                    <input type="checkbox" wire:model.live="solo3D" class="size-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue">
                    <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-800"><x-lucide-box class="text-brand-blue" /> Con vista 3D</span>
                </label>

                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Categoría</p>
                    <div class="max-h-52 space-y-0.5 overflow-y-auto pr-1">
                        <button
                            type="button"
                            wire:click="$set('categoriaId', '')"
                            @class([
                                'flex w-full items-center justify-between rounded-xl px-3 py-1.5 text-left text-sm transition',
                                'bg-brand-orange font-semibold text-white' => $categoriaId === '',
                                'text-slate-600 hover:bg-slate-100' => $categoriaId !== '',
                            ])
                        >
                            Todas
                        </button>
                        @foreach($categorias as $cat)
                            <button
                                type="button"
                                wire:click="$set('categoriaId', '{{ $cat->id }}')"
                                @class([
                                    'flex w-full items-center justify-between rounded-xl px-3 py-1.5 text-left text-sm transition',
                                    'bg-brand-orange font-semibold text-white' => (string) $categoriaId === (string) $cat->id,
                                    'text-slate-600 hover:bg-slate-100' => (string) $categoriaId !== (string) $cat->id,
                                ])
                            >
                                <span class="truncate">{{ $cat->nombre }}</span>
                                <span class="text-xs opacity-70">{{ $cat->productos_count }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label for="marca_select" class="mb-2 block text-sm font-semibold text-slate-700">Marca</label>
                    <select id="marca_select" wire:model.live="marcaId" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange">
                        <option value="">Todas</option>
                        @foreach($marcas as $marca)
                            <option value="{{ $marca->id }}">{{ $marca->nombre }} ({{ $marca->productos_count }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Edad</p>
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
                                    'rounded-xl border px-2 py-1.5 text-center text-sm transition',
                                    'border-slate-900 bg-slate-900 font-semibold text-white' => (string) $edad === (string) $valorEdad,
                                    'border-slate-200 text-slate-600 hover:bg-slate-50' => (string) $edad !== (string) $valorEdad,
                                ])
                            >
                                {{ $etiquetaEdad }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-700">Precio (Bs.)</p>
                    <div class="flex items-center gap-2">
                        <input type="number" wire:model.live.debounce.400ms="precioMin" placeholder="Mín." min="0" aria-label="Precio mínimo" class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange">
                        <span class="text-slate-400">–</span>
                        <input type="number" wire:model.live.debounce.400ms="precioMax" placeholder="Máx." min="0" aria-label="Precio máximo" class="w-full rounded-xl border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange">
                    </div>
                </div>
            </div>
        </aside>

        <!-- RESULTADOS -->
        <section class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-1.5">
                    @if($buscar)
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-sm text-brand-orange">
                            "{{ $buscar }}"
                            <button type="button" wire:click="$set('buscar', '')" class="hover:text-red-700" aria-label="Quitar búsqueda"><x-lucide-x /></button>
                        </span>
                    @endif

                    @if($categoriaId && ($catActiva = $categorias->firstWhere('id', $categoriaId)))
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-sm text-brand-orange">
                            {{ $catActiva->nombre }}
                            <button type="button" wire:click="$set('categoriaId', '')" class="hover:text-red-700" aria-label="Quitar categoría"><x-lucide-x /></button>
                        </span>
                    @endif

                    @if($marcaId && ($marcaActiva = $marcas->firstWhere('id', $marcaId)))
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-sm text-brand-orange">
                            {{ $marcaActiva->nombre }}
                            <button type="button" wire:click="$set('marcaId', '')" class="hover:text-red-700" aria-label="Quitar marca"><x-lucide-x /></button>
                        </span>
                    @endif

                    @if($solo3D)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-sm text-brand-blue">
                            <x-lucide-box /> 3D
                            <button type="button" wire:click="$set('solo3D', false)" class="hover:text-red-700" aria-label="Quitar filtro 3D"><x-lucide-x /></button>
                        </span>
                    @endif

                    @if($precioMin || $precioMax)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-800">
                            Bs. {{ $precioMin ?: 0 }} – {{ $precioMax ?: '∞' }}
                            <button type="button" wire:click="$set('precioMin', ''); $set('precioMax', '')" class="hover:text-red-700" aria-label="Quitar precio"><x-lucide-x /></button>
                        </span>
                    @endif
                </div>

                <label class="flex shrink-0 items-center gap-2 self-end sm:self-auto">
                    <span class="sr-only">Ordenar</span>
                    <x-lucide-arrow-up-down class="text-slate-400" />
                    <select wire:model.live="orden" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 focus:border-brand-orange focus:ring-1 focus:ring-brand-orange">
                        <option value="recientes">Más recientes</option>
                        <option value="precio_asc">Menor precio</option>
                        <option value="precio_desc">Mayor precio</option>
                        <option value="nombre_asc">Nombre A–Z</option>
                    </select>
                </label>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @forelse($productos as $producto)
                    @php
                        $imagen = $producto->imagenes->sortBy('orden')->first()?->ruta_archivo;
                        $tiene3D = $producto->modelos3D->whereNotNull('modelo_glb_path')->isNotEmpty();
                        $precio = $producto->precio_venta !== null ? (float) $producto->precio_venta : null;
                    @endphp
                    <a
                        href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}"
                        wire:navigate
                        class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-3 shadow-sm transition duration-300 hover:border-brand-orange/50 hover:shadow-lg"
                    >
                        <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-100">
                            @if($imagen)
                                <img
                                    src="{{ asset('storage/' . ltrim($imagen, '/')) }}"
                                    alt="{{ $producto->nombre }}"
                                    class="size-full object-cover transition duration-500 group-hover:scale-105"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                >
                                <div style="display:none;" class="size-full place-items-center text-6xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                            @else
                                <div class="grid size-full place-items-center text-6xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                            @endif

                            @if($tiene3D)
                                <span class="absolute left-2.5 top-2.5 inline-flex items-center gap-1 rounded-full bg-slate-900/85 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur-sm">
                                    <x-lucide-box /> 3D
                                </span>
                            @endif

                            @if($producto->edad_recomendada)
                                <span class="absolute bottom-2.5 right-2.5 rounded-full bg-white/90 px-2.5 py-0.5 text-xs font-semibold text-slate-700 backdrop-blur-sm">
                                    +{{ $producto->edad_recomendada }} años
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-1 flex-col px-1 pb-1 pt-3">
                            <p class="text-xs text-slate-500">{{ $producto->categoria?->nombre ?? 'Juguete' }}@if($producto->marca) · {{ $producto->marca->nombre }}@endif</p>
                            <h2 class="mt-0.5 line-clamp-2 font-semibold text-brand-ink transition group-hover:text-brand-orange">{{ $producto->nombre }}</h2>
                            <p class="mt-auto pt-3 text-lg font-bold text-brand-orange">
                                {{ $precio !== null ? 'Bs. '.number_format($precio, 2) : 'Precio por confirmar' }}
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center">
                        <x-lucide-search class="mx-auto text-4xl text-slate-300" />
                        <p class="mt-3 font-semibold text-slate-700">Sin resultados</p>
                        <button type="button" wire:click="limpiarFiltros" class="mt-4 rounded-xl bg-brand-orange px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-600">
                            Limpiar filtros
                        </button>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">{{ $productos->links() }}</div>
        </section>
    </div>
</div>
