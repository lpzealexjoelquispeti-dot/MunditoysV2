<div>
    <h1 class="text-2xl font-semibold text-slate-900">Panel administrativo</h1>
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-slate-500">Productos</p><p class="mt-2 text-3xl font-semibold">{{ $totalProductos }}</p></div>
        <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-slate-500">Registros de inventario</p><p class="mt-2 text-3xl font-semibold">{{ $totalInventarios }}</p></div>
        <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-slate-500">Ventas pendientes</p><p class="mt-2 text-3xl font-semibold">{{ $ventasPendientes }}</p></div>
    </div>
</div>
