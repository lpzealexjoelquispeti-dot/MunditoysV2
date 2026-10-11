<div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1">
    <button type="button" wire:click="decrementar({{ $producto->id }})" class="grid size-7 place-items-center rounded-lg bg-white text-slate-700 transition hover:bg-slate-100 active:scale-95" aria-label="Quitar uno">
        <x-lucide-minus />
    </button>
    <span class="w-8 text-center text-sm font-semibold text-slate-900">{{ $item['cantidad'] }}</span>
    <button type="button" wire:click="incrementar({{ $producto->id }})" class="grid size-7 place-items-center rounded-lg bg-white text-slate-700 transition hover:bg-slate-100 active:scale-95" aria-label="Agregar uno">
        <x-lucide-plus />
    </button>
</div>
