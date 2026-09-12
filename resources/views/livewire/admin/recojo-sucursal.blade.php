<x-admin-shell active="recojo-sucursal">
    <div class="space-y-6">
        <!-- ENCABEZADO Y SUCURSAL -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-brand-orange">
                    <span>🛍️</span>
                    <span>Entregas de Pedidos Online</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Recojo en Sucursal</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Verificación por código QR, auditoría de juguetes comprados en línea y emisión de ficha de entrega.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 rounded-2xl border border-blue-200 bg-blue-50/80 px-4 py-2 text-sm text-brand-blue">
                    <span class="text-lg">📍</span>
                    <div>
                        <div class="text-[11px] font-medium text-slate-500 uppercase">Sucursal Asignada</div>
                        <div class="font-bold text-slate-800">{{ $sucursalActual?->nombre ?? 'Sucursal' }}</div>
                    </div>
                </div>

                @if(auth()->user()?->hasRole('Administrador') && count($todasSucursales) > 1)
                    <div class="w-48">
                        <select wire:model.live="sucursalId" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue">
                            @foreach($todasSucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }} ({{ $suc->ciudad }})</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <!-- TARJETAS DE ACCIÓN QR Y MÉTRICAS -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- TARJETA SCANNER QR -->
            <div class="md:col-span-2 rounded-3xl border border-blue-100 bg-gradient-to-br from-white to-blue-50/30 p-5 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="grid size-9 place-items-center rounded-xl bg-brand-blue text-white text-lg">
                            📷
                        </div>
                        <div>
                            <h2 class="font-bold text-slate-900 text-sm sm:text-base">Escanear Ticket QR del Cliente</h2>
                            <p class="text-xs text-slate-500">Apunta la cámara de la laptop al comprobante del cliente o simula la lectura</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="abrirCamara" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                            <span>📷</span>
                            <span>Activar Cámara Web</span>
                        </button>
                        <button type="button" wire:click="simularEscaneoQR()" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 transition" title="Simula el escaneo inmediato de un pedido pendiente">
                            <span>⚡</span>
                            <span>Simular Escaneo</span>
                        </button>
                    </div>
                </div>

                <!-- BÚSQUEDA MANUAL O LECTURA RÁPIDA -->
                <form wire:submit="procesarCodigoQR" class="mt-4 flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            wire:model="codigoQR"
                            placeholder="Código del ticket (ej: VTA-20260909-0001)..."
                            class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                        <span class="absolute left-3 top-3 text-xs text-slate-400">🔍</span>
                    </div>
                    <button type="submit" class="rounded-xl bg-slate-900 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition">
                        Verificar Código
                    </button>
                </form>
            </div>

            <!-- CONTADORES RÁPIDOS -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-1">
                <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4">
                    <div class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Pendientes de Retiro</div>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-amber-900">{{ $conteoPendientes }}</span>
                        <span class="text-xs text-amber-700 font-medium">En tienda</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
                    <div class="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Entregados Hoy</div>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-black text-emerald-900">{{ $conteoEntregadosHoy }}</span>
                        <span class="text-xs text-emerald-700 font-medium">Completados</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERTAS Y MENSAJES -->
        @if ($mensajeAlerta)
            <div @class([
                'flex items-center justify-between rounded-2xl p-4 text-sm shadow-sm transition border',
                'bg-emerald-50 border-emerald-200 text-emerald-800' => $tipoAlerta === 'success',
                'bg-amber-50 border-amber-200 text-amber-900' => $tipoAlerta === 'warning',
                'bg-red-50 border-red-200 text-red-800' => $tipoAlerta === 'error',
                'bg-blue-50 border-blue-200 text-blue-800' => $tipoAlerta === 'info',
            ])>
                <div class="flex items-center gap-3">
                    <span class="text-lg">
                        {{ match($tipoAlerta) { 'success' => '✅', 'warning' => '⚠️', 'error' => '❌', default => 'ℹ️' } }}
                    </span>
                    <p class="font-medium">{{ $mensajeAlerta }}</p>
                </div>
                <button type="button" wire:click="$set('mensajeAlerta', null)" class="text-xs font-bold opacity-60 hover:opacity-100">✕</button>
            </div>
        @endif

        <!-- FILTROS Y TABLA DE PEDIDOS -->
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <!-- PESTAÑAS DE ESTADO -->
                <div class="flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        wire:click="cambiarFiltro('pendientes')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            'bg-white text-brand-blue shadow-sm' => $filtroEstado === 'pendientes',
                            'text-slate-600 hover:text-slate-900' => $filtroEstado !== 'pendientes',
                        ])
                    >
                        ⏳ Pendientes de Retiro
                    </button>
                    <button
                        type="button"
                        wire:click="cambiarFiltro('entregados')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            'bg-white text-brand-blue shadow-sm' => $filtroEstado === 'entregados',
                            'text-slate-600 hover:text-slate-900' => $filtroEstado !== 'entregados',
                        ])
                    >
                        ✅ Entregados
                    </button>
                    <button
                        type="button"
                        wire:click="cambiarFiltro('todos')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            'bg-white text-brand-blue shadow-sm' => $filtroEstado === 'todos',
                            'text-slate-600 hover:text-slate-900' => $filtroEstado !== 'todos',
                        ])
                    >
                        📋 Todos
                    </button>
                </div>

                <!-- BUSCADOR -->
                <div class="w-full sm:w-72">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="busqueda"
                        placeholder="Buscar por cliente, CI o ticket..."
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                </div>
            </div>

            <!-- LISTADO DE PEDIDOS -->
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider">
                            <th class="py-3 px-3">Ticket / Pedido</th>
                            <th class="py-3 px-3">Cliente / Receptor</th>
                            <th class="py-3 px-3">Fecha Compra</th>
                            <th class="py-3 px-3">Artículos</th>
                            <th class="py-3 px-3">Total</th>
                            <th class="py-3 px-3">Estado Entrega</th>
                            <th class="py-3 px-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($pedidos as $pedido)
                            @php
                                $estadoEntrega = $pedido->entrega?->estado ?? 'pendiente';
                                $esEntregado = ($estadoEntrega === 'entregado');
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-3 font-bold text-brand-blue">
                                    {{ $pedido->numero_venta }}
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-semibold text-slate-900">{{ $pedido->cliente?->name ?? 'Cliente Mostrador' }}</div>
                                    <div class="text-[11px] text-slate-500">
                                        CI: {{ $pedido->cliente?->ci_nit ?? 'S/N' }}
                                        @if($pedido->cliente?->telefono) | Tel: {{ $pedido->cliente->telefono }} @endif
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-slate-600">
                                    {{ $pedido->fecha_venta?->format('d/m/Y H:i') ?? $pedido->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="py-3 px-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ $pedido->detalles->sum('cantidad') }} juguetes
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-bold text-slate-900">
                                    Bs. {{ number_format($pedido->total, 2) }}
                                </td>
                                <td class="py-3 px-3">
                                    @if($esEntregado)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                            <span>✔</span> Entregado
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                            <span>⏳</span> Listo para Retiro
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            wire:click="seleccionarPedido({{ $pedido->id }})"
                                            class="rounded-lg bg-blue-50 px-2.5 py-1.5 text-xs font-semibold text-brand-blue hover:bg-blue-100 transition"
                                            title="Ver detalles del pedido"
                                        >
                                            Ver Detalle
                                        </button>

                                        @if(! $esEntregado)
                                            <button
                                                type="button"
                                                wire:click="simularEscaneoQR('{{ $pedido->numero_venta }}')"
                                                class="rounded-lg bg-amber-50 px-2 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100 transition"
                                                title="Simular escaneo QR de este ticket"
                                            >
                                                ⚡ QR
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="descargarFichaPdf({{ $pedido->id }})"
                                                class="rounded-lg bg-slate-100 px-2 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition"
                                                title="Descargar Ficha de Entrega PDF"
                                            >
                                                📄 PDF
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    No se encontraron pedidos de recojo en sucursal para los criterios seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $pedidos->links() }}
            </div>
        </div>

        <!-- MODAL DE CÁMARA WEB PARA ESCANEO QR EN TIEMPO REAL -->
        @if ($modalCamaraAbierto)
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4"
                x-data="mundiToysQrScanner($wire, 'lector-qr-camara-recojo')"
                x-init="iniciar()"
                x-on:keydown.escape.window="detener(); $wire.cerrarCamara()"
            >
                <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">📷</span>
                            <div>
                                <h3 class="font-bold text-slate-900 leading-tight">Escáner QR en Tiempo Real</h3>
                                <p class="text-[11px] text-slate-500">Apunta la cámara de la laptop al comprobante del cliente</p>
                            </div>
                        </div>
                        <button
                            type="button"
                            x-on:click="detener(); $wire.cerrarCamara()"
                            class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="mt-4">
                        <template x-if="iniciando">
                            <div class="flex flex-col items-center justify-center h-72 rounded-2xl bg-slate-900 text-white">
                                <div class="size-9 animate-spin rounded-full border-4 border-brand-orange border-t-transparent mb-3"></div>
                                <p class="text-xs font-semibold">Activando sensor de cámara web...</p>
                                <p class="text-[10px] text-slate-400 mt-1">Concediendo acceso al dispositivo de video</p>
                            </div>
                        </template>

                        <template x-if="errorCamara">
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800">
                                <div class="font-bold mb-1 flex items-center gap-1.5">
                                    <span>⚠️</span>
                                    <span>Aviso de Acceso a Cámara</span>
                                </div>
                                <p x-text="errorCamara"></p>
                                <div class="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        x-on:click="detener(); $wire.simularEscaneoQR()"
                                        class="rounded-xl bg-amber-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition"
                                    >
                                        ⚡ Simular Escaneo Ahora
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div class="relative overflow-hidden rounded-2xl bg-slate-950 h-72" x-show="!errorCamara && !iniciando">
                            <!-- CONTENEDOR HTML5-QRCODE -->
                            <div id="lector-qr-camara-recojo" class="size-full overflow-hidden [&_video]:size-full [&_video]:object-cover"></div>

                            <!-- MIRA VISOR QR CON CAMBIO DE COLOR (NARANJA ESCANEANDO -> VERDE AL LEER) -->
                            <div class="absolute inset-0 flex items-center justify-center pointer-events-none p-4">
                                <!-- ESTADO 1: ESCANEANDO (BORDES NARANJA) -->
                                <div
                                    x-show="!escaneado"
                                    class="size-52 sm:size-56 rounded-3xl border-3 border-brand-orange shadow-[0_0_0_9999px_rgba(0,0,0,0.55)] relative transition-all duration-300"
                                >
                                    <div class="absolute inset-x-2 top-0 h-0.5 bg-gradient-to-r from-transparent via-brand-orange to-transparent animate-pulse shadow-sm"></div>
                                    <div class="absolute -top-1 -left-1 size-4 border-t-4 border-l-4 border-white rounded-tl-lg"></div>
                                    <div class="absolute -top-1 -right-1 size-4 border-t-4 border-r-4 border-white rounded-tr-lg"></div>
                                    <div class="absolute -bottom-1 -left-1 size-4 border-b-4 border-l-4 border-white rounded-bl-lg"></div>
                                    <div class="absolute -bottom-1 -right-1 size-4 border-b-4 border-r-4 border-white rounded-br-lg"></div>
                                    <div class="absolute bottom-3 inset-x-0 text-center text-[10px] font-black text-white uppercase tracking-wider drop-shadow-md">
                                        Enfoca el ticket QR
                                    </div>
                                </div>

                                <!-- ESTADO 2: DETECTADO (ORILLAS EN VERDE BRILLANTE + CHECK) -->
                                <div
                                    x-show="escaneado"
                                    x-cloak
                                    class="size-52 sm:size-56 rounded-3xl border-4 border-emerald-400 bg-emerald-500/20 shadow-[0_0_40px_rgba(16,185,129,0.95),0_0_0_9999px_rgba(0,0,0,0.65)] relative transition-all duration-300 scale-105 flex flex-col items-center justify-center"
                                >
                                    <div class="grid size-14 place-items-center rounded-full bg-emerald-500 text-white text-2xl font-black shadow-lg animate-bounce">
                                        ✓
                                    </div>
                                    <span class="mt-2 text-xs font-black uppercase tracking-wider text-emerald-200 bg-slate-950/80 px-3 py-1 rounded-full border border-emerald-400/50 shadow-sm">
                                        ¡Código QR Leído!
                                    </span>
                                    <span class="mt-1 text-[10px] font-mono text-white/90 truncate max-w-[190px] px-2" x-text="codigoDetectado"></span>
                                    <span class="text-[10px] text-emerald-300 mt-1 font-medium">Abriendo entrega y datos...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-slate-100 pt-3">
                        <button
                            type="button"
                            x-on:click="detener(); $wire.simularEscaneoQR()"
                            class="w-full sm:w-auto rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-white hover:bg-amber-600 transition flex items-center justify-center gap-1.5"
                        >
                            <span>⚡</span>
                            <span>Simular Detección de Ticket</span>
                        </button>

                        <button
                            type="button"
                            x-on:click="detener(); $wire.cerrarCamara()"
                            class="w-full sm:w-auto rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                        >
                            Cerrar Cámara
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- MODAL / DRAWER DE DETALLE DEL PEDIDO AUDITADO -->
        @if ($pedidoDetallado)
            @php
                $entrega = $pedidoDetallado->entrega;
                $estadoEntrega = $entrega?->estado ?? 'pendiente';
                $esEntregado = ($estadoEntrega === 'entregado');
                $sucursalPedidoId = $entrega?->sucursal_origen_id;
                $sucursalValida = ($this->sucursalId === null || $sucursalPedidoId === $this->sucursalId || auth()->user()?->hasRole('Administrador'));
                $estaValidadoQr = ($qrValidado && $pedidoQrValidadoId === $pedidoDetallado->id);
            @endphp
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4 overflow-y-auto">
                <div class="relative w-full max-w-3xl rounded-3xl bg-white p-6 shadow-2xl my-8">
                    <!-- ENCABEZADO DEL MODAL -->
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-lg bg-brand-orange/10 px-2 py-0.5 text-xs font-black text-brand-orange">
                                    {{ $pedidoDetallado->numero_venta }}
                                </span>
                                @if($esEntregado)
                                    <span class="rounded-lg bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                        ✔ Entregado
                                    </span>
                                @else
                                    <span class="rounded-lg bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                        ⏳ Listo para Retiro
                                    </span>
                                @endif
                            </div>
                            <h2 class="mt-2 text-xl font-bold text-slate-900">Auditoría de Entrega de Juguetes</h2>
                            <p class="text-xs text-slate-500">
                                Realizada el {{ $pedidoDetallado->fecha_venta?->format('d/m/Y H:i') ?? $pedidoDetallado->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="cerrarDetalle"
                            class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- ALERTA DE SUCURSAL SI NO COINCIDE -->
                    @if(! $sucursalValida)
                        <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-xs text-red-800">
                            <div class="font-bold text-sm mb-1">⚠️ Sucursal Incorrecta</div>
                            <p>
                                Este pedido fue programado para ser retirado en la sucursal <strong>{{ $entrega?->sucursal?->nombre ?? 'Otra' }}</strong>.
                                Tu usuario está operando en <strong>{{ $sucursalActual?->nombre }}</strong>. No debes entregar mercadería de otra sucursal.
                            </p>
                        </div>
                    @endif

                    <!-- DATOS DEL CLIENTE Y PAGO -->
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs">
                            <div class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] text-brand-blue">
                                👤 Datos del Comprador
                            </div>
                            <div class="space-y-1 text-slate-700">
                                <div><strong class="text-slate-500">Nombre:</strong> {{ $pedidoDetallado->cliente?->name ?? 'Consumidor Final' }}</div>
                                <div><strong class="text-slate-500">CI / NIT:</strong> {{ $pedidoDetallado->cliente?->ci_nit ?? 'Sin registrar' }}</div>
                                <div><strong class="text-slate-500">Teléfono:</strong> {{ $pedidoDetallado->cliente?->telefono ?? 'Sin registrar' }}</div>
                                <div><strong class="text-slate-500">Correo:</strong> {{ $pedidoDetallado->cliente?->email ?? 'Sin registrar' }}</div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs">
                            <div class="font-bold text-slate-800 uppercase tracking-wider mb-2 text-[11px] text-brand-blue">
                                💳 Comprobación de Pago
                            </div>
                            <div class="space-y-1 text-slate-700">
                                <div><strong class="text-slate-500">Método de Pago:</strong> {{ ucfirst($pedidoDetallado->pago?->metodo_pago ?? 'En línea') }}</div>
                                <div><strong class="text-slate-500">Referencia:</strong> {{ $pedidoDetallado->pago?->referencia_pago ?? 'N/A' }}</div>
                                <div><strong class="text-slate-500">Estado Pago:</strong> <span class="font-bold text-emerald-700">{{ strtoupper($pedidoDetallado->pago?->estado ?? 'PAGADO') }}</span></div>
                                <div><strong class="text-slate-500">Total Venta:</strong> <span class="font-bold text-slate-900 text-sm">Bs. {{ number_format($pedidoDetallado->total, 2) }}</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DE JUGUETES COMPRADOS -->
                    <div class="mt-4">
                        <div class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-2 text-brand-blue">
                            🧸 Juguetes a Entregar al Cliente
                        </div>
                        <div class="max-h-60 overflow-y-auto rounded-2xl border border-slate-200">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-slate-500 font-semibold uppercase">
                                    <tr>
                                        <th class="py-2.5 px-3">Juguete</th>
                                        <th class="py-2.5 px-3 text-center">Cantidad</th>
                                        <th class="py-2.5 px-3 text-right">P. Unitario</th>
                                        <th class="py-2.5 px-3 text-right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($pedidoDetallado->detalles as $detalle)
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-2 px-3">
                                                <div class="flex items-center gap-3">
                                                    @php
                                                        $imagen = $detalle->producto?->imagenes?->firstWhere('principal', true)?->ruta_archivo ?? $detalle->producto?->imagenes?->sortBy('orden')->first()?->ruta_archivo;
                                                    @endphp
                                                    @if($imagen)
                                                        <img
                                                            src="{{ asset('storage/' . ltrim($imagen, '/')) }}"
                                                            alt="{{ $detalle->producto?->nombre }}"
                                                            class="size-9 rounded-lg object-cover border border-slate-200"
                                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                                        >
                                                        <div style="display:none;" class="size-9 rounded-lg bg-slate-100 place-items-center text-sm">🧸</div>
                                                    @else
                                                        <div class="size-9 rounded-lg bg-slate-100 grid place-items-center text-sm">🧸</div>
                                                    @endif
                                                    <div>
                                                        <div class="font-bold text-slate-900">{{ $detalle->producto?->nombre ?? 'Juguete' }}</div>
                                                        <div class="text-[10px] text-slate-500">Código: {{ $detalle->producto?->codigo_producto }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 text-center font-bold text-sm text-slate-800">
                                                {{ $detalle->cantidad }}
                                            </td>
                                            <td class="py-2 px-3 text-right text-slate-600">
                                                Bs. {{ number_format($detalle->precio_unitario, 2) }}
                                            </td>
                                            <td class="py-2 px-3 text-right font-bold text-slate-900">
                                                Bs. {{ number_format($detalle->subtotal, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- BLOQUE DE VALIDACIÓN OBLIGATORIA DE CÓDIGO QR -->
                    @if(! $esEntregado)
                        @if($estaValidadoQr)
                            <div class="mt-4 rounded-2xl border border-emerald-300 bg-emerald-50/90 p-4 shadow-sm animate-in fade-in">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="grid size-10 place-items-center rounded-xl bg-emerald-600 text-white text-xl font-bold shadow-sm">
                                            ✓
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-black text-emerald-950 uppercase tracking-wider">¡Código QR Validado Exitosamente!</span>
                                                <span class="rounded-full bg-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-900">Acreditado</span>
                                            </div>
                                            <p class="text-xs text-emerald-800 mt-0.5">
                                                Comprobante del cliente verificado: <strong class="font-mono bg-emerald-100 px-1.5 py-0.5 rounded">{{ $codigoQrTextoValidado ?: $pedidoDetallado->numero_venta }}</strong>
                                            </p>
                                            <p class="text-[11px] text-emerald-700">
                                                Se acreditó la posesión del ticket oficial (correo Gmail, factura PDF o compra). Ya puedes entregar la mercadería al cliente.
                                            </p>
                                        </div>
                                    </div>
                                    <span class="self-start sm:self-center text-xs font-semibold text-emerald-700 bg-white/80 border border-emerald-200 px-3 py-1.5 rounded-xl">
                                        Listo para entregar
                                    </span>
                                </div>
                            </div>
                        @else
                            <div class="mt-4 rounded-2xl border-2 border-dashed border-amber-300 bg-amber-50/80 p-4">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/70 pb-3">
                                    <div class="flex items-start gap-2.5">
                                        <span class="text-xl">🔐</span>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-xs sm:text-sm font-bold text-amber-950">Validación Obligatoria de Código QR</h3>
                                                <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700 uppercase">Requerido</span>
                                            </div>
                                            <p class="text-xs text-amber-800 mt-0.5">
                                                Para autorizar la entrega, el cliente debe presentarte el código QR recibido en su correo Gmail, en su factura PDF descargada o en su pantalla de compra.
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 self-end sm:self-center">
                                        <button
                                            type="button"
                                            wire:click="abrirCamara"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition"
                                        >
                                            <span>📷</span>
                                            <span>Activar Cámara</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="simularQrParaPedidoActual"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-amber-500 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-amber-600 transition"
                                            title="Simula la lectura del código QR del cliente para este pedido"
                                        >
                                            <span>⚡</span>
                                            <span>Simular QR</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- FORMULARIO DE VALIDACIÓN DE QR DEL CLIENTE -->
                                <form wire:submit="validarQrPedidoModal" class="mt-3 flex flex-col sm:flex-row gap-2">
                                    <div class="relative flex-1">
                                        <input
                                            type="text"
                                            wire:model="codigoQrModal"
                                            placeholder="Ingresa o escanea el código QR del cliente (ej: {{ $pedidoDetallado->numero_venta }})..."
                                            class="w-full rounded-xl border border-amber-300 bg-white py-2 pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue shadow-inner"
                                        >
                                        <span class="absolute left-3 top-2.5 text-xs text-slate-400">📱</span>
                                    </div>
                                    <button
                                        type="submit"
                                        class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition flex items-center justify-center gap-1.5"
                                    >
                                        <span>🔍</span>
                                        <span>Validar QR del Cliente</span>
                                    </button>
                                </form>

                                @if ($mensajeValidacionQr && ! $estaValidadoQr)
                                    <div class="mt-2 text-xs font-semibold text-red-600 flex items-center gap-1.5">
                                        <span>❌</span>
                                        <span>{{ $mensajeValidacionQr }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif

                    <!-- ESTADO Y ACCIONES DE ENTREGA -->
                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-100 pt-4">
                        <div>
                            @if($esEntregado)
                                <div class="text-xs text-emerald-700 font-semibold">
                                    ✔ Entregado el {{ $entrega?->fecha_programada?->format('d/m/Y') }} a las {{ $entrega?->hora_fin ?? 'sucursal' }} por {{ $entrega?->empleado?->name ?? 'Personal de ventas' }}.
                                </div>
                            @else
                                <div class="text-xs text-slate-500">
                                    @if($estaValidadoQr)
                                        <span class="text-emerald-700 font-semibold">✔ QR validado. Verifica físicamente los artículos e identidad antes de entregar.</span>
                                    @else
                                        <span class="text-amber-800 font-medium">⚠️ No se puede entregar hasta que el cliente presente y valide su código QR.</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 w-full sm:w-auto">
                            @if(! $esEntregado)
                                @if($estaValidadoQr && $sucursalValida)
                                    <button
                                        type="button"
                                        wire:click="confirmarEntrega"
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition"
                                    >
                                        <span>✔</span>
                                        <span>Confirmar y Marcar como Entregado</span>
                                    </button>
                                @else
                                    <button
                                        type="button"
                                        disabled
                                        title="No es posible marcar como entregado sin antes validar el código QR presentado por el cliente"
                                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-slate-200 px-5 py-2.5 text-xs font-bold text-slate-500 border border-slate-300 cursor-not-allowed shadow-none"
                                    >
                                        <span>🔒</span>
                                        <span>Validar QR para Entregar</span>
                                    </button>
                                @endif
                            @else
                                <button
                                    type="button"
                                    wire:click="descargarFichaPdf({{ $pedidoDetallado->id }})"
                                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand-blue px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition"
                                >
                                    <span>📄</span>
                                    <span>Descargar Ficha de Entrega PDF</span>
                                </button>
                            @endif

                            <button
                                type="button"
                                wire:click="cerrarDetalle"
                                class="w-full sm:w-auto rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-shell>

