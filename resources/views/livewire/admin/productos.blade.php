<x-admin-shell active="productos">
    <div class="mx-auto max-w-[1700px]" data-product-manager>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>

                <h1 class="text-3xl font-semibold text-brand-ink sm:text-4xl">Productos</h1>
            </div>
            @can('crear productos')
            <button type="button" wire:click="abrirCrear" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-red-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-red">
                <x-lucide-plus /> Nuevo producto
            </button>
            @endcan
        </div>

        @if (session('status'))
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                <span aria-hidden="true"><x-lucide-check /></span><span>{{ session('status') }}</span>
            </div>
        @endif

        <section class="mt-6 rounded-2xl border border-orange-100 bg-white p-4 shadow-sm sm:p-5" aria-label="Filtros de productos">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <label class="xl:col-span-2 2xl:col-span-2">
                    <span class="mb-1.5 block text-xs font-semibold text-slate-600">Buscar producto</span>
                    <span class="relative block">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400"><x-lucide-search /></span>
                        <input wire:model.live.debounce.350ms="buscar" type="search" maxlength="100" placeholder="Nombre, código automático o QR" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-brand-ink placeholder:text-slate-400">
                    </span>
                    @error('buscar') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-slate-600">Categoría</span>
                    <select wire:model.live="filtroCategoria" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-brand-ink">
                        <option value="">Todas</option>
                        @foreach ($categorias as $categoria)
                            <option value="{{ $categoria['id'] }}">{{ $categoria['nombre'] }}</option>
                        @endforeach
                    </select>
                    @error('filtroCategoria') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-slate-600">Edad recomendada</span>
                    <select wire:model.live="filtroEdad" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-brand-ink">
                        <option value="">Todas las edades</option>
                        @foreach ($rangosEdad as $rango)
                            <option value="{{ $rango }}">{{ $rango }} años</option>
                        @endforeach
                    </select>
                    @error('filtroEdad') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-slate-600">Estado</span>
                    <select wire:model.live="filtroEstado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-brand-ink">
                        <option value="activo">Activos</option>
                        <option value="inactivo">Inactivos</option>
                        <option value="todos">Activos e inactivos</option>
                        <option value="eliminados">Papelera</option>
                    </select>
                </label>

                <label>
                    <span class="mb-1.5 block text-xs font-semibold text-slate-600">Ordenar por</span>
                    <select wire:model.live="orden" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-brand-ink">
                        <option value="recientes">Más recientes</option>
                        <option value="antiguos">Más antiguos</option>
                        <option value="nombre_asc">Nombre A–Z</option>
                        <option value="nombre_desc">Nombre Z–A</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <p class="text-sm text-slate-500"><span class="font-semibold text-brand-ink">{{ $productos->total() }}</span> {{ $productos->total() === 1 ? 'producto encontrado' : 'productos encontrados' }}</p>
                <button type="button" wire:click="limpiarFiltros" class="rounded-lg px-3 py-2 text-sm font-semibold text-brand-blue transition hover:bg-blue-50">Limpiar filtros</button>
            </div>
        </section>

        <div class="relative mt-6">
            <div wire:loading.flex wire:target="buscar,filtroCategoria,filtroEdad,filtroEstado,orden,eliminar,restaurar" class="absolute inset-0 z-10 items-start justify-center rounded-2xl bg-brand-surface/80 pt-16 backdrop-blur-[1px]">
                <span class="rounded-full bg-brand-blue px-4 py-2 text-sm font-semibold text-white shadow">Actualizando catálogo…</span>
            </div>

            <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                @forelse ($productos as $producto)
                    @php($imagenPrincipal = $producto->imagenes->sortByDesc('principal')->first())
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" wire:key="producto-{{ $producto->id }}">
                        <div class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-orange-50 via-white to-blue-50">
                            @if ($imagenPrincipal)
                                <img src="{{ asset('storage/'.$imagenPrincipal->ruta_archivo) }}" alt="Imagen de {{ $producto->nombre }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                            @else
                                <div class="grid h-full place-items-center text-center text-slate-400">
                                    <div><span class="text-6xl" aria-hidden="true"><x-lucide-toy-brick class="text-slate-300" /></span><p class="mt-2 text-xs font-semibold">Sin imagen</p></div>
                                </div>
                            @endif

                            <span @class([
                                'absolute left-3 top-3 rounded-full px-2.5 py-1 text-xs font-semibold shadow-sm',
                                'bg-emerald-100 text-emerald-800' => !$producto->trashed() && $producto->estado === 'activo',
                                'bg-slate-200 text-slate-700' => !$producto->trashed() && $producto->estado !== 'activo',
                                'bg-red-100 text-brand-red-dark' => $producto->trashed(),
                            ])>{{ $producto->trashed() ? 'En papelera' : ucfirst($producto->estado) }}</span>
                            <span class="absolute right-3 top-3 rounded-full bg-brand-blue px-2.5 py-1 text-xs font-semibold text-white shadow-sm">{{ $producto->edad_recomendada }} años</span>
                        </div>

                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-brand-orange">{{ $producto->categoria->nombre }}</p>
                                    <h2 class="mt-1 line-clamp-2 text-lg font-semibold leading-snug text-brand-ink">{{ $producto->nombre }}</h2>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Código: <span class="font-semibold text-slate-700">{{ $producto->codigo_producto }}</span> · {{ $producto->marca?->nombre ?? 'Sin marca' }}</p>
                            <p class="mt-3 line-clamp-2 min-h-10 text-sm leading-5 text-slate-600">{{ $producto->descripcion ?: 'Este producto todavía no tiene una descripción.' }}</p>

                            <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                                @if ($producto->trashed())
                                    <button type="button" wire:click="verDetalle({{ $producto->id }})" class="rounded-xl bg-orange-50 px-3 py-2.5 text-sm font-semibold text-brand-orange transition hover:bg-orange-100">Detalles</button>
                                    @can('editar productos')
                                    <button type="button" x-on:click="window.confirmarCrud($wire, 'restaurar', {{ $producto->id }}, '¿Restaurar este producto?', 'El juguete volverá a estar disponible conservando su historial.')" class="flex-1 rounded-xl bg-brand-blue px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-blue-dark">Restaurar</button>
                                    @endcan
                                @else
                                    <button type="button" wire:click="verDetalle({{ $producto->id }})" class="flex-1 rounded-xl bg-orange-50 px-3 py-2.5 text-sm font-semibold text-brand-orange transition hover:bg-orange-100">Detalles</button>
                                    @can('editar productos')
                                    <button type="button" wire:click="editar({{ $producto->id }})" class="rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-semibold text-brand-blue transition hover:bg-blue-100">Editar</button>
                                    @endcan
                                    @can('eliminar productos')
                                    <button 
