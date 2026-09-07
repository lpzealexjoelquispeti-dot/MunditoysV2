<x-admin-shell active="ventas">
    <h1 class="text-2xl font-bold text-slate-900">Ventas</h1>
    <div class="mt-6 overflow-x-auto rounded-lg bg-white shadow"><table class="min-w-full divide-y divide-slate-200 text-left text-sm"><thead class="bg-slate-50"><tr><th class="px-4 py-3">Número</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3">Total</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse ($ventas as $venta)<tr><td class="px-4 py-3">{{ $venta->numero_venta }}</td><td class="px-4 py-3">{{ $venta->cliente?->name ?? 'Venta sin cliente' }}</td><td class="px-4 py-3">{{ $venta->estado }}</td><td class="px-4 py-3">{{ number_format((float) $venta->total, 2) }}</td></tr>@empty<tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No hay ventas registradas.</td></tr>@endforelse</tbody></table></div>
    <div class="mt-4">{{ $ventas->links() }}</div>
</x-admin-shell>
