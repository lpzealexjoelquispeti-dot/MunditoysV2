<div class="mx-auto max-w-[1700px]">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-[.2em] text-brand-orange">Catálogo interno</p><h1 class="mt-1 text-3xl font-bold text-brand-ink sm:text-4xl">{{ $titulo }}</h1><p class="mt-1 text-sm text-slate-600">Administra la información e imágenes de {{ mb_strtolower($titulo) }}.</p></div>
        <button type="button" wire:click="crear" class="min-h-11 rounded-xl bg-brand-red px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-red-dark">＋ Nueva {{ $singular }}</button>
    </header>
    <section class="mt-6 rounded-2xl border border-orange-100 bg-white p-5 shadow-sm" aria-label="Filtros">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="md:col-span-2"><x-admin-field label="Buscar" name="buscar"><input wire:model.live.debounce.350ms="buscar" type="search" maxlength="100" placeholder="Nombre o descripción" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field></div>
            <x-admin-field label="Estado" name="filtroEstado"><select wire:model.live="filtroEstado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="activo">Activas</option><option value="inactivo">Inactivas</option><option value="todos">Activas e inactivas</option><option value="eliminados">Papelera</option></select></x-admin-field>
            <x-admin-field label="Ordenar por" name="orden"><select wire:model.live="orden" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="recientes">Más recientes</option><option value="antiguos">Más antiguas</option><option value="nombre_asc">Nombre A–Z</option><option value="nombre_desc">Nombre Z–A</option></select></x-admin-field>
        </div>
        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm"><p class="text-slate-500"><strong class="text-brand-ink">{{ $registros->total() }}</strong> registros encontrados</p><button type="button" wire:click="limpiarFiltros" class="rounded-lg px-3 py-2 font-semibold text-brand-blue hover:bg-blue-50">Limpiar filtros</button></div>
    </section>
    <p wire:loading wire:target="buscar,filtroEstado,orden,eliminar,restaurar" class="mt-3 text-sm text-brand-blue" role="status">Actualizando listado…</p>
    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($registros as $registro)
            <article wire:key="registro-{{ $registro->id }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                <div class="relative aspect-[4/3] bg-gradient-to-br from-orange-50 via-white to-blue-50">
                    @if ($registro->imagen)<img src="{{ asset('storage/'.$registro->imagen) }}" alt="Imagen de {{ $registro->nombre }}" class="h-full w-full object-contain p-3" loading="lazy">@else<div class="grid h-full place-items-center text-6xl" aria-label="Sin imagen">{{ $icono }}</div>@endif
                    <span @class(['absolute left-3 top-3 rounded-full px-2.5 py-1 text-xs font-bold shadow-sm', 'bg-emerald-100 text-emerald-800' => !$registro->trashed() && $registro->estado, 'bg-slate-200 text-slate-700' => !$registro->trashed() && !$registro->estado, 'bg-red-100 text-brand-red' => $registro->trashed()])>{{ $registro->trashed() ? 'En papelera' : ($registro->estado ? 'Activa' : 'Inactiva') }}</span>
                </div>
                <div class="p-4"><h2 class="text-lg font-bold text-brand-ink">{{ $registro->nombre }}</h2><p class="mt-2 line-clamp-2 min-h-10 text-sm text-slate-600">{{ $registro->descripcion ?: 'Sin descripción' }}</p><p class="mt-3 text-xs text-slate-500">{{ $registro->productos_count }} productos asociados</p>
                    <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4" x-data>
                        <button type="button" wire:click="verDetalle({{ $registro->id }})" class="flex-1 rounded-xl bg-orange-50 px-3 py-2.5 text-sm font-bold text-brand-orange">Detalles</button>
                        @if ($registro->trashed())
                            <button type="button" x-on:click="window.confirmarCrud($wire, 'restaurar', {{ $registro->id }}, '¿Restaurar esta {{ $singular }}?', 'Volverá a estar disponible con su información conservada.')" class="flex-1 rounded-xl bg-brand-blue px-3 py-2.5 text-sm font-bold text-white">Restaurar</button>
                        @else
                            <button type="button" wire:click="editar({{ $registro->id }})" class="rounded-xl bg-blue-50 px-3 py-2.5 text-sm font-bold text-brand-blue">Editar</button>
                            <button type="button" x-on:click="window.confirmarCrud($wire, 'eliminar', {{ $registro->id }}, '¿Enviar esta {{ $singular }} a la papelera?', 'No se eliminará definitivamente. No se permite si tiene productos asociados.')" class="rounded-xl bg-red-50 px-3 py-2.5 text-sm font-bold text-brand-red">Eliminar</button>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border-2 border-dashed border-orange-200 bg-white px-6 py-16 text-center"><span class="text-5xl">{{ $icono }}</span><h2 class="mt-4 text-xl font-bold text-brand-ink">No hay registros para mostrar</h2><p class="mt-2 text-sm text-slate-500">Ajusta los filtros o registra una nueva {{ $singular }}.</p></div>
        @endforelse
    </div>
    @if ($registros->hasPages())<div class="mt-7 rounded-xl bg-white px-4 py-3 shadow-sm">{{ $registros->links() }}</div>@endif
</div>
