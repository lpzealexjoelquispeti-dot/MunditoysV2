<div class="relative inline-flex items-center">
    <a
        id="navbar-carrito-btn"
        href="{{ route('tienda.carrito') }}"
        class="relative inline-flex items-center gap-2 rounded-2xl border border-orange-200/80 bg-orange-50/80 px-3.5 py-2 text-xs font-semibold text-brand-orange shadow-sm transition hover:bg-brand-orange hover:text-white group"
        title="Ver carrito de compras"
        wire:navigate
    >
        <span class="text-base group-hover:scale-110 transition-transform duration-200"><x-lucide-shopping-cart /></span>
        <span class="hidden sm:inline">Carrito</span>
        <span
            id="navbar-carrito-badge"
            @class([
                'grid size-5 place-items-center rounded-full text-[10px] font-bold transition-all duration-300',
                'bg-brand-orange text-white group-hover:bg-white group-hover:text-brand-orange' => $conteo > 0,
                'bg-slate-200 text-slate-500' => $conteo === 0,
            ])
        >
            {{ $conteo }}
        </span>
    </a>
</div>

