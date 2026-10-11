<div class="space-y-6 py-4">
    <!-- CABECERA Y PESTAÑAS -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Mis pedidos</h1>

        <div class="flex items-center gap-1 self-start rounded-2xl bg-slate-100 p-1 sm:self-auto">
            <button
                type="button"
                wire:click="cambiarPestana('pedidos')"
                @class([
                    'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                    'bg-white text-brand-blue shadow-sm' => $pestanaActiva === 'pedidos',
                    'text-slate-600 hover:text-slate-900' => $pestanaActiva !== 'pedidos',
                ])
            >
                <x-lucide-package /> Compras
            </button>

            <button
                type="button"
                wire:click="cambiarPestana('notificaciones')"
                @class([
                    'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition',
                    'bg-white text-brand-blue shadow-sm' => $pestanaActiva === 'notificaciones',
                    'text-slate-600 hover:text-slate-900' => $pestanaActiva !== 'notificaciones',
                ])
            >
                <x-lucide-bell /> Notificaciones
                @if($totalNoLeidas > 0)
                    <span class="grid min-w-5 place-items-center rounded-full bg-brand-orange px-1 text-[11px] leading-5 text-white">{{ $totalNoLeidas }}</span>
                @endif
            </button>
        </div>
    </div>

    <!-- PESTAÑA: COMPRAS -->
    @if($pestanaActiva === 'pedidos')
        <div class="space-y-4">
            <label class="relative block">
                <span class="sr-only">Buscar pedido</span>
                <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                    type="text"
                    wire:model.live.debounce.300ms="busqueda"
                    placeholder="Código de pedido o CI"
                    class="w-full rounded-2xl border-slate-200 bg-white py-3 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 shadow-sm focus:border-brand-blue focus:ring-brand-blue"
                >
            </label>

            @if($pedidos->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-14 text-center">
                    <x-lucide-package class="mx-auto text-4xl text-slate-300" />
                    <p class="mt-3 font-semibold text-slate-700">
                        {{ $esInvitado && empty(trim($busqueda)) ? 'Busca tu pedido con el código o tu CI' : 'No encontramos pedidos' }}
                    </p>
                    <a href="{{ route('tienda.catalogo') }}" class="mt-5 inline-flex rounded-xl bg-brand-orange px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600" wire:navigate>
                        Ver catálogo
                    </a>
                </div>
            @else
                <div class="grid gap-4">
                    @foreach($pedidos as $pedido)
                        @php
                            $entrega = $pedido->entrega;
                            $sucursal = $entrega?->sucursal;
                            $esEntregado = $entrega?->estado === 'entregado';
                        @endphp
                        <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono font-semibold text-slate-900">{{ $pedido->numero_venta }}</p>
                                    <p class="text-xs text-slate-400">{{ ($pedido->fecha_venta ?? $pedido->created_at)->format('d/m/Y H:i') }}</p>
                                </div>
                                @if($esEntregado)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                                        <x-lucide-check /> Entregado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                                        <x-lucide-hourglass /> Listo para Recojo
                                    </span>
                                @endif
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                <div class="flex items-start gap-2">
                                    <x-lucide-store class="mt-0.5 text-slate-400" />
                                    <div class="min-w-0">
                                        <dt class="sr-only">Sucursal</dt>
                                        <dd class="truncate font-semibold text-slate-800">{{ $sucursal?->nombre ?? 'Sucursal' }}</dd>
                                        <dd class="truncate text-xs text-slate-500">{{ $sucursal?->direccion }}</dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <x-lucide-toy-brick class="mt-0.5 text-slate-400" />
                                    <div class="min-w-0">
                                        <dt class="sr-only">Juguetes</dt>
                                        <dd class="truncate text-slate-700">{{ $pedido->detalles->map(fn($d) => $d->producto?->nombre)->filter()->join(', ') }}</dd>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2 sm:justify-end">
                                    <dt class="sr-only">Total</dt>
                                    <dd class="text-lg font-bold text-brand-blue">Bs. {{ number_format($pedido->total, 2) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 flex flex-wrap justify-end gap-2">
                                <button type="button" wire:click="verQr({{ $pedido->id }})" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-blue-dark">
                                    <x-lucide-qr-code /> Ver QR
                                </button>
                                <button type="button" wire:click="descargarFactura({{ $pedido->id }})" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                    <x-lucide-file-text /> Factura
                                </button>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div>{{ $pedidos->links() }}</div>
            @endif
        </div>
    @endif

    <!-- PESTAÑA: NOTIFICACIONES -->
    @if($pestanaActiva === 'notificaciones')
        <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
            @if(Auth::check() && $totalNoLeidas > 0)
                <div class="mb-3 flex justify-end">
                    <button type="button" wire:click="marcarTodasLeidas" class="text-sm font-semibold text-brand-blue hover:underline">
                        Marcar todas como leídas
                    </button>
                </div>
            @endif

            @if($notificaciones->isEmpty())
                <div class="py-12 text-center text-slate-400">
                    <x-lucide-bell class="mx-auto text-3xl" />
                    <p class="mt-2 text-sm">Sin notificaciones</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($notificaciones as $notif)
                        <div @class(['flex items-start justify-between gap-4 rounded-2xl p-4', 'bg-orange-50/60' => ! $notif->leido])>
                            <div class="flex items-start gap-3">
                                <x-dynamic-component :component="$notif->leido ? 'lucide-mail-open' : 'lucide-mail'" @class(['mt-0.5 text-xl', 'text-slate-300' => $notif->leido, 'text-brand-orange' => ! $notif->leido]) />
                                <div>
                                    <p @class(['text-sm font-semibold', 'text-slate-900' => ! $notif->leido, 'text-slate-600' => $notif->leido])>{{ $notif->titulo }}</p>
                                    <p class="mt-0.5 text-sm text-slate-600">{{ $notif->mensaje }}</p>
                                    @if($notif->producto)
                                        <a href="{{ route('tienda.producto.detalle', ['codigo' => $notif->producto->codigo_producto]) }}#resenas-juguete" wire:navigate class="mt-2 inline-flex rounded-lg bg-brand-blue px-3 py-1.5 text-xs font-semibold text-white">Reseñar juguete</a>
                                    @endif
                                    <p class="mt-1 text-xs text-slate-400">{{ $notif->created_at->diffForHumans() }}</p>
                                </div>
                            </div>

                            @if(! $notif->leido)
                                <button type="button" wire:click="marcarNotificacionLeida({{ $notif->id }})" class="shrink-0 rounded-full p-1.5 text-slate-400 transition hover:bg-white hover:text-brand-blue" aria-label="Marcar como leída" title="Marcar como leída">
                                    <x-lucide-check class="text-lg" />
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">{{ $notificaciones->links() }}</div>
            @endif
        </div>
    @endif

    <!-- MODAL QR DE RECOJO -->
    @if($pedidoSeleccionado && $qrDataUriModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-sm space-y-4 rounded-3xl bg-white p-6 text-center shadow-2xl">
                <button type="button" wire:click="cerrarQr" class="absolute right-4 top-4 grid size-8 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Cerrar">
                    <x-lucide-x />
                </button>

                <div>
                    <p class="text-sm text-slate-500">Código de Retiro en Tienda</p>
                    <h3 class="font-mono text-lg font-bold text-slate-900">{{ $pedidoSeleccionado->numero_venta }}</h3>
                </div>

                @if($qrVisible)
                    <img src="{{ $qrDataUriModal }}" alt="QR de recojo {{ $pedidoSeleccionado->numero_venta }}" class="mx-auto size-56 rounded-2xl border border-slate-200 bg-white p-2">
                @else
                    <div class="mx-auto grid size-56 place-items-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 text-slate-400">
                        <div>
                            <x-lucide-lock class="mx-auto text-3xl" />
                            <p class="mt-2 text-sm font-semibold text-slate-600">Código QR Oculto</p>
                        </div>
                    </div>
                @endif

                <button type="button" wire:click="toggleQrVisibilidad" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue hover:underline">
                    @if($qrVisible)
                        <x-lucide-eye-off /> Ocultar QR
                    @else
                        <x-lucide-eye /> Mostrar QR
                    @endif
                </button>

                <div class="flex items-start gap-2 rounded-2xl bg-slate-50 p-3 text-left text-sm">
                    <x-lucide-map-pin class="mt-0.5 text-slate-400" />
                    <div>
                        <p class="font-semibold text-slate-800">{{ $pedidoSeleccionado->entrega?->sucursal?->nombre ?? 'MundiToys' }}</p>
                        <p class="text-xs text-slate-500">{{ $pedidoSeleccionado->entrega?->sucursal?->direccion ?? 'Tienda central' }}</p>
                    </div>
                </div>

                <button type="button" wire:click="descargarFactura({{ $pedidoSeleccionado->id }})" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-brand-orange px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600">
                    <x-lucide-file-text /> Descargar factura
                </button>
            </div>
        </div>
    @endif
</div>
