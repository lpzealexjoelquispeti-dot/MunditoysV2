<div class="space-y-8 py-4">
    <!-- CABECERA -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-orange-100 pb-6">
        <div>
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-brand-orange/10 text-2xl text-brand-orange">📦</span>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Seguimiento y Registro</h1>
                    <p class="text-sm text-slate-500">Historial de pedidos, códigos QR para recojo en tienda y facturas electrónicas oficiales.</p>
                </div>
            </div>
        </div>

        <!-- PESTAÑAS -->
        <div class="flex items-center gap-2 rounded-2xl bg-slate-100 p-1.5 self-start sm:self-auto">
            <button
                type="button"
                wire:click="cambiarPestana('pedidos')"
                @class([
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition',
                    'bg-white text-brand-blue shadow-sm' => $pestanaActiva === 'pedidos',
                    'text-slate-600 hover:text-slate-900' => $pestanaActiva !== 'pedidos',
                ])
            >
                <span>📦 Mis Compras y Retiros</span>
            </button>

            <button
                type="button"
                wire:click="cambiarPestana('notificaciones')"
                @class([
                    'flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-black transition relative',
                    'bg-white text-brand-blue shadow-sm' => $pestanaActiva === 'notificaciones',
                    'text-slate-600 hover:text-slate-900' => $pestanaActiva !== 'notificaciones',
                ])
            >
                <span>🔔 Notificaciones</span>
                @if($totalNoLeidas > 0)
                    <span class="rounded-full bg-brand-orange px-1.5 py-0.2 text-[10px] font-bold text-white">
                        {{ $totalNoLeidas }}
                    </span>
                @endif
            </button>
        </div>
    </div>

    <!-- CONTENIDO PESTAÑA 1: HISTORIAL DE COMPRAS -->
    @if($pestanaActiva === 'pedidos')
        <div class="space-y-6">
            <!-- BARRA DE BÚSQUEDA Y FILTRO -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-3xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <div class="relative flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">🔍</span>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="busqueda"
                        placeholder="Buscar por código de venta (ej. VTA-2026...) o Cédula de Identidad (CI)..."
                        class="w-full rounded-2xl border-slate-200 bg-slate-50/50 pl-10 pr-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-brand-blue focus:bg-white focus:ring-brand-blue"
                    >
                </div>
                @if($esInvitado)
                    <div class="rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-2 text-xs text-amber-800">
                        💡 Como invitado, ingresa tu <strong>código de pedido</strong> o <strong>CI</strong> para localizar tus compras.
                    </div>
                @endif
            </div>

            <!-- LISTADO DE PEDIDOS -->
            @if($pedidos->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
                    <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-slate-50 text-3xl text-slate-400 shadow-inner">
                        📋
                    </div>
                    <h3 class="mt-4 text-base font-black text-slate-800">
                        @if($esInvitado && empty(trim($busqueda)))
                            Ingresa tu N° de Pedido o CI en el buscador superior
                        @else
                            No se encontraron pedidos registrados
                        @endif
                    </h3>
                    <p class="mx-auto mt-1 max-w-sm text-xs text-slate-500">
                        @if($esInvitado && empty(trim($busqueda)))
                            Si realizaste una compra como invitado, escribe el código que recibiste por correo (ej. VTA-2026...) para ver tu QR y descargar tu factura.
                        @else
                            No localizamos ninguna compra que coincida con los criterios especificados.
                        @endif
                    </p>
                    <div class="mt-6">
                        <a
                            href="{{ route('tienda.catalogo') }}"
                            class="inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-5 py-2.5 text-xs font-black text-white shadow-sm hover:bg-orange-600 transition"
                            wire:navigate
                        >
                            <span>Ir al Catálogo de Juguetes</span>
                        </a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 gap-5">
                    @foreach($pedidos as $pedido)
                        @php
                            $entrega = $pedido->entrega;
                            $sucursal = $entrega?->sucursal;
                            $esEntregado = $entrega?->estado === 'entregado';
                        @endphp
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:shadow-md space-y-4">
                            <!-- CABECERA DE LA TARJETA -->
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                                <div class="flex items-center gap-3">
                                    <div class="grid size-12 place-items-center rounded-2xl bg-brand-blue/10 text-xl text-brand-blue font-bold">
                                        #
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-base font-black text-slate-900">{{ $pedido->numero_venta }}</span>
                                            <span class="rounded-md bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-brand-blue uppercase">
                                                Compra Online
                                            </span>
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            Fecha de compra: {{ $pedido->fecha_venta?->format('d/m/Y H:i') ?? $pedido->created_at->format('d/m/Y H:i') }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($esEntregado)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-bold text-emerald-700">
                                            <span>✓ Entregado en Sucursal</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-3 py-1 text-xs font-bold text-amber-800 animate-pulse">
                                            <span>⏳ Listo para Recojo</span>
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- CUERPO DE LA TARJETA -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                                <div class="rounded-2xl bg-slate-50 p-3.5 space-y-1">
                                    <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Modalidad</span>
                                    <div class="font-bold text-slate-800">🏪 Recojo en Sucursal</div>
                                    <div class="text-slate-500 font-medium truncate">{{ $sucursal?->nombre ?? 'Sucursal' }}</div>
                                    <div class="text-slate-400 text-[11px] truncate">{{ $sucursal?->direccion }}</div>
                                </div>

                                <div class="rounded-2xl bg-slate-50 p-3.5 space-y-1">
                                    <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Pago y Factura</span>
                                    <div class="font-bold text-brand-blue">Total: Bs. {{ number_format($pedido->total, 2) }}</div>
                                    <div class="text-slate-500">Pasarela: Stripe (Aprobado)</div>
                                    <div class="text-slate-400 text-[11px]">NIT/CI: {{ $pedido->cliente?->ci_nit ?? '0' }}</div>
                                </div>

                                <div class="rounded-2xl bg-slate-50 p-3.5 space-y-1">
                                    <span class="text-slate-400 font-bold uppercase tracking-wider text-[10px]">Artículos</span>
                                    <div class="font-bold text-slate-800">{{ $pedido->detalles->sum('cantidad') }} juguete(s)</div>
                                    <div class="text-slate-500 truncate">
                                        {{ $pedido->detalles->map(fn($d) => $d->producto?->nombre)->filter()->join(', ') }}
                                    </div>
                                </div>
                            </div>

                            <!-- ACCIONES: VER QR Y DESCARGAR FACTURA -->
                            <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    wire:click="verQr({{ $pedido->id }})"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-brand-blue bg-blue-50/50 px-4 py-2 text-xs font-bold text-brand-blue hover:bg-blue-100 transition"
                                >
                                    <span>📱 Ver QR de Recojo</span>
                                </button>

                                <button
                                    type="button"
                                    wire:click="descargarFactura({{ $pedido->id }})"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-xs transition"
                                >
                                    <span>📄 Descargar Factura PDF</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- PAGINACIÓN -->
                <div class="mt-4">
                    {{ $pedidos->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- CONTENIDO PESTAÑA 2: NOTIFICACIONES -->
    @if($pestanaActiva === 'notificaciones')
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <h2 class="text-base font-black text-slate-900">Centro de Notificaciones</h2>
                    <p class="text-xs text-slate-500">Avisos automáticos sobre tus compras y estados de entrega.</p>
                </div>
                @if(Auth::check())
                    <button
                        type="button"
                        wire:click="marcarTodasLeidas"
                        class="text-xs font-bold text-brand-blue hover:underline"
                    >
                        Marcar todas como leídas
                    </button>
                @endif
            </div>

            @if($notificaciones->isEmpty())
                <div class="py-12 text-center text-slate-400 text-sm">
                    <span class="text-3xl block mb-2">🔔</span>
                    No tienes notificaciones pendientes en este momento.
                </div>
            @else
                <div class="divide-y divide-slate-100 space-y-2">
                    @foreach($notificaciones as $notif)
                        <div class="flex items-start justify-between gap-4 p-4 rounded-2xl transition {{ $notif->leido ? 'bg-white' : 'bg-orange-50/50 border border-orange-100' }}">
                            <div class="flex items-start gap-3">
                                <span class="text-xl shrink-0 mt-0.5">
                                    {{ $notif->leido ? '📩' : '📬' }}
                                </span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 {{ ! $notif->leido ? 'text-brand-orange' : '' }}">
                                        {{ $notif->titulo }}
                                    </h4>
                                    <p class="mt-1 text-xs text-slate-600 leading-relaxed">
                                        {{ $notif->mensaje }}
                                    </p>
                                    @if($notif->producto)
                                        <a href="{{ route('tienda.producto.detalle', ['codigo' => $notif->producto->codigo_producto]) }}#resenas-juguete" wire:navigate class="mt-2 inline-flex rounded-lg bg-brand-blue px-3 py-2 text-xs font-semibold text-white">Ver juguete y reseñar</a>
                                    @endif
                                    <span class="mt-1 block text-[10px] text-slate-400">
                                        {{ $notif->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>

                            @if(! $notif->leido)
                                <button
                                    type="button"
                                    wire:click="marcarNotificacionLeida({{ $notif->id }})"
                                    class="shrink-0 rounded-lg bg-white border border-slate-200 px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:bg-slate-50 shadow-xs"
                                >
                                    Marcar leída
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    {{ $notificaciones->links() }}
                </div>
            @endif
        </div>
    @endif

    <!-- MODAL INTERACTIVO PARA QR DE RECOJO -->
    @if($pedidoSeleccionado && $qrDataUriModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs">
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl space-y-4 text-center animate-in fade-in zoom-in-95 duration-150">
                <!-- BOTÓN CERRAR -->
                <button
                    type="button"
                    wire:click="cerrarQr"
                    class="absolute top-4 right-4 grid size-8 place-items-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                >
                    ✕
                </button>

                <div>
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold text-brand-blue uppercase tracking-wider mb-2">
                        Código de Retiro en Tienda
                    </div>
                    <h3 class="text-lg font-black text-slate-900">
                        Pedido #{{ $pedidoSeleccionado->numero_venta }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        Sucursal: <strong>{{ $pedidoSeleccionado->entrega?->sucursal?->nombre ?? 'MundiToys' }}</strong>
                    </p>
                </div>

                <!-- CONTROLES VISIBILIDAD QR -->
                <div class="flex justify-center">
                    <button
                        type="button"
                        wire:click="toggleQrVisibilidad"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
                    >
                        @if($qrVisible)
                            <span>👁️ Ocultar QR de Pantalla</span>
                        @else
                            <span>👁️ Mostrar QR de Pantalla</span>
                        @endif
                    </button>
                </div>

                <!-- DISPLAY DEL QR -->
                @if($qrVisible)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 inline-block">
                        <img
                            src="{{ $qrDataUriModal }}"
                            alt="QR de Recojo {{ $pedidoSeleccionado->numero_venta }}"
                            class="size-52 mx-auto rounded-lg shadow-xs bg-white p-2"
                        >
                        <div class="mt-2 font-mono text-xs font-bold text-slate-700">
                            {{ $pedidoSeleccionado->numero_venta }}
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 p-8 text-center">
                        <div class="text-3xl opacity-40">🔒</div>
                        <div class="mt-2 text-xs font-bold text-slate-700">Código QR Oculto</div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Presiona "Mostrar QR" cuando estés frente al encargado de sucursal.
                        </p>
                    </div>
                @endif

                <div class="rounded-xl bg-slate-50 border border-slate-100 p-3 text-left text-[11px] text-slate-600 space-y-1">
                    <div><strong>Dirección:</strong> {{ $pedidoSeleccionado->entrega?->sucursal?->direccion ?? 'Tienda Central' }}</div>
                    <div><strong>Retira:</strong> {{ $pedidoSeleccionado->cliente?->name ?? 'Cliente Registrado' }} (CI: {{ $pedidoSeleccionado->cliente?->ci_nit ?? '-' }})</div>
                    <div><strong>Total Pagado:</strong> Bs. {{ number_format($pedidoSeleccionado->total, 2) }} (Stripe)</div>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button
                        type="button"
                        wire:click="descargarFactura({{ $pedidoSeleccionado->id }})"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-brand-orange px-4 py-2.5 text-xs font-black text-white hover:bg-orange-600 shadow-xs transition"
                    >
                        <span>📄 Factura PDF</span>
                    </button>
                    <button
                        type="button"
                        wire:click="cerrarQr"
                        class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-xs transition"
                    >
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
