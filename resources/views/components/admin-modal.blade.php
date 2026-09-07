@props(['property', 'close', 'title', 'wide' => false])
<div x-data x-show="$wire.{{ $property }}" x-cloak x-on:keydown.escape.window="if ($wire.{{ $property }}) $wire.{{ $close }}()" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-{{ $property }}-titulo">
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
        <button type="button" class="fixed inset-0 bg-brand-ink/60 backdrop-blur-sm" x-on:click="$wire.{{ $close }}()" aria-label="Cerrar ventana"></button>
        <section @class(['relative w-full overflow-hidden rounded-3xl bg-white shadow-2xl', 'max-w-5xl' => $wide, 'max-w-xl' => !$wide])>
            <header class="flex items-center justify-between gap-4 border-b border-slate-100 bg-gradient-to-r from-orange-50 to-blue-50 px-6 py-4">
                <div><p class="text-xs font-bold uppercase tracking-[.18em] text-brand-orange">MundiToys · Administración</p><h2 id="modal-{{ $property }}-titulo" class="mt-1 text-xl font-bold text-brand-ink">{{ $title }}</h2></div>
                <button type="button" wire:click="{{ $close }}" class="grid size-10 shrink-0 place-items-center rounded-full bg-white text-xl text-slate-500 hover:text-brand-red" aria-label="Cerrar">×</button>
            </header>
            <div class="max-h-[calc(100dvh-11rem)] overflow-y-auto p-5 sm:p-6">{{ $slot }}</div>
        </section>
    </div>
</div>