type="button"
wire:click="confirmarEliminar({{ $producto->id }})"
class="rounded-xl bg-red-50 px-3 py-2.5 text-sm font-semibold text-brand-red transition hover:bg-red-100">

Eliminar

</button>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-2xl border-2 border-dashed border-orange-200 bg-white px-6 py-16 text-center">
                        <div class="mx-auto grid size-20 place-items-center rounded-full bg-brand-orange-soft text-4xl" aria-hidden="true"><x-lucide-toy-brick class="text-brand-orange" /></div>
                        <p class="mt-4 font-semibold text-slate-700">{{ $filtroEstado === 'eliminados' ? 'Papelera vacía' : 'Sin productos' }}</p>
                        @if ($filtroEstado !== 'eliminados' && auth()->user()->can('crear productos'))
                            <button type="button" wire:click="abrirCrear" class="mt-5 rounded-xl bg-brand-red px-5 py-3 text-sm font-semibold text-white hover:bg-brand-red-dark">Registrar producto</button>
                        @endif
                    </div>
                @endforelse
            </div>
        </div>

        @if ($productos->hasPages())
            <div class="mt-7 rounded-xl bg-white px-4 py-3 shadow-sm">{{ $productos->links() }}</div>
        @endif
    </div>

    @canany(['crear productos', 'editar productos'])
        @include('livewire.admin.partials.producto-form-modal')
    @endcanany
    @include('livewire.admin.partials.producto-detail-modal')
</x-admin-shell>
