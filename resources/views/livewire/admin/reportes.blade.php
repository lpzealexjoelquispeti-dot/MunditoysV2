<x-admin-shell active="reportes">
    <div class="space-y-6">

        {{-- ENCABEZADO PRINCIPAL Y ACCIONES DE EXPORTACIÓN --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex size-9 items-center justify-center rounded-xl bg-brand-blue/10 text-xl text-brand-blue">📊</span>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Analítica y Reportes Ejecutivos</h1>
                </div>
                <p class="mt-1 text-sm text-slate-500">
                    Control estratégico de rentabilidad real (FIFO), comportamiento de usuarios, modelos 3D, inventario y rendimiento comercial.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                {{-- Botón Exportar PDF --}}
                <button
                    type="button"
                    wire:click="exportarPdf"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm transition hover:bg-red-700 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="exportarPdf" class="text-base">📄</span>
                    <span wire:loading wire:target="exportarPdf" class="inline-block size-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    <span>Descargar PDF</span>
                </button>

                {{-- Botón Exportar Excel --}}
                <button
                    type="button"
                    wire:click="exportarExcel"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                >
                    <span wire:loading.remove wire:target="exportarExcel" class="text-base">📊</span>
                    <span wire:loading wire:target="exportarExcel" class="inline-block size-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
                    <span>Descargar Excel</span>
                </button>
            </div>
        </div>

        {{-- NAVEGACIÓN ENTRE SECCIONES DE REPORTES --}}
        <div class="border-b border-slate-200">
            <nav class="-mb-px flex space-x-2 sm:space-x-6 overflow-x-auto">
                <button
                    type="button"
                    wire:click="cambiarSeccion('ventas')"
                    @class([
                        'whitespace-nowrap pb-3 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2',
                        'border-brand-blue text-brand-blue' => $seccion === 'ventas',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $seccion !== 'ventas'
                    ])
                >
                    <span>📈</span> Ventas y Rentabilidad (FIFO)
                </button>

                <button
                    type="button"
                    wire:click="cambiarSeccion('interacciones')"
                    @class([
                        'whitespace-nowrap pb-3 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2',
                        'border-brand-blue text-brand-blue' => $seccion === 'interacciones',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $seccion !== 'interacciones'
                    ])
                >
                    <span>👁️</span> Interacciones, 3D y Topics IA
                </button>

                <button
                    type="button"
                    wire:click="cambiarSeccion('inventario')"
                    @class([
                        'whitespace-nowrap pb-3 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2',
                        'border-brand-blue text-brand-blue' => $seccion === 'inventario',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $seccion !== 'inventario'
                    ])
                >
                    <span>📦</span> Inventario, Valoración y Costos
                </button>

                <button
                    type="button"
                    wire:click="cambiarSeccion('comercial')"
                    @class([
                        'whitespace-nowrap pb-3 px-2 text-sm font-semibold border-b-2 transition flex items-center gap-2',
                        'border-brand-blue text-brand-blue' => $seccion === 'comercial',
                        'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' => $seccion !== 'comercial'
                    ])
                >
                    <span>🏢</span> Rendimiento Comercial y Sucursales
                </button>
            </nav>
        </div>

        {{-- BARRA PARAMETRIZADA DE FILTROS AVANZADOS --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                    <svg class="size-4 text-brand-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    Filtros y Parámetros del Reporte
                </span>

                <div class="flex items-center gap-2">
                    {{-- Botón Reset --}}
                    <button
                        type="button"
                        wire:click="reiniciarFiltros"
                        class="text-xs font-semibold text-slate-500 hover:text-brand-blue transition"
                    >
                        ↺ Restablecer valores
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                {{-- Selector de Periodo Rápido --}}
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Rango Temporal Predefinido</label>
                    <select
                        wire:model.live="periodoPreset"
                        class="w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                        <option value="este_mes">Este Mes (Mes en curso)</option>
                        <option value="mes_anterior">Mes Anterior</option>
                        <option value="ultimos_30_dias">Últimos 30 días</option>
                        <option value="ultimos_7_dias">Últimos 7 días</option>
                        <option value="hoy">Sólo Hoy</option>
                        <option value="este_ano">Año en curso (Acumulado)</option>
                        <option value="personalizado">Rango Personalizado...</option>
                    </select>
                </div>

                {{-- Fecha Desde --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Desde</label>
                    <input
                        type="date"
                        wire:model.live="fechaDesde"
                        class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                </div>

                {{-- Fecha Hasta --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Hasta</label>
                    <input
                        type="date"
                        wire:model.live="fechaHasta"
                        class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                </div>

                {{-- Filtro Sucursal --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Sucursal</label>
                    <select
                        wire:model.live="sucursalId"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                        <option value="todos">Todas las sucursales</option>
                        @foreach ($opciones['sucursales'] as $suc)
                            <option value="{{ $suc->id }}">{{ $suc->nombre }} ({{ $suc->ciudad }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtro Categoría --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Categoría</label>
                    <select
                        wire:model.live="categoriaId"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                    >
                        <option value="todos">Todas las categorías</option>
                        @foreach ($opciones['categorias'] as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Filtros Específicos por Sección --}}
                @if ($seccion === 'ventas')
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Canal de Venta</label>
                        <select
                            wire:model.live="tipoVenta"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los canales</option>
                            <option value="online">Tienda Online / Web</option>
                            <option value="fisica">Sucursal Física / Mostrador</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Método de Pago</label>
                        <select
                            wire:model.live="metodoPago"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los métodos</option>
                            @foreach ($opciones['metodos_pago'] as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Vendedor</label>
                        <select
                            wire:model.live="vendedorId"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los vendedores</option>
                            @foreach ($opciones['vendedores'] as $vend)
                                <option value="{{ $vend->id }}">{{ $vend->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($seccion === 'interacciones')
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Tipo de Evento</label>
                        <select
                            wire:model.live="tipoInteraccion"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los eventos</option>
                            @foreach ($opciones['tipos_interaccion'] as $tipo => $etiqueta)
                                <option value="{{ $tipo }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($seccion === 'inventario')
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Estado de Stock</label>
                        <select
                            wire:model.live="estadoStock"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los estados</option>
                            <option value="optimo">Stock Óptimo</option>
                            <option value="bajo_minimo">Bajo Mínimo (Alerta)</option>
                            <option value="agotado">Agotados (Quiebre)</option>
                        </select>
                    </div>
                @elseif ($seccion === 'comercial')
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Asesor Evaluado</label>
                        <select
                            wire:model.live="vendedorId"
                            class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                        >
                            <option value="todos">Todos los asesores</option>
                            @foreach ($opciones['vendedores'] as $vend)
                                <option value="{{ $vend->id }}">{{ $vend->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        {{-- CONTENIDO DINÁMICO POR SECCIÓN --}}
        @if ($seccion === 'ventas')
            {{-- ========== SECCIÓN 1: VENTAS Y RENTABILIDAD ========== --}}
            <div class="space-y-6">
                {{-- KPI Cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Ingresos Totales</span>
                        <div class="mt-2 text-2xl font-black text-slate-900">Bs. {{ number_format($datos['kpis']['total_ingresos'], 2) }}</div>
                        <div class="mt-1 text-xs text-emerald-600 font-semibold">Ventas Brutas Facturadas</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Costo FIFO Lotes</span>
                        <div class="mt-2 text-2xl font-black text-slate-700">Bs. {{ number_format($datos['kpis']['total_costo'], 2) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Costo de adquisición real</div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Ganancia Real</span>
                        <div class="mt-2 text-2xl font-black text-emerald-600">Bs. {{ number_format($datos['kpis']['ganancia_bruta'], 2) }}</div>
                        <div class="mt-1 text-xs text-emerald-700 font-bold">Margen: {{ $datos['kpis']['margen_promedio'] }}%</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Pedidos</span>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($datos['kpis']['total_pedidos']) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Órdenes completadas</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Ticket Promedio</span>
                        <div class="mt-2 text-2xl font-black text-brand-blue">Bs. {{ number_format($datos['kpis']['ticket_promedio'], 2) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Gasto medio por cliente</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Unidades Vendidas</span>
                        <div class="mt-2 text-2xl font-black text-brand-orange">{{ number_format($datos['kpis']['unidades_vendidas']) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Desc: Bs. {{ number_format($datos['kpis']['descuentos_aplicados'], 2) }}</div>
                    </div>
                </div>

                {{-- Gráficos Visuales y Desglose --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {{-- Gráfico Serie Cronológica --}}
                    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Tendencia de Ingresos y Ganancia</h3>
                            <span class="text-xs text-slate-400">Por Día</span>
                        </div>

                        @if (count($datos['serie_cronologica']) > 0)
                            @php
                                $maxIngreso = max(collect($datos['serie_cronologica'])->max('ingresos') ?? 1, 1);
                            @endphp
                            <div class="space-y-3 pt-2">
                                @foreach ($datos['serie_cronologica'] as $punto)
                                    @php
                                        $pctIngreso = min(round(($punto['ingresos'] / $maxIngreso) * 100), 100);
                                        $pctGanancia = $punto['ingresos'] > 0 ? min(round(($punto['ganancia'] / $maxIngreso) * 100), 100) : 0;
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="font-bold text-slate-700">{{ $punto['fecha'] }}</span>
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-500">{{ $punto['pedidos'] }} ped.</span>
                                                <span class="font-bold text-slate-900">Bs. {{ number_format($punto['ingresos'], 2) }}</span>
                                                <span class="font-bold text-emerald-600">(Bs. {{ number_format($punto['ganancia'], 2) }})</span>
                                            </div>
                                        </div>
                                        <div class="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex">
                                            <div class="bg-brand-blue rounded-l-full" style="width: {{ $pctIngreso }}%" title="Ingresos: Bs. {{ $punto['ingresos'] }}"></div>
                                            <div class="bg-emerald-500" style="width: {{ $pctGanancia }}%" title="Ganancia: Bs. {{ $punto['ganancia'] }}"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-end gap-4 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand-blue"></span> Ingresos Brutos</span>
                                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> Margen Neto</span>
                            </div>
                        @else
                            <div class="py-12 text-center text-sm text-slate-400">No hay movimientos registrados para graficar en el rango seleccionado.</div>
                        @endif
                    </div>

                    {{-- Desglose por Canal y Método de Pago --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-3">Ventas por Canal</h3>
                            @php
                                $totalCanales = max(array_sum($datos['ventas_por_canal']), 1);
                                $pctOnline = round((($datos['ventas_por_canal']['online'] ?? 0) / $totalCanales) * 100, 1);
                                $pctFisica = round((($datos['ventas_por_canal']['fisica'] ?? 0) / $totalCanales) * 100, 1);
                            @endphp
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs">
                                    <span class="font-medium text-slate-600">🌐 Tienda Online</span>
                                    <span class="font-bold text-slate-900">Bs. {{ number_format($datos['ventas_por_canal']['online'] ?? 0, 2) }} ({{ $pctOnline }}%)</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full bg-brand-blue rounded-full" style="width: {{ $pctOnline }}%"></div>
                                </div>

                                <div class="flex justify-between text-xs pt-1">
                                    <span class="font-medium text-slate-600">🏬 Sucursales Físicas</span>
                                    <span class="font-bold text-slate-900">Bs. {{ number_format($datos['ventas_por_canal']['fisica'] ?? 0, 2) }} ({{ $pctFisica }}%)</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full bg-brand-orange rounded-full" style="width: {{ $pctFisica }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-5">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-3">Métodos de Pago</h3>
                            <div class="space-y-2.5">
                                @forelse ($datos['ventas_por_metodo'] as $metodo => $monto)
                                    @php
                                        $pctM = round(($monto / $totalCanales) * 100, 1);
                                    @endphp
                                    <div>
                                        <div class="flex justify-between text-xs">
                                            <span class="font-medium capitalize text-slate-600">{{ $metodo }}</span>
                                            <span class="font-bold text-slate-800">Bs. {{ number_format($monto, 2) }}</span>
                                        </div>
                                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden mt-1">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pctM }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400">Sin métodos registrados</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabla Top Juguetes Rentables --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 mb-4">Top 10 Juguetes con Mayor Margen Real de Ganancia</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Juguete</th>
                                    <th class="px-4 py-3">Categoría</th>
                                    <th class="px-4 py-3 text-center">Unidades</th>
                                    <th class="px-4 py-3 text-right">Facturación</th>
                                    <th class="px-4 py-3 text-right">Costo FIFO</th>
                                    <th class="px-4 py-3 text-right">Ganancia Neta</th>
                                    <th class="px-4 py-3 text-right">% Margen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($datos['top_productos'] as $tp)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $tp['nombre'] }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ $tp['categoria'] }}</td>
                                        <td class="px-4 py-3 text-center font-bold">{{ $tp['unidades'] }}</td>
                                        <td class="px-4 py-3 text-right font-medium">Bs. {{ number_format($tp['ingresos'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-slate-500">Bs. {{ number_format($tp['costos'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-emerald-600">Bs. {{ number_format($tp['ganancia'], 2) }}</td>
                                        <td class="px-4 py-3 text-right font-bold">
                                            <span class="inline-flex rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                                                {{ $tp['margen_pct'] }}%
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-8 text-center text-slate-400">No se registraron ventas en el periodo seleccionado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif ($seccion === 'interacciones')
            {{-- ========== SECCIÓN 2: INTERACCIONES, 3D Y TOPICS IA ========== --}}
            <div class="space-y-6">
                {{-- KPI Cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Interacciones</span>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($datos['kpis']['total_interacciones']) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Eventos de navegación</div>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-sky-800">Vistas de Catálogo</span>
                        <div class="mt-2 text-2xl font-black text-sky-600">{{ number_format($datos['kpis']['total_vistas_producto']) }}</div>
                        <div class="mt-1 text-xs text-sky-700 font-medium">Impresiones de detalle</div>
                    </div>

                    <div class="rounded-2xl border border-purple-200 bg-purple-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Visor 3D Interactivo</span>
                        <div class="mt-2 text-2xl font-black text-purple-600">{{ number_format($datos['kpis']['total_interacciones_3d']) }}</div>
                        <div class="mt-1 text-xs text-purple-700 font-bold">Modelos Tripo explorados</div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Añadidos al Carrito</span>
                        <div class="mt-2 text-2xl font-black text-emerald-600">{{ number_format($datos['kpis']['total_agregados_carrito']) }}</div>
                        <div class="mt-1 text-xs text-emerald-700 font-medium">Intención de compra</div>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Conversión Visual</span>
                        <div class="mt-2 text-2xl font-black text-amber-600">{{ $datos['kpis']['tasa_conversion_visual'] }}%</div>
                        <div class="mt-1 text-xs text-amber-700 font-medium">Vistas que van a carrito</div>
                    </div>

                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Asistente Virtual IA</span>
                        <div class="mt-2 text-2xl font-black text-indigo-600">{{ number_format($datos['kpis']['total_conversaciones_ia']) }}</div>
                        <div class="mt-1 text-xs text-indigo-700 font-medium">{{ number_format($datos['kpis']['total_mensajes_ia']) }} mensajes</div>
                    </div>
                </div>

                {{-- Funnel y Topics IA --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Embudo Visual de Interacción --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Embudo de Interacción Visual del Catálogo</h3>
                        @php
                            $maxStep = max($datos['kpis']['total_vistas_producto'], 1);
                            $pctVistas = 100;
                            $pct3D = round(($datos['kpis']['total_interacciones_3d'] / $maxStep) * 100, 1);
                            $pctCarrito = round(($datos['kpis']['total_agregados_carrito'] / $maxStep) * 100, 1);
                        @endphp
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-xs mb-1 font-bold text-slate-700">
                                    <span>1. Vistas de Producto (Catálogo)</span>
                                    <span>{{ number_format($datos['kpis']['total_vistas_producto']) }} (100%)</span>
                                </div>
                                <div class="h-4 w-full rounded-xl bg-sky-100 overflow-hidden">
                                    <div class="h-full bg-sky-500 rounded-xl" style="width: 100%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs mb-1 font-bold text-purple-700">
                                    <span>2. Interacción con Gemelo Digital 3D</span>
                                    <span>{{ number_format($datos['kpis']['total_interacciones_3d']) }} ({{ $pct3D }}%)</span>
                                </div>
                                <div class="h-4 w-full rounded-xl bg-purple-100 overflow-hidden">
                                    <div class="h-full bg-purple-600 rounded-xl" style="width: {{ max($pct3D, 5) }}%"></div>
                                </div>
                            </div>

                            <div>
                                <div class="flex justify-between text-xs mb-1 font-bold text-emerald-700">
                                    <span>3. Adición al Carrito de Compras</span>
                                    <span>{{ number_format($datos['kpis']['total_agregados_carrito']) }} ({{ $pctCarrito }}%)</span>
                                </div>
                                <div class="h-4 w-full rounded-xl bg-emerald-100 overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-xl" style="width: {{ max($pctCarrito, 5) }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 rounded-xl bg-slate-50 p-4 border border-slate-100 text-xs text-slate-600">
                            💡 <strong>Insight MundiToys:</strong> Los clientes que interactúan con modelos 3D generados con IA tienen mayor probabilidad de conversión hacia el carrito de compras.
                        </div>
                    </div>

                    {{-- Topics Asistente IA y Búsquedas --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-3">Tópicos Recurrentes en el Asistente IA</h3>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($datos['topicos_ia'] as $tema => $frec)
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 border border-indigo-100 px-3 py-1.5 text-xs font-semibold text-indigo-700">
                                        <span>💬</span> {{ $tema }}
                                        <span class="rounded-full bg-indigo-200/60 px-1.5 py-0.5 text-[10px] font-bold text-indigo-900">{{ $frec }}</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400">Sin consultas registradas al asistente</span>
                                @endforelse
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-3">Términos de Búsqueda Frecuentes</h3>
                            <div class="flex flex-wrap gap-2">
                                @forelse ($datos['top_busquedas'] as $termino => $cant)
                                    <span class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-800">
                                        🔍 "{{ $termino }}"
                                        <span class="rounded-full bg-white px-1.5 py-0.5 text-[10px] font-bold text-slate-600 shadow-sm">{{ $cant }}</span>
                                    </span>
                                @empty
                                    <span class="text-xs text-slate-400">Sin búsquedas registradas</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabla Top Juguetes --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 mb-4">Ranking de Popularidad e Interés de Juguetes</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Juguete</th>
                                    <th class="px-4 py-3">Categoría</th>
                                    <th class="px-4 py-3 text-center">Vistas</th>
                                    <th class="px-4 py-3 text-center">Clics Visor 3D</th>
                                    <th class="px-4 py-3 text-center">Al Carrito</th>
                                    <th class="px-4 py-3 text-right">Total Eventos</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($datos['top_juguetes'] as $tj)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $tj['nombre'] }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ $tj['categoria'] }}</td>
                                        <td class="px-4 py-3 text-center font-medium">{{ $tj['vistas'] }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-purple-600">{{ $tj['interacciones_3d'] }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-emerald-600">{{ $tj['carritos'] }}</td>
                                        <td class="px-4 py-3 text-right font-black text-slate-900">{{ $tj['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-slate-400">No hay interacciones registradas en este periodo.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif ($seccion === 'inventario')
            {{-- ========== SECCIÓN 3: INVENTARIO, VALORACIÓN Y COSTOS ========== --}}
            <div class="space-y-6">
                {{-- KPI Cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Stock Total</span>
                        <div class="mt-2 text-2xl font-black text-slate-900">{{ number_format($datos['kpis']['unidades_stock_total']) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Unidades en almacenes</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Valuación Costo FIFO</span>
                        <div class="mt-2 text-2xl font-black text-slate-700">Bs. {{ number_format($datos['kpis']['valor_costo_total'], 2) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Capital invertido en lotes</div>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-sky-800">Valuación Catálogo</span>
                        <div class="mt-2 text-2xl font-black text-sky-600">Bs. {{ number_format($datos['kpis']['valor_venta_total'], 2) }}</div>
                        <div class="mt-1 text-xs text-sky-700 font-medium">Precio público proyectado</div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Margen Potencial</span>
                        <div class="mt-2 text-2xl font-black text-emerald-600">Bs. {{ number_format($datos['kpis']['margen_potencial'], 2) }}</div>
                        <div class="mt-1 text-xs text-emerald-700 font-bold">Retorno: {{ $datos['kpis']['margen_porcentaje'] }}%</div>
                    </div>

                    <div class="rounded-2xl border border-red-200 bg-red-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-red-800">Quiebres / Alertas</span>
                        <div class="mt-2 text-2xl font-black text-red-600">{{ $datos['kpis']['productos_agotados'] }} / {{ $datos['kpis']['productos_bajo_minimo'] }}</div>
                        <div class="mt-1 text-xs text-red-700 font-medium">Agotados / Bajo mínimo</div>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Mermas y Daños</span>
                        <div class="mt-2 text-2xl font-black text-amber-600">{{ $datos['kpis']['unidades_merma'] }} u.</div>
                        <div class="mt-1 text-xs text-amber-700 font-medium">Bajas de stock auditadas</div>
                    </div>
                </div>

                {{-- Distribución por Categoría y Lotes con Alerta --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Capital Inmovilizado por Categoría</h3>
                        <div class="space-y-3">
                            @php
                                $maxVal = max(collect($datos['stock_por_categoria'])->max('valor_costo') ?? 1, 1);
                            @endphp
                            @forelse ($datos['stock_por_categoria'] as $cat)
                                @php
                                    $pct = round(($cat['valor_costo'] / $maxVal) * 100);
                                @endphp
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-800">{{ $cat['categoria'] }} ({{ $cat['unidades'] }} u.)</span>
                                        <span class="font-semibold text-slate-700">Bs. {{ number_format($cat['valor_costo'], 2) }}</span>
                                    </div>
                                    <div class="h-2.5 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full bg-brand-blue rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400">Sin datos de inventario</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Lotes Próximos a Vencer o Caducados</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2">Lote</th>
                                        <th class="px-3 py-2">Juguete</th>
                                        <th class="px-3 py-2 text-center">Unidades</th>
                                        <th class="px-3 py-2 text-center">Fecha</th>
                                        <th class="px-3 py-2 text-right">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($datos['lotes_alerta'] as $la)
                                        <tr>
                                            <td class="px-3 py-2 font-mono font-semibold">{{ $la['lote'] }}</td>
                                            <td class="px-3 py-2">{{ $la['producto'] }}</td>
                                            <td class="px-3 py-2 text-center font-bold">{{ $la['unidades'] }}</td>
                                            <td class="px-3 py-2 text-center">{{ $la['fecha'] }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <span @class([
                                                    'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold',
                                                    'bg-red-100 text-red-700' => $la['estado'] === 'Vencido',
                                                    'bg-amber-100 text-amber-700' => $la['estado'] === 'Por Vencer',
                                                ])>
                                                    {{ $la['estado'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-emerald-600 font-semibold">
                                                ✓ Todos los lotes se encuentran vigentes y dentro de su periodo óptimo.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Tabla Detalle Inventario --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-slate-900 mb-4">Auditoría Detallada de Existencias</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-4 py-3">Código</th>
                                    <th class="px-4 py-3">Producto</th>
                                    <th class="px-4 py-3">Categoría</th>
                                    <th class="px-4 py-3">Sucursal - Almacén</th>
                                    <th class="px-4 py-3 text-center">Stock</th>
                                    <th class="px-4 py-3 text-center">Mínimo</th>
                                    <th class="px-4 py-3 text-center">Estado</th>
                                    <th class="px-4 py-3 text-right">Val. Costo</th>
                                    <th class="px-4 py-3 text-right">Val. Venta</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($datos['inventario_registros'] as $inv)
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="px-4 py-3 font-mono font-medium text-slate-600">{{ $inv['codigo'] }}</td>
                                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $inv['producto'] }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ $inv['categoria'] }}</td>
                                        <td class="px-4 py-3 text-slate-500">{{ $inv['sucursal'] }} - {{ $inv['almacen'] }}</td>
                                        <td class="px-4 py-3 text-center font-bold text-slate-900">{{ $inv['stock_actual'] }}</td>
                                        <td class="px-4 py-3 text-center text-slate-400">{{ $inv['stock_minimo'] }}</td>
                                        <td class="px-4 py-3 text-center">
                                            <span @class([
                                                'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold',
                                                'bg-emerald-100 text-emerald-800' => $inv['condicion'] === 'Óptimo',
                                                'bg-amber-100 text-amber-800' => $inv['condicion'] === 'Bajo Mínimo',
                                                'bg-red-100 text-red-800' => $inv['condicion'] === 'Agotado',
                                            ])>
                                                {{ $inv['condicion'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right text-slate-600 font-medium">Bs. {{ number_format($inv['valor_costo'], 2) }}</td>
                                        <td class="px-4 py-3 text-right text-brand-blue font-bold">Bs. {{ number_format($inv['valor_venta'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-4 py-8 text-center text-slate-400">Sin registros que coincidan con los filtros.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @elseif ($seccion === 'comercial')
            {{-- ========== SECCIÓN 4: RENDIMIENTO COMERCIAL Y SUCURSALES ========== --}}
            <div class="space-y-6">
                {{-- KPI Cards --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-2xl border border-purple-200 bg-purple-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Sucursal Líder</span>
                        <div class="mt-2 text-xl font-black text-purple-700 truncate">{{ $datos['kpis']['sucursal_top'] }}</div>
                        <div class="mt-1 text-xs text-purple-600 font-medium">Mayor volumen facturado</div>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-sky-800">Asesor Destacado</span>
                        <div class="mt-2 text-xl font-black text-sky-700 truncate">{{ $datos['kpis']['vendedor_top'] }}</div>
                        <div class="mt-1 text-xs text-sky-600 font-medium">Mayor cantidad de ventas</div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Éxito en Entregas</span>
                        <div class="mt-2 text-2xl font-black text-emerald-600">{{ $datos['kpis']['tasa_exito_entrega'] }}%</div>
                        <div class="mt-1 text-xs text-emerald-700 font-medium">{{ $datos['kpis']['total_entregas'] }} órdenes despachadas</div>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Satisfacción (CSAT)</span>
                        <div class="mt-2 text-2xl font-black text-amber-600">{{ $datos['kpis']['calificacion_promedio'] }} / 5.0 ★</div>
                        <div class="mt-1 text-xs text-amber-700 font-medium">{{ $datos['kpis']['total_resenas'] }} valoraciones</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Entregas Exitosas</span>
                        <div class="mt-2 text-2xl font-black text-emerald-600">{{ $datos['resumen_entregas']['completadas'] }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Completadas sin reclamos</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Envíos en Ruta</span>
                        <div class="mt-2 text-2xl font-black text-brand-blue">{{ $datos['resumen_entregas']['en_camino'] }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Pendientes: {{ $datos['resumen_entregas']['pendientes'] }}</div>
                    </div>
                </div>

                {{-- Sucursales y Vendedores --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {{-- Facturación Sucursales --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Facturación por Sucursal</h3>
                        <div class="space-y-4">
                            @php
                                $maxSuc = max(collect($datos['rendimiento_sucursales'])->max('facturacion') ?? 1, 1);
                            @endphp
                            @forelse ($datos['rendimiento_sucursales'] as $rs)
                                @php
                                    $pctS = round(($rs['facturacion'] / $maxSuc) * 100);
                                @endphp
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-900">{{ $rs['nombre'] }} <span class="text-slate-400 font-normal">({{ $rs['ciudad'] }})</span></span>
                                        <span class="font-bold text-brand-blue">Bs. {{ number_format($rs['facturacion'], 2) }}</span>
                                    </div>
                                    <div class="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex">
                                        <div class="h-full bg-brand-blue rounded-full" style="width: {{ max($pctS, 3) }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[11px] text-slate-500 mt-1">
                                        <span>{{ $rs['pedidos'] }} órdenes</span>
                                        <span>Ticket prom: Bs. {{ number_format($rs['ticket_promedio'], 2) }}</span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400">Sin ventas por sucursal registradas</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Ranking de Vendedores --}}
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Ranking de Asesores Comerciales</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="bg-slate-50 text-[10px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2">Asesor</th>
                                        <th class="px-3 py-2 text-center">Ventas</th>
                                        <th class="px-3 py-2 text-right">Facturado</th>
                                        <th class="px-3 py-2 text-right">Ticket Prom.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($datos['ranking_vendedores'] as $rv)
                                        <tr>
                                            <td class="px-3 py-2.5">
                                                <div class="font-bold text-slate-900">{{ $rv['nombre'] }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $rv['email'] }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 text-center font-black">{{ $rv['ventas_conteo'] }}</td>
                                            <td class="px-3 py-2.5 text-right font-bold text-emerald-600">Bs. {{ number_format($rv['total_vendido'], 2) }}</td>
                                            <td class="px-3 py-2.5 text-right text-slate-500">Bs. {{ number_format($rv['ticket_promedio'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-3 py-6 text-center text-slate-400">Sin registros de ventas por asesor en el periodo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- CSAT Estrellas y Logística --}}
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Distribución de Reseñas de Clientes</h3>
                        <div class="space-y-3">
                            @php
                                $totalR = max($datos['kpis']['total_resenas'], 1);
                            @endphp
                            @foreach ([5 => '★★★★★', 4 => '★★★★☆', 3 => '★★★☆☆', 2 => '★★☆☆☆', 1 => '★☆☆☆☆'] as $stars => $display)
                                @php
                                    $cnt = $datos['distribucion_estrellas'][$stars] ?? 0;
                                    $pctR = round(($cnt / $totalR) * 100, 1);
                                @endphp
                                <div>
                                    <div class="flex justify-between text-xs mb-1 font-semibold">
                                        <span class="text-amber-500 tracking-wider">{{ $display }} <span class="text-slate-600 font-normal">({{ $stars }} estrellas)</span></span>
                                        <span class="text-slate-800 font-bold">{{ $cnt }} ({{ $pctR }}%)</span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full bg-amber-400 rounded-full" style="width: {{ $pctR }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wide mb-4">Estado Operativo de Entregas</h3>
                        <div class="grid grid-cols-2 gap-4 pt-2">
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                <span class="text-2xl">✅</span>
                                <div class="mt-2 text-2xl font-black text-emerald-700">{{ $datos['resumen_entregas']['completadas'] }}</div>
                                <span class="text-xs font-semibold text-emerald-800">Entregados con Éxito</span>
                            </div>

                            <div class="rounded-xl border border-sky-100 bg-sky-50/50 p-4 text-center">
                                <span class="text-2xl">🚚</span>
                                <div class="mt-2 text-2xl font-black text-sky-700">{{ $datos['resumen_entregas']['en_camino'] }}</div>
                                <span class="text-xs font-semibold text-sky-800">En Ruta / Despacho</span>
                            </div>

                            <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 text-center">
                                <span class="text-2xl">⏳</span>
                                <div class="mt-2 text-2xl font-black text-amber-700">{{ $datos['resumen_entregas']['pendientes'] }}</div>
                                <span class="text-xs font-semibold text-amber-800">Pendientes de Asignación</span>
                            </div>

                            <div class="rounded-xl border border-red-100 bg-red-50/50 p-4 text-center">
                                <span class="text-2xl">⚠️</span>
                                <div class="mt-2 text-2xl font-black text-red-700">{{ $datos['resumen_entregas']['fallidas'] }}</div>
                                <span class="text-xs font-semibold text-red-800">Fallidos o Devueltos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-admin-shell>


