<div>
    <h1 class="text-2xl font-bold text-slate-900">Catálogo</h1>
    <input wire:model.live.debounce.300ms="buscar" type="search" placeholder="¿Qué juguete buscas?" class="mt-6 w-full rounded-md border-slate-300 sm:w-96">
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">@forelse ($productos as $producto)<article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm"><p class="text-sm text-slate-500">{{ $producto->categoria->nombre }}</p><h2 class="mt-1 font-semibold">{{ $producto->nombre }}</h2><p class="mt-3 text-lg font-semibold">{{ $producto->precio_venta === null ? 'Precio por confirmar' : number_format((float) $producto->precio_venta, 2) }}</p></article>@empty<p class="text-slate-500">No encontramos productos.</p>@endforelse</div>
    <div class="mt-4">{{ $productos->links() }}</div>
</div>
