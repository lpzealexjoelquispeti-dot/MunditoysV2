<div x-data="{ abierto: $persist(false).as('asistente-abierto-{{ $canal }}') }" class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3">
    <section
        x-show="abierto"
        x-transition.origin.bottom.right
        x-cloak
        class="flex h-[min(620px,calc(100vh-7rem))] w-[min(400px,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
        aria-label="Asistente virtual"
    >
        <header @class(['flex items-center justify-between gap-3 px-5 py-4 text-white', 'bg-brand-blue' => $canal === 'admin', 'bg-brand-orange' => $canal !== 'admin'])>
            <div class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-2xl bg-white/20 text-lg"><x-lucide-bot /></span>
                <div>
                    <p class="font-semibold leading-tight">Mundi</p>
                    <p class="text-xs text-white/80">{{ $canal === 'admin' ? 'Asistente del negocio' : 'Tu asistente de compras' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                @if ($mensajes !== [])
                    <button type="button" wire:click="nuevaConversacion" class="rounded-lg px-2 py-1 text-xs font-semibold text-white/90 hover:bg-white/15" title="Empezar una conversación nueva">Nueva</button>
                @endif
                <button type="button" x-on:click="abierto = false" class="rounded-lg px-2 py-1 text-lg leading-none hover:bg-white/15" aria-label="Cerrar asistente"><x-lucide-x /></button>
            </div>
        </header>

        <div
            class="flex-1 space-y-3 overflow-y-auto bg-brand-surface px-4 py-4"
            x-init="new MutationObserver(() => $el.scrollTop = $el.scrollHeight).observe($el, { childList: true, subtree: true }); $el.scrollTop = $el.scrollHeight"
        >
            @if ($mensajes === [])
                <div class="rounded-2xl bg-white p-4 text-sm text-slate-600 shadow-xs">
                    <p class="font-semibold text-brand-ink">¡Hola! Soy Mundi</p>
                    <p class="mt-1">
                        {{ $canal === 'admin'
                            ? 'Consulta tus operaciones o pide un reporte.'
                            : 'Dime la edad, gustos y presupuesto, y te recomiendo juguetes.' }}
                    </p>
                </div>
                <div class="flex flex-col gap-2">
                    @foreach ($sugerencias as $sugerencia)
                        <button type="button" wire:key="sugerencia-{{ $loop->index }}" wire:click="usarSugerencia(@js($sugerencia))"
                            class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-left text-sm text-slate-700 hover:border-brand-orange hover:text-brand-orange">
                            {{ $sugerencia }}
                        </button>
                    @endforeach
                </div>
            @endif

            @foreach ($mensajes as $mensaje)
                <div wire:key="mensaje-{{ $loop->index }}" @class(['flex', 'justify-end' => $mensaje['rol'] === 'user'])>
                    @if ($mensaje['rol'] === 'user')
                        <p class="max-w-[85%] rounded-2xl rounded-br-md bg-brand-blue px-4 py-2.5 text-sm text-white">{{ $mensaje['texto'] }}</p>
                    @else
                        <div class="max-w-[90%] space-y-2">
                            <div @class([
                                'rounded-2xl rounded-bl-md px-4 py-2.5 text-sm leading-relaxed shadow-xs [&_p]:my-1 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_strong]:font-semibold [&_a]:text-brand-orange [&_a]:underline',
                                'bg-white text-brand-ink' => empty($mensaje['error']),
                                'bg-red-50 text-red-700' => ! empty($mensaje['error']),
                            ])>
                                {!! Str::markdown($mensaje['texto'], ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}
                            </div>
                            @if (! empty($mensaje['reporte_mensaje_id']))
                                <button type="button" wire:click="descargarReporte({{ $mensaje['reporte_mensaje_id'] }})"
                                    wire:loading.attr="disabled" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-brand-blue hover:border-brand-blue">
                                    <x-lucide-download /> Descargar reporte
                                </button>
                            @endif
                            @if (! empty($mensaje['puede_valorar']) && ! empty($mensaje['mensaje_id']))
                                <div class="flex gap-2 text-xs text-slate-500">
                                    @if (array_key_exists('valoracion', $mensaje))
                                        <span>Valoración guardada</span>
                                    @else
                                        <button type="button" wire:click="valorarRespuesta({{ $mensaje['mensaje_id'] }}, true)" class="rounded-lg px-2 py-1 hover:bg-white hover:text-brand-blue">Útil</button>
                                        <button type="button" wire:click="valorarRespuesta({{ $mensaje['mensaje_id'] }}, false)" class="rounded-lg px-2 py-1 hover:bg-white hover:text-brand-blue">No útil</button>
                                    @endif
                                </div>
                            @endif
                            @foreach ($mensaje['productos'] ?? [] as $producto)
                                <a href="{{ $producto['url'] }}" wire:navigate wire:key="producto-{{ $loop->parent->index }}-{{ $producto['id'] }}"
                                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-2 pr-3 hover:border-brand-orange">
                                    @if ($producto['imagen'])
                                        <img src="{{ $producto['imagen'] }}" alt="{{ $producto['nombre'] }}" class="size-14 rounded-xl object-cover">
                                    @else
                                        <span class="grid size-14 place-items-center rounded-xl bg-slate-100 text-xl"><x-lucide-toy-brick class="text-slate-300" /></span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-brand-ink">{{ $producto['nombre'] }}</span>
                                        <span class="block text-xs text-slate-500">{{ $producto['edad'] ? $producto['edad'].' años · ' : '' }}Ver producto</span>
                                    </span>
                                    <span class="text-sm font-semibold text-brand-orange">Bs {{ number_format($producto['precio'], 2) }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach

            @if ($pendiente !== null)
                {{-- Mientras el modelo escribe, el texto llega por wire:stream y reemplaza el indicador. --}}
                <div class="max-w-[90%] rounded-2xl rounded-bl-md bg-white px-4 py-2.5 text-sm leading-relaxed text-brand-ink shadow-xs" aria-live="polite" wire:stream.replace="respuesta-{{ $canal }}">
                    <span class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="flex gap-1">
                            <span class="size-2 animate-bounce rounded-full bg-brand-orange"></span>
                            <span class="size-2 animate-bounce rounded-full bg-brand-orange [animation-delay:150ms]"></span>
                            <span class="size-2 animate-bounce rounded-full bg-brand-orange [animation-delay:300ms]"></span>
                        </span>
                        Pensando…
                    </span>
                </div>
            @endif
        </div>

        <form wire:submit="enviar" class="flex items-end gap-2 border-t border-slate-100 bg-white p-3">
            <label for="asistente-texto-{{ $canal }}" class="sr-only">Escribe tu consulta</label>
            <textarea
                id="asistente-texto-{{ $canal }}"
                wire:model="texto"
                rows="1"
                maxlength="{{ config('asistente.conversacion.max_caracteres_mensaje') }}"
                placeholder="{{ $canal === 'admin' ? 'Escribe tu consulta o pide un reporte…' : 'Regalo para niña de 6 años, hasta 300 Bs' }}"
                x-on:keydown.enter.prevent="if (! $event.shiftKey) $wire.enviar()"
                @disabled($pendiente !== null)
                class="max-h-32 min-h-11 flex-1 resize-none rounded-2xl border border-slate-200 px-4 py-2.5 text-sm focus:border-brand-orange focus:outline-none focus:ring-2 focus:ring-brand-orange/20 disabled:bg-slate-50"
            ></textarea>
            <button type="submit" @disabled($pendiente !== null)
                class="grid size-11 place-items-center rounded-2xl bg-brand-orange text-white transition hover:brightness-110 disabled:opacity-50" aria-label="Enviar">
                <x-lucide-send-horizontal class="text-lg" />
            </button>
        </form>
        <p class="bg-white px-4 pb-2 text-[11px] text-slate-400">Confirma precio y stock en la ficha del producto.</p>
    </section>

    @if ($canal === 'cliente')
        <div
            x-data="{
                visible: false,
                descartado: false,
                temporizadorMostrar: null,
                temporizadorOcultar: null,
                init() {
                    this.programar(8000);
                    this.$watch('abierto', () => {
                        this.visible = false;
                        this.limpiarTemporizadores();
                        this.programar();
                    });
                },
                programar(espera = 90000) {
                    if (this.abierto || this.descartado) { return; }
                    this.temporizadorMostrar = setTimeout(() => {
                        if (document.hidden) { this.programar(); return; }
                        this.visible = true;
                        this.temporizadorOcultar = setTimeout(() => {
                            this.visible = false;
                            this.programar();
                        }, 8000);
                    }, espera);
                },
                descartar() {
                    this.descartado = true;
                    this.visible = false;
                    this.limpiarTemporizadores();
                },
                limpiarTemporizadores() {
                    clearTimeout(this.temporizadorMostrar);
                    clearTimeout(this.temporizadorOcultar);
                },
                destroy() { this.limpiarTemporizadores(); },
            }"
            x-show="visible && ! abierto"
            x-transition.opacity.duration.200ms
            x-cloak
            class="relative flex max-w-[calc(100vw-2.5rem)] items-start gap-1 rounded-2xl border border-brand-orange/30 bg-white p-2 shadow-[3px_3px_0_var(--color-brand-orange-soft)] motion-reduce:transition-none"
        >
            <span aria-hidden="true" class="absolute -bottom-1.5 right-8 size-3 rotate-45 border-b border-r border-brand-orange/30 bg-white"></span>
            <button type="button" x-on:click="abierto = true" class="rounded-xl px-3 py-2 text-left text-sm text-brand-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-orange">
                <span class="block font-semibold">¿No sabes qué comprar?</span>
                <span class="mt-0.5 block text-brand-blue">Te ayudo</span>
            </button>
            <button type="button" x-on:click="descartar()" class="rounded-lg p-1.5 text-brand-ink/60 hover:bg-brand-orange-soft focus-visible:outline-2 focus-visible:outline-brand-orange" aria-label="Cerrar sugerencia">
                <x-lucide-x class="size-4" />
            </button>
        </div>
    @endif

    <button
        type="button"
        x-on:click="abierto = ! abierto"
        @class(['flex items-center gap-2 rounded-full px-5 py-3.5 font-semibold text-white shadow-xl transition hover:scale-105', 'bg-brand-blue' => $canal === 'admin', 'bg-brand-orange' => $canal !== 'admin'])
        x-bind:aria-expanded="abierto"
        aria-label="Abrir asistente virtual"
    >
        <span class="text-lg"><x-lucide-bot /></span>
        <span x-show="! abierto" class="text-sm">¿Te ayudo?</span>
        <span x-show="abierto" x-cloak class="text-sm">Cerrar</span>
    </button>
</div>
