<x-admin-shell active="mis-reportes">
    <div class="space-y-6">
        <!-- ENCABEZADO -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-brand-orange">
                    <span><x-lucide-chart-column /></span>
                    <span>Centro de Rendimiento Personal</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-semibold text-slate-900">Mis reportes</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-2 rounded-2xl border border-blue-200 bg-blue-50/80 px-4 py-2 text-xs text-brand-blue">
                    <span><x-lucide-map-pin /></span>
                    <div>
                        <div class="text-[10px] font-semibold text-slate-500">Sucursal</div>
                        <div class="font-semibold text-slate-800">{{ $sucursalActual?->nombre ?? 'Sucursal asignada' }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 rounded-2xl border border-orange-200 bg-orange-50/80 px-4 py-2 text-xs text-brand-orange">
                    <span><x-lucide-user /></span>
                    <div>
                        <div class="text-[10px] font-semibold text-slate-500">Vendedor</div>
                        <div class="font-semibold text-slate-800">{{ $vendedorActual?->name ?? 'Personal de ventas' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PESTAÑAS DE SECCIÓN -->
        <div class="flex border-b border-slate-200">
            <button
                type="button"
                wire:click="cambiarSeccion('ventas_fisicas')"
                @class([
                    'flex items-center gap-2 px-6 py-3.5 text-sm font-semibold border-b-2 transition -mb-px',
                    'border-brand-blue text-brand-blue bg-white' => $seccion === 'ventas_fisicas',
                    'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' => $seccion !== 'ventas_fisicas',
                ])
            >
                <span class="text-base"><x-lucide-receipt /></span>
                <span>1. Ventas Físicas (Mostrador / POS)</span>
            </button>

            <button
                type="button"
                wire:click="cambiarSeccion('recojos_sucursal')"
                @class([
                    'flex items-center gap-2 px-6 py-3.5 text-sm font-semibold border-b-2 transition -mb-px',
                    'border-brand-blue text-brand-blue bg-white' => $seccion === 'recojos_sucursal',
                    'border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300' => $seccion !== 'recojos_sucursal',
                ])
            >
                <span class="text-base"><x-lucide-shopping-bag /></span>
                <span>2. Ventas de Recojo en Sucursal (Entregas Online)</span>
            </button>
        </div>

        <!-- BARRA DE PARÁMETROS Y VALIDACIÓN DE FECHAS -->
        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <!-- PRESETS DE FECHAS -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-semibold text-slate-400 mr-1">Período:</span>
                    @foreach([
                        'hoy' => 'Hoy',
                        'ultimos_7_dias' => 'Últimos 7 días',
                        'este_mes' => 'Este Mes',
                        'mes_anterior' => 'Mes Anterior',
                    ] as $presetKey => $presetLabel)
                        <button
                            type="button"
                            wire:click="aplicarPreset('{{ $presetKey }}')"
                            @class([
                                'rounded-xl px-3 py-1.5 text-xs font-semibold transition',
                                'bg-brand-blue text-white shadow-sm' => $periodoPreset === $presetKey,
                                'bg-slate-100 text-slate-600 hover:bg-slate-200' => $periodoPreset !== $presetKey,
                            ])
                        >
                            {{ $presetLabel }}
                        </button>
                    @endforeach
                </div>

                <!-- SELECTORES DE FECHA CON MÁXIMO EN HOY -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center gap-1.5">
                        <label for="fecha_desde" class="text-xs font-semibold text-slate-600">Desde:</label>
                        <input
                            id="fecha_desde"
                            type="date"
                            wire:model.live="fechaDesde"
                            max="{{ now()->toDateString() }}"
                            class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue shadow-sm"
                        >
                    </div>

                    <div class="flex items-center gap-1.5">
                        <label for="fecha_hasta" class="text-xs font-semibold text-slate-600">Hasta:</label>
                        <input
                            id="fecha_hasta"
                            type="date"
                            wire:model.live="fechaHasta"
                            max="{{ now()->toDateString() }}"
                            class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-brand-blue focus:ring-1 focus:ring-brand-blue shadow-sm"
                        >
                    </div>

                    <!-- BOTONES DE EXPORTACIÓN -->
                    <div class="flex items-center gap-2 pl-2 border-l border-slate-200">
                        <button
                            type="button"
                            wire:click="exportarPdf"
                            @disabled(! $esValido)
                            class="inline-flex items-center gap-1.5 rounded-xl bg-red-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            title="Descargar reporte oficial en formato PDF"
                        >
                            <span><x-lucide-file-text /></span>
                            <span>PDF</span>
                        </button>

                        <button
                            type="button"
                            wire:click="exportarExcel"
                            @disabled(! $esValido)
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
                            title="Descargar datos tabulares en formato Excel"
                        >
                            <span><x-lucide-chart-column /></span>
                            <span>Excel</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ERRORES DE VALIDACIÓN LÓGICA DE FECHAS EN ESPAÑOL -->
            @error('fechaDesde')
                <div class="mt-3 flex items-center gap-2 rounded-xl bg-red-50 border border-red-200 px-4 py-2 text-xs font-medium text-red-700">
                    <span><x-lucide-triangle-alert /></span>
                    <span>{{ $message }}</span>
                </div>
            @enderror

            @error('fechaHasta')
                <div class="mt-2 flex items-center gap-2 rounded-xl bg-red-50 border border-red-200 px-4 py-2 text-xs font-medium text-red-700">
                    <span><x-lucide-triangle-alert /></span>
                    <span>{{ $message }}</span>
                </div>
            @enderror
        </div>

        @if (! $esValido)
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-8 text-center text-amber-800">
                <span class="text-3xl"><x-lucide-triangle-alert /></span>
                <h3 class="mt-2 text-base font-semibold">Fechas inválidas</h3>
                <p class="mt-1 text-xs text-amber-700">
                    Corrige las fechas para ver el reporte.
                </p>
            </div>
        @else
            <!-- SECCIÓN 1: VENTAS FÍSICAS (MOSTRADOR / POS) -->
            @if ($seccion === 'ventas_fisicas')
                <div class="space-y-6">
                    <!-- KPIS PRINCIPALES -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Total Recaudado (POS)</div>
                            <div class="mt-2 text-2xl font-bold text-brand-blue">
                                Bs. {{ number_format($datos['total_ingresos'] ?? 0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Monto total vendido en mostrador</div>
                        </div>

                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Tickets Emitidos</div>
                            <div class="mt-2 text-2xl font-bold text-slate-900">
                                {{ $datos['total_ventas'] ?? 0 }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Transacciones completadas</div>
                        </div>

                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Ticket Promedio</div>
                            <div class="mt-2 text-2xl font-bold text-slate-900">
                                Bs. {{ number_format($datos['ticket_promedio'] ?? 0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Por compra presencial</div>
                        </div>

                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Juguetes Despachados</div>
                            <div class="mt-2 text-2xl font-bold text-brand-orange">
                                {{ $datos['unidades_vendidas'] ?? 0 }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Unidades de inventario entregadas</div>
                        </div>
                    </div>

                    <!-- GRÁFICOS INTERACTIVOS SVG Y DESGLOSE DE PAGO -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- GRÁFICO SVG: EVOLUCIÓN DIARIA DE VENTAS -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-trending-up /></span> Ventas por día (Bs.)
                            </h3>

                            @php
                                $dias = $datos['ventas_por_dia'] ?? [];
                                $maxMonto = max(1, ...array_column($dias, 'monto') ?: [1]);
                            @endphp

                            <div class="mt-6">
                                @if (empty($dias))
                                    <div class="py-12 text-center text-xs text-slate-400">
                                        Sin transacciones en estas fechas
                                    </div>
                                @else
                                    <div class="flex items-end gap-2 h-44 border-b border-slate-200 pb-2 overflow-x-auto">
                                        @foreach ($dias as $dia)
                                            @php
                                                $alturaPorcentaje = max(6, round(($dia['monto'] / $maxMonto) * 100));
                                            @endphp
                                            <div class="flex flex-col items-center flex-1 min-w-[32px] group">
                                                <div class="relative w-full flex justify-center items-end h-36">
                                                    <!-- TOOLTIP AL PASAR EL CURSOR -->
                                                    <div class="absolute -top-7 hidden group-hover:flex z-10 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[10px] font-semibold text-white shadow-md">
                                                        Bs. {{ number_format($dia['monto'], 2) }} ({{ $dia['transacciones'] }} vtas)
                                                    </div>
                                                    <div
                                                        style="height: {{ $alturaPorcentaje }}%;"
                                                        class="w-full max-w-[28px] rounded-t-lg bg-brand-blue group-hover:bg-brand-orange transition-all duration-300 shadow-sm"
                                                    ></div>
                                                </div>
                                                <span class="mt-2 text-[10px] font-semibold text-slate-500">{{ $dia['etiqueta'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- DESGLOSE POR MÉTODO DE COBRO -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-credit-card /></span> Por forma de pago
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Distribución de cobros en caja de mostrador</p>

                            <div class="mt-6 space-y-4">
                                @php
                                    $desglose = $datos['desglose_pagos'] ?? [];
                                @endphp
                                @foreach ($desglose as $clave => $item)
                                    <div>
                                        <div class="flex items-center justify-between text-xs font-semibold">
                                            <span class="text-slate-800">{{ $item['nombre'] }}</span>
                                            <span class="text-slate-600">
                                                Bs. {{ number_format($item['monto'], 2) }}
                                                <span class="text-slate-400 font-normal">({{ $item['porcentaje'] }}%)</span>
                                            </span>
                                        </div>
                                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                class="h-full rounded-full bg-brand-blue transition-all duration-500"
                                                style="width: {{ $item['porcentaje'] }}%;"
                                            ></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- TOP JUGUETES Y TABLA DE VENTAS -->
                    <div class="grid gap-6 lg:grid-cols-3">
                        <!-- TOP JUGUETES -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-toy-brick /></span> Más vendidos
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">Tus productos estrella en mostrador</p>

                            <div class="mt-4 divide-y divide-slate-100">
                                @forelse ($datos['top_productos'] ?? [] as $index => $prod)
                                    <div class="py-3 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="grid size-6 place-items-center rounded-full bg-brand-orange/10 text-xs font-semibold text-brand-orange">
                                                {{ $index + 1 }}
                                            </span>
                                            <div>
                                                <div class="font-semibold text-xs text-slate-800 line-clamp-1">{{ $prod['nombre'] }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $prod['codigo'] }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-xs font-bold text-slate-900">{{ $prod['unidades'] }} uds.</div>
                                            <div class="text-[10px] text-slate-500">Bs. {{ number_format($prod['total_generado'], 2) }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="py-8 text-center text-xs text-slate-400">Sin datos registrados</div>
                                @endforelse
                            </div>
                        </div>

                        <!-- TABLA HISTORIAL DE VENTAS -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-clipboard-list /></span> Tickets emitidos
                            </h3>

                            <div class="mt-4 overflow-x-auto max-h-80">
                                <table class="w-full text-left text-xs">
                                    <thead class="sticky top-0 bg-slate-50 text-slate-500 font-semibold">
                                        <tr>
                                            <th class="py-2.5 px-3">Ticket</th>
                                            <th class="py-2.5 px-3">Fecha / Hora</th>
                                            <th class="py-2.5 px-3">Cliente</th>
                                            <th class="py-2.5 px-3">Método</th>
                                            <th class="py-2.5 px-3 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @forelse ($datos['ventas'] ?? [] as $v)
                                            <tr class="hover:bg-slate-50">
                                                <td class="py-2 px-3 font-semibold text-brand-blue">{{ $v->numero_venta }}</td>
                                                <td class="py-2 px-3 text-slate-500">{{ $v->fecha_venta?->format('d/m/Y H:i') ?? $v->created_at->format('d/m/Y H:i') }}</td>
                                                <td class="py-2 px-3">{{ $v->cliente?->name ?? 'Consumidor Final' }}</td>
                                                <td class="py-2 px-3">
                                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                                        {{ ucfirst($v->pago?->metodo_pago ?? 'Efectivo') }}
                                                    </span>
                                                </td>
                                                <td class="py-2 px-3 text-right font-semibold text-slate-900">Bs. {{ number_format($v->total, 2) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="py-8 text-center text-slate-400">Sin ventas en este período</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- SECCIÓN 2: VENTAS DE RECOJO EN SUCURSAL (ENTREGAS ONLINE) -->
                <div class="space-y-6">
                    <!-- KPIS PRINCIPALES DE RECOJO -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Pedidos para Recojo</div>
                            <div class="mt-2 text-2xl font-bold text-slate-900">
                                {{ $datos['total_pedidos'] ?? 0 }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Compras online asignadas a la tienda</div>
                        </div>

                        <div class="rounded-3xl border border-emerald-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-emerald-800">Pedidos Entregados</div>
                            <div class="mt-2 text-2xl font-bold text-emerald-600">
                                {{ $datos['pedidos_entregados'] ?? 0 }}
                            </div>
                            <div class="mt-1 text-[11px] text-emerald-700">Efectividad: {{ $datos['tasa_efectividad'] ?? 0 }}%</div>
                        </div>

                        <div class="rounded-3xl border border-amber-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-amber-800">Pendientes en Tienda</div>
                            <div class="mt-2 text-2xl font-bold text-amber-600">
                                {{ $datos['pedidos_pendientes'] ?? 0 }}
                            </div>
                            <div class="mt-1 text-[11px] text-amber-700">Esperando retiro del cliente</div>
                        </div>

                        <div class="rounded-3xl border border-blue-100 bg-white p-5 shadow-sm">
                            <div class="text-xs font-semibold text-slate-500">Monto Entregado</div>
                            <div class="mt-2 text-2xl font-bold text-brand-blue">
                                Bs. {{ number_format($datos['total_monto_entregado'] ?? 0, 2) }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">Mercadería entregada en sucursal</div>
                        </div>
                    </div>

                    <!-- GRÁFICOS DE EFECTIVIDAD Y ACTIVIDAD -->
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- TASA DE EFECTIVIDAD -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-target /></span> Efectividad de entrega
                            </h3>

                            <div class="mt-6 flex flex-col sm:flex-row items-center gap-6">
                                <!-- GRÁFICO CIRCULAR SVG -->
                                <div class="relative size-36">
                                    <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                                        <path
                                            class="text-slate-100"
                                            stroke-width="3.8"
                                            stroke="currentColor"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                        <path
                                            class="text-emerald-500"
                                            stroke-dasharray="{{ $datos['tasa_efectividad'] ?? 0 }}, 100"
                                            stroke-width="3.8"
                                            stroke-linecap="round"
                                            stroke="currentColor"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                        <span class="text-2xl font-bold text-slate-900">{{ $datos['tasa_efectividad'] ?? 0 }}%</span>
                                        <span class="text-[9px] font-semibold text-slate-400">Entregados</span>
                                    </div>
                                </div>

                                <div class="space-y-3 flex-1">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-2 font-medium text-slate-700">
                                            <span class="size-3 rounded-full bg-emerald-500"></span> Entregados con éxito
                                        </span>
                                        <span class="font-semibold text-emerald-700">{{ $datos['pedidos_entregados'] ?? 0 }} pedidos</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="flex items-center gap-2 font-medium text-slate-700">
                                            <span class="size-3 rounded-full bg-amber-500"></span> Pendientes de retiro
                                        </span>
                                        <span class="font-semibold text-amber-700">{{ $datos['pedidos_pendientes'] ?? 0 }} pedidos</span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs border-t border-slate-100 pt-2">
                                        <span class="text-slate-500">Atendidos por tu usuario:</span>
                                        <span class="font-semibold text-brand-blue">{{ $datos['entregados_por_vendedor'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIVIDAD DE RECOJOS POR DÍA -->
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                            <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                                <span><x-lucide-calendar /></span> Pedidos por día
                            </h3>

                            @php
                                $diasRecojo = $datos['recojos_por_dia'] ?? [];
                                $maxRecojos = max(1, ...array_column($diasRecojo, 'total') ?: [1]);
                            @endphp

                            <div class="mt-6">
                                @if (empty($diasRecojo))
                                    <div class="py-12 text-center text-xs text-slate-400">
                                        Sin pedidos en estas fechas
                                    </div>
                                @else
                                    <div class="flex items-end gap-2 h-44 border-b border-slate-200 pb-2 overflow-x-auto">
                                        @foreach ($diasRecojo as $dia)
                                            @php
                                                $altura = max(8, round(($dia['total'] / $maxRecojos) * 100));
                                            @endphp
                                            <div class="flex flex-col items-center flex-1 min-w-[32px] group">
                                                <div class="relative w-full flex justify-center items-end h-36">
                                                    <div class="absolute -top-7 hidden group-hover:flex z-10 whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[10px] font-semibold text-white shadow-md">
                                                        {{ $dia['total'] }} pedidos ({{ $dia['entregados'] }} entregados)
                                                    </div>
                                                    <div
                                                        style="height: {{ $altura }}%;"
                                                        class="w-full max-w-[28px] rounded-t-lg bg-emerald-500 group-hover:bg-brand-orange transition-all duration-300 shadow-sm"
                                                    ></div>
                                                </div>
                                                <span class="mt-2 text-[10px] font-semibold text-slate-500">{{ $dia['etiqueta'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- TABLA DETALLADA DE PEDIDOS DE RECOJO -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="font-semibold text-slate-900 text-sm flex items-center gap-2">
                            <span><x-lucide-package /></span> Pedidos de recojo
                        </h3>

                        <div class="mt-4 overflow-x-auto max-h-80">
                            <table class="w-full text-left text-xs">
                                <thead class="sticky top-0 bg-slate-50 text-slate-500 font-semibold">
                                    <tr>
                                        <th class="py-2.5 px-3">Ticket</th>
                                        <th class="py-2.5 px-3">Fecha Compra</th>
                                        <th class="py-2.5 px-3">Comprador</th>
                                        <th class="py-2.5 px-3">Estado Recojo</th>
                                        <th class="py-2.5 px-3">Fecha / Hora Entrega</th>
                                        <th class="py-2.5 px-3">Atendido Por</th>
                                        <th class="py-2.5 px-3 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($datos['pedidos'] ?? [] as $p)
                                        @php
                                            $estadoEntrega = $p->entrega?->estado ?? 'pendiente';
                                        @endphp
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-2 px-3 font-semibold text-brand-blue">{{ $p->numero_venta }}</td>
                                            <td class="py-2 px-3 text-slate-500">{{ $p->fecha_venta?->format('d/m/Y H:i') ?? $p->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="py-2 px-3">{{ $p->cliente?->name ?? 'Cliente Web' }}</td>
                                            <td class="py-2 px-3">
                                                @if ($estadoEntrega === 'entregado')
                                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">Entregado</span>
                                                @else
                                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Listo en tienda</span>
                                                @endif
                                            </td>
                                            <td class="py-2 px-3 text-slate-600">
                                                @if ($estadoEntrega === 'entregado')
                                                    {{ $p->entrega?->fecha_programada?->format('d/m/Y') }} {{ $p->entrega?->hora_fin }}
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="py-2 px-3">{{ $p->entrega?->empleado?->name ?? 'Personal' }}</td>
                                            <td class="py-2 px-3 text-right font-semibold text-slate-900">Bs. {{ number_format($p->total, 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-8 text-center text-slate-400">Sin pedidos en este período</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-admin-shell>

