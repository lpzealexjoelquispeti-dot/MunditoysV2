<div class="relative inline-flex items-center" wire:poll.30s="actualizarConteo">
    <a
        href="{{ route('tienda.seguimiento', ['vista' => 'notificaciones']) }}"
        wire:navigate
        class="relative inline-flex items-center gap-1 rounded-full border border-slate-200 p-2 text-brand-blue transition hover:border-brand-blue/40 hover:bg-blue-50"
        aria-label="Mis notificaciones{{ $noLeidas > 0 ? ', '.$noLeidas.' sin leer' : '' }}"
        title="Mis notificaciones"
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        <span class="hidden md:inline text-xs font-semibold">Notificaciones</span>

        @if ($noLeidas > 0)
            <span class="absolute -right-1.5 -top-1.5 grid min-w-5 place-items-center rounded-full bg-brand-red px-1 text-[10px] font-bold leading-5 text-white ring-2 ring-white">
                {{ $noLeidas > 9 ? '9+' : $noLeidas }}
            </span>
        @endif
    </a>
</div>
