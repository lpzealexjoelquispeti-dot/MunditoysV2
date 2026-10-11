<x-admin-shell :active="match ($pestanaActiva) { 'reportes' => 'reportes-logistica', 'completados' => 'historial-logistica', default => 'logistica' }">
    <div class="space-y-6">
        @if (in_array($pestanaActiva, ['tablero', 'mapa'], true))
            <!-- ENCABEZADO Y SELECTOR DE FECHA -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold tracking-[.18em] text-brand-orange">
                        <span><x-lucide-truck /></span>
                        <span>Panel de Despacho y Envíos</span>
                    </div>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-semibold text-brand-ink">Logística</h1>
                </div>

                <!-- ACCIONES Y SELECTOR RÁPIDO DE FECHA -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex flex-wrap items-center gap-2 rounded-2xl border border-orange-100 bg-white p-2 shadow-2xs">
                        <span class="text-xs font-semibold text-slate-600 pl-2">Fecha:</span>
                        <input
                            type="date"
                            wire:model.live="fechaFiltro"
                            class="rounded-xl border border-slate-300 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-brand-ink focus:border-brand-blue"
                        >
                        <button
                            type="button"
                            wire:click="irAHoy"
                            @class([
                                'rounded-xl px-3 py-1.5 text-xs font-semibold transition cursor-pointer',
                                'bg-brand-blue text-white shadow-2xs' => $fechaFiltro === now()->toDateString(),
                                'bg-slate-100 text-slate-700 hover:bg-slate-200' => $fechaFiltro !== now()->toDateString(),
                            ])
                        >
                            Hoy
                        </button>
                        <button
                            type="button"
                            wire:click="irAManana"
                            @class([
                                'rounded-xl px-3 py-1.5 text-xs font-semibold transition cursor-pointer',
                                'bg-brand-blue text-white shadow-2xs' => $fechaFiltro === now()->addDay()->toDateString(),
                                'bg-slate-100 text-slate-700 hover:bg-slate-200' => $fechaFiltro !== now()->addDay()->toDateString(),
                            ])
                        >
                            Mañana
                        </button>
                    </div>
                </div>
            </div>

        @endif

        <!-- ALERTA FLASH -->
        @if ($mensajeAlerta)
            @php
                $alertaClase = match($tipoAlerta) {
                    'danger' => 'border-rose-200 bg-rose-50 text-brand-red',
                    'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
                    'info' => 'border-blue-200 bg-blue-50 text-brand-blue',
                    default => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                };
            @endphp
            <div class="flex items-center justify-between gap-3 rounded-2xl border p-4 text-xs font-medium {{ $alertaClase }}">
                <div class="flex items-center gap-2">
                    <span class="text-base font-semibold"><x-lucide-info /></span>
                    <span>{{ $mensajeAlerta }}</span>
                </div>
                <button type="button" wire:click="descartarAlerta" class="text-sm font-semibold opacity-70 hover:opacity-100"><x-lucide-x /></button>
            </div>
        @endif

        @if ($pestanaActiva === 'tablero')
            <!-- TARJETAS DE MÉTRICAS DEL DÍA -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
                    <span class="text-[11px] font-semibold text-slate-500">Despachos del Día</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-brand-ink">{{ $conteoTotal }}</span>
                        <span class="text-xs text-slate-400">Total</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 shadow-2xs">
                    <span class="text-[11px] font-semibold text-amber-800">Pendientes</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-amber-900">{{ $conteoPendientes }}</span>
                        <span class="text-xs text-amber-700 font-medium">Por salir</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 shadow-2xs">
                    <span class="text-[11px] font-semibold text-brand-blue">En Camino</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-brand-blue">{{ $conteoEnCamino }}</span>
                        <span class="text-xs text-blue-700 font-medium">En ruta</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-2xs">
                    <span class="text-[11px] font-semibold text-emerald-800">Entregados</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-emerald-900">{{ $conteoEntregados }}</span>
                        <span class="text-xs text-emerald-700 font-medium">Completados</span>
                    </div>
                </div>

                <div class="col-span-2 sm:col-span-1 rounded-2xl border border-rose-200 bg-rose-50/60 p-4 shadow-2xs">
                    <span class="text-[11px] font-semibold text-brand-red">Cancelados</span>
                    <div class="mt-1 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-brand-red">{{ $conteoCancelados }}</span>
                        <span class="text-xs text-rose-700 font-medium">Incidencias</span>
                    </div>
                </div>
            </div>

        @endif

        <!-- CONTENIDO PESTAÑA 1: TABLERO DE ENVÍOS -->
        @if ($pestanaActiva === 'tablero')
            <div class="space-y-4">
                <!-- FILTROS DEL TABLERO -->
                <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between">
                    <div class="relative flex-1 max-w-md">
                        <span class="pointer-events-none absolute inset-y-0 left-3 grid place-items-center text-slate-400"><x-lucide-search /></span>
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="busqueda"
                            placeholder="Venta, cliente, teléfono o zona"
                            class="min-h-11 w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-xs text-brand-ink placeholder:text-slate-400 focus:border-brand-blue"
                        >
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="text-xs font-semibold text-slate-600">Estado:</label>
                        <select
                            wire:model.live="filtroEstado"
                            class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-brand-ink focus:border-brand-blue"
                        >
                            <option value="todos">Todos los estados</option>
                            <option value="pendiente">Pendientes</option>
                            <option value="en_camino">En camino</option>
                            <option value="entregado">Entregados</option>
                            <option value="cancelado">Cancelados</option>
                        </select>
                    </div>
                </div>

                <!-- LISTADO DE ENTREGAS -->
                <div class="rounded-3xl border border-orange-100 bg-white overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="border-b border-slate-100 bg-gradient-to-r from-orange-50/50 to-blue-50/50 text-[11px] font-semibold text-slate-500">
                                <tr>
                                    <th class="px-5 py-3.5">Pedido / Horario</th>
                                    <th class="px-5 py-3.5">Cliente & Destino</th>
                                    <th class="px-5 py-3.5">Juguetes</th>
                                    <th class="px-5 py-3.5">Repartidor</th>
                                    <th class="px-5 py-3.5 text-center">Estado</th>
                                    <th class="px-5 py-3.5 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($entregas as $entrega)
                                    <tr class="hover:bg-slate-50/80 transition" wire:key="entrega-{{ $entrega->id }}">
                                        <!-- PEDIDO & HORARIO MATEMÁTICO -->
                                        <td class="px-5 py-4">
                                            <div class="flex flex-col">
                                                <span class="font-semibold text-sm text-brand-blue">{{ $entrega->venta?->numero_venta ?? 'S/N' }}</span>
                                                <div class="mt-1 flex items-center gap-1.5 text-xs text-slate-600">
                                                    <span class="font-semibold text-brand-ink"><x-lucide-timer /> {{ $entrega->hora_inicio ? substr((string) $entrega->hora_inicio, 0, 5) : '--:--' }} a {{ $entrega->hora_fin ? substr((string) $entrega->hora_fin, 0, 5) : '--:--' }}</span>
                                                </div>
                                                <span class="text-[10px] text-slate-400">Total: Bs. {{ number_format((float) ($entrega->venta?->total ?? 0), 2) }}</span>
                                            </div>
                                        </td>

                                        <!-- CLIENTE & DESTINO -->
                                        <td class="px-5 py-4">
                                            <div>
                                                <p class="font-semibold text-brand-ink">{{ $entrega->venta?->cliente?->name ?? 'Cliente' }}</p>
                                                <p class="text-slate-500">
                                                    @if ($entrega->venta?->cliente?->telefono)
                                                        <a href="https://wa.me/591{{ preg_replace('/\D/', '', $entrega->venta->cliente->telefono) }}" target="_blank" rel="noopener" class="text-brand-blue hover:underline">
                                                            <x-lucide-phone /> {{ $entrega->venta->cliente->telefono }}
                                                        </a>
                                                    @else
                                                        <span class="text-slate-400">Sin teléfono</span>
                                                    @endif
                                                </p>
                                                <p class="mt-1 text-[11px] text-slate-600 font-medium">
                                                    <x-lucide-map-pin /> <span class="font-semibold text-brand-ink">{{ $entrega->direccion?->zona ?? 'La Paz' }}:</span> {{ Str::limit($entrega->direccion?->direccion_completa ?? 'Sin dirección', 35) }}
                                                </p>
                                            </div>
                                        </td>

                                        <!-- JUGUETES -->
                                        <td class="px-5 py-4">
                                            @php
                                                $detalles = $entrega->venta?->detalles ?? collect();
                                                $totalItems = $detalles->sum('cantidad');
                                            @endphp
                                            <div class="flex items-center gap-2">
                                                <span class="grid size-7 place-items-center rounded-lg bg-orange-50 font-semibold text-brand-orange text-xs">
                                                    {{ $totalItems }}
                                                </span>
                                                <div class="text-xs">
                                                    <p class="font-medium text-slate-700 truncate max-w-[150px]">
                                                        {{ $detalles->first()?->producto?->nombre ?? 'Juguetes' }}
                                                    </p>
                                                    @if ($detalles->count() > 1)
                                                        <span class="text-[10px] text-slate-400">+ {{ $detalles->count() - 1 }} más</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>

                                        <!-- REPARTIDOR ASIGNADO -->
                                        <td class="px-5 py-4">
                                            @if ($entrega->empleado)
                                                <div class="flex items-center gap-2">
                                                    <span class="grid size-6 place-items-center rounded-full bg-blue-100 text-brand-blue font-semibold text-[10px]">
                                                        {{ mb_substr($entrega->empleado->name, 0, 1) }}
                                                    </span>
                                                    <span class="font-semibold text-slate-800 text-xs">{{ $entrega->empleado->name }}</span>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-500">
                                                    Sin asignar
                                                </span>
                                            @endif
                                        </td>

                                        <!-- BADGE ESTADO -->
                                        <td class="px-5 py-4 text-center">
                                            @php
                                                $badgeClase = match($entrega->estado) {
                                                    'entregado' => 'bg-emerald-100 text-emerald-800',
                                                    'en_camino' => 'bg-blue-100 text-brand-blue',
                                                    'cancelado' => 'bg-rose-100 text-brand-red',
                                                    default => 'bg-amber-100 text-amber-800',
                                                };
                                                $badgeTexto = match($entrega->estado) {
                                                    'entregado' => 'Entregado',
                                                    'en_camino' => 'En camino',
                                                    'cancelado' => 'Cancelado',
                                                    default => 'Pendiente',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badgeClase }}">
                                                {{ $badgeTexto }}
                                            </span>
                                        </td>

                                        <!-- BOTONES DE ACCIÓN -->
                                        <td class="px-5 py-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- VER DETALLE -->
                                                <button
                                                    type="button"
                                                    wire:click="abrirDetalle({{ $entrega->id }})"
                                                    title="Ver Detalle Completo"
                                                    class="grid size-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:border-brand-blue hover:text-brand-blue transition cursor-pointer"
                                                >
                                                    <x-lucide-eye />
                                                </button>

                                                <!-- ASIGNAR / REASIGNAR -->
                                                @if ($entrega->estado !== 'entregado')
                                                    <button
                                                        type="button"
                                                        wire:click="abrirAsignar({{ $entrega->id }})"
                                                        title="Asignar Turno Matemático"
                                                        class="grid size-8 place-items-center rounded-lg border border-orange-200 bg-orange-50 text-brand-orange hover:bg-orange-100 transition cursor-pointer"
                                                    >
                                                        <x-lucide-timer />
                                                    </button>
                                                @endif

                                                <!-- INICIAR RUTA -->
                                                @if ($entrega->estado === 'pendiente')
                                                    <button
                                                        type="button"
                                                        wire:click="iniciarRuta({{ $entrega->id }})"
                                                        title="Poner en Camino"
                                                        class="grid size-8 place-items-center rounded-lg bg-brand-blue text-white hover:bg-blue-700 transition cursor-pointer"
                                                    >
                                                        <x-lucide-truck />
                                                    </button>
                                                @endif

                                                <!-- CONFIRMAR ENTREGA -->
                                                @if ($entrega->estado === 'en_camino')
                                                    <button
                                                        type="button"
                                                        wire:click="abrirConfirmarEntrega({{ $entrega->id }})"
                                                        title="Marcar como Entregado"
                                                        class="grid size-8 place-items-center rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition cursor-pointer"
                                                    >
                                                        <x-lucide-check />
                                                    </button>
                                                @endif

                                                <!-- CANCELAR ENVÍO -->
                                                @if ($entrega->estado !== 'cancelado' && $entrega->estado !== 'entregado')
                                                    <button
                                                        type="button"
                                                        wire:click="abrirCancelar({{ $entrega->id }})"
                                                        title="Cancelar con Justificación"
                                                        class="grid size-8 place-items-center rounded-lg border border-rose-200 bg-rose-50 text-brand-red hover:bg-rose-100 transition cursor-pointer"
                                                    >
                                                        <x-lucide-x />
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                            <div class="flex flex-col items-center justify-center">
                                                <span class="text-4xl"><x-lucide-truck /></span>
                                                <p class="mt-2 text-sm font-semibold text-slate-600">Sin entregas</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($entregas->hasPages())
                        <div class="border-t border-slate-100 p-4">
                            {{ $entregas->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- CONTENIDO PESTAÑA 2: MAPA GENERAL DE RUTAS -->
        @if ($pestanaActiva === 'mapa')
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- CONTENEDOR LEAFLET -->
                <div class="lg:col-span-2 rounded-3xl border border-orange-100 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                        <div>
                            <h3 class="font-semibold text-brand-ink text-sm sm:text-base">Ruta del día</h3>
                            <p class="text-xs text-slate-500">Entregas del {{ Carbon\Carbon::parse($fechaFiltro)->format('d/m/Y') }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-brand-blue">
                            {{ count($puntosMapa) }} entregas con pin
                        </span>
                    </div>

                    <div
                        wire:ignore
                        x-data="mundiToysLogisticaMap(@js($puntosMapa))"
                        class="relative w-full h-[450px] sm:h-[550px] rounded-2xl overflow-hidden border border-slate-200"
                    >
                        <div x-ref="mapaRutas" class="size-full z-10"></div>
                        <template x-if="cargando">
                            <div class="absolute inset-0 grid place-items-center bg-white/70 z-20 text-xs font-semibold text-slate-600">
                                Cargando mapa de rutas...
                            </div>
                        </template>
                        <template x-if="error">
                            <div class="absolute inset-x-4 top-4 rounded-xl bg-red-50 p-3 text-xs text-brand-red border border-red-200 z-20" x-text="error"></div>
                        </template>
                    </div>

                    <!-- LEYENDA DEL MAPA -->
                    <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-slate-600 pt-2 border-t border-slate-100">
                        <span class="font-semibold text-slate-700">Leyenda de Pines:</span>
                        <div class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-[#f97316]"></span> Pendiente</div>
                        <div class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-[#005ca8]"></span> En Camino</div>
                        <div class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-[#10b981]"></span> Entregado</div>
                        <div class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-[#dc2626]"></span> Cancelado</div>
                    </div>
                </div>

                <!-- LISTA LATERAL DE PARADAS -->
                <div class="rounded-3xl border border-orange-100 bg-white p-4 shadow-xs flex flex-col h-[520px] sm:h-[620px]">
                    <div class="border-b border-slate-100 pb-3 mb-3">
                        <h4 class="font-semibold text-brand-ink text-sm">Paradas Programadas</h4>
                        <p class="text-xs text-slate-400">Ordenadas por turno de salida</p>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-2.5 pr-1">
                        @forelse ($puntosMapa as $idx => $pto)
                            <div class="rounded-xl border border-slate-200 p-3 text-xs hover:border-brand-blue transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="grid size-5 place-items-center rounded-full bg-brand-orange text-white text-[10px] font-semibold">
                                            {{ $idx + 1 }}
                                        </span>
                                        <strong class="font-semibold text-brand-ink">{{ $pto['numero_venta'] }}</strong>
                                    </div>
                                    <span class="font-semibold text-brand-orange">{{ $pto['hora_inicio'] }}</span>
                                </div>
                                <p class="mt-1.5 font-semibold text-slate-700">{{ $pto['cliente'] }}</p>
                                <p class="text-[11px] text-slate-500"><x-lucide-map-pin /> {{ $pto['zona'] }}</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ $pto['direccion'] }}</p>
                                <div class="mt-2 flex items-center justify-between border-t border-slate-100 pt-1.5 text-[10px]">
                                    <span class="text-slate-500">Chofer: {{ $pto['repartidor'] }}</span>
                                    <button type="button" wire:click="abrirDetalle({{ $pto['id'] }})" class="font-semibold text-brand-blue hover:underline">
                                        Ver »
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-xs text-slate-400">
                                Sin paradas con coordenadas para este día.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- CONTENIDO PESTAÑA 3: HISTORIAL DE COMPLETADOS -->
        @if ($pestanaActiva === 'completados')
            <div class="rounded-3xl border border-orange-100 bg-white overflow-hidden shadow-xs">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h1 class="font-semibold text-brand-ink text-xl sm:text-2xl">Entregas completadas</h1>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-semibold text-slate-400">
                            <tr>
                                <th class="px-5 py-3.5">N° Venta</th>
                                <th class="px-5 py-3.5">Cliente</th>
                                <th class="px-5 py-3.5">Destino</th>
                                <th class="px-5 py-3.5">Repartidor</th>
                                <th class="px-5 py-3.5">Fecha y Hora Entrega</th>
                                <th class="px-5 py-3.5 text-right">Total</th>
                                <th class="px-5 py-3.5 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($entregas as $ent)
                                <tr class="hover:bg-slate-50 transition" wire:key="completado-{{ $ent->id }}">
                                    <td class="px-5 py-3.5 font-semibold text-brand-blue">{{ $ent->venta?->numero_venta ?? 'S/N' }}</td>
                                    <td class="px-5 py-3.5 font-semibold text-brand-ink">{{ $ent->venta?->cliente?->name ?? 'Cliente' }}</td>
                                    <td class="px-5 py-3.5 text-slate-600">{{ $ent->direccion?->zona ?? 'La Paz' }}</td>
                                    <td class="px-5 py-3.5 text-slate-700 font-medium">{{ $ent->empleado?->name ?? 'Repartidor' }}</td>
                                    <td class="px-5 py-3.5 text-slate-500">
                                        {{ $ent->entregado_at?->format('d/m/Y H:i') ?? ($ent->updated_at?->format('d/m/Y H:i') ?? 'Completado') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-semibold text-brand-ink">
                                        Bs. {{ number_format((float) ($ent->venta?->total ?? 0), 2) }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button
                                            type="button"
                                            wire:click="abrirDetalle({{ $ent->id }})"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:border-brand-blue hover:text-brand-blue"
                                        >
                                            Ver Ficha
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                        Sin despachos completados
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($entregas->hasPages())
                    <div class="border-t border-slate-100 p-4">
                        {{ $entregas->links() }}
                    </div>
                @endif
            </div>
        @endif

        <!-- CONTENIDO PESTAÑA 4: REPORTES Y ANALÍTICA DE LOGÍSTICA -->
        @if ($pestanaActiva === 'reportes' && $datosReporte)
            <div class="space-y-6">
                <!-- ENCABEZADO DE REPORTES Y ACCIONES DE DESCARGA -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-2xs">
                    <div>
                        <div class="flex items-center gap-2 text-xs font-semibold tracking-[.18em] text-brand-orange">
                            <span><x-lucide-chart-column /></span>
                            <span>Analítica Operativa de Despacho</span>
                        </div>
                        <h1 class="mt-1 text-xl sm:text-2xl font-semibold text-brand-ink">Reportes de logística</h1>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            wire:click="exportarReportePdf"
                            class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition shadow-2xs cursor-pointer"
                        >
                            <span><x-lucide-file-text /></span>
                            <span>Descargar PDF</span>
                        </button>
                        <button
                            type="button"
                            wire:click="exportarReporteExcel"
                            class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition shadow-2xs cursor-pointer"
                        >
                            <span><x-lucide-file-spreadsheet /></span>
                            <span>Descargar Excel</span>
                        </button>
                    </div>
                </div>

                <!-- BARRA DE FILTROS PARAMETRIZADOS -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <span class="text-xs font-semibold text-slate-500 flex items-center gap-1.5">
                            <svg class="size-4 text-brand-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filtros de Analítica y Auditoría
                        </span>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                wire:click="reiniciarFiltrosReporte"
                                class="text-xs font-semibold text-slate-500 hover:text-brand-orange transition flex items-center gap-1"
                            >
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Restablecer Filtros
                            </button>
                        </div>
                    </div>

                    <!-- PRESETS DE PERIODO -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-500 mr-1">Rango Temporal:</span>
                        @foreach ([
                            'hoy' => 'Hoy',
                            'ultimos_7_dias' => 'Últimos 7 días',
                            'ultimos_30_dias' => 'Últimos 30 días',
                            'este_mes' => 'Este Mes',
                            'este_ano' => 'Este Año',
                            'personalizado' => 'Personalizado'
                        ] as $clave => $etiqueta)
                            <button
                                type="button"
                                wire:click="aplicarPresetReporte('{{ $clave }}')"
                                @class([
                                    'px-3 py-1 text-xs rounded-lg font-medium transition cursor-pointer',
                                    'bg-brand-blue text-white shadow-2xs' => $reportePeriodoPreset === $clave,
                                    'bg-slate-100 text-slate-600 hover:bg-slate-200' => $reportePeriodoPreset !== $clave,
                                ])
                            >
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>

                    <!-- SELECTORES DE FILTROS EN CUADRÍCULA -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Desde</label>
                            <input
                                type="date"
                                wire:model.live="reporteFechaDesde"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Fecha Hasta</label>
                            <input
                                type="date"
                                wire:model.live="reporteFechaHasta"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Chofer / Repartidor</label>
                            <select
                                wire:model.live="reporteChoferId"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                            >
                                <option value="todos">Todos los choferes</option>
                                @if (isset($opcionesReporte['choferes']))
                                    @foreach ($opcionesReporte['choferes'] as $chf)
                                        <option value="{{ $chf->id }}">{{ $chf->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Estado de Entrega</label>
                            <select
                                wire:model.live="reporteEstadoEntrega"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                            >
                                <option value="todos">Todos los estados</option>
                                @if (isset($opcionesReporte['estados_entrega']))
                                    @foreach ($opcionesReporte['estados_entrega'] as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Turno de 90 Minutos</label>
                            <select
                                wire:model.live="reporteTurnoHorario"
                                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-brand-blue focus:ring-1 focus:ring-brand-blue"
                            >
                                <option value="todos">Todos los turnos</option>
                                @if (isset($opcionesReporte['turnos_horarios']))
                                    @foreach ($opcionesReporte['turnos_horarios'] as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 6 TARJETAS KPI DE LOGÍSTICA -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400">Total Despachos</span>
                        <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($datosReporte['kpis']['total_despachos']) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Envíos programados</div>
                    </div>

                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-emerald-800">Entregados con Éxito</span>
                        <div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($datosReporte['kpis']['total_entregados']) }}</div>
                        <div class="mt-1 text-xs text-emerald-700 font-semibold">Efectividad: {{ $datosReporte['kpis']['tasa_efectividad'] }}%</div>
                    </div>

                    <div class="rounded-2xl border border-sky-200 bg-sky-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-sky-800">En Tránsito / En Ruta</span>
                        <div class="mt-2 text-2xl font-bold text-sky-600">{{ number_format($datosReporte['kpis']['total_en_camino']) }}</div>
                        <div class="mt-1 text-xs text-sky-700 font-medium">Repartos activos</div>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-amber-800">Pendientes</span>
                        <div class="mt-2 text-2xl font-bold text-amber-600">{{ number_format($datosReporte['kpis']['total_pendientes']) }}</div>
                        <div class="mt-1 text-xs text-amber-700 font-medium">Por asignar / despachar</div>
                    </div>

                    <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-rose-800">Cancelaciones</span>
                        <div class="mt-2 text-2xl font-bold text-rose-600">{{ number_format($datosReporte['kpis']['total_cancelados']) }}</div>
                        <div class="mt-1 text-xs text-rose-700 font-semibold">Tasa: {{ $datosReporte['kpis']['tasa_cancelacion'] }}%</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <span class="text-[11px] font-semibold text-slate-400">Total Entregado</span>
                        <div class="mt-2 text-2xl font-bold text-brand-orange">Bs. {{ number_format($datosReporte['kpis']['monto_total_despachado'], 2) }}</div>
                        <div class="mt-1 text-xs text-slate-500 font-medium">Valor mercadería cerrada</div>
                    </div>
                </div>

                <!-- CRONOLOGÍA DE DESPACHOS Y DESGLOSE POR TURNOS -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Serie Cronológica Diaria -->
                    <div class="lg:col-span-2 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800">Despachos por día</h3>
                            </div>
                            <span class="text-xs font-semibold text-slate-400">Últimos días</span>
                        </div>

                        @if (count($datosReporte['serie_cronologica']) > 0)
                            @php
                                $maxDespachos = max(collect($datosReporte['serie_cronologica'])->max('total') ?? 1, 1);
                            @endphp
                            <div class="space-y-3 pt-2">
                                @foreach ($datosReporte['serie_cronologica'] as $dia)
                                    @php
                                        $pctTotal = min(round(($dia['total'] / $maxDespachos) * 100), 100);
                                        $pctEntregados = $dia['total'] > 0 ? min(round(($dia['entregados'] / $dia['total']) * 100), 100) : 0;
                                    @endphp
                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="font-semibold text-slate-700">{{ $dia['etiqueta'] }} <span class="text-[10px] text-slate-400 font-normal">({{ $dia['fecha'] }})</span></span>
                                            <div class="flex items-center gap-3">
                                                <span class="text-slate-600 font-medium">{{ $dia['total'] }} despachos</span>
                                                <span class="font-semibold text-emerald-600">{{ $dia['entregados'] }} entregados</span>
                                                @if ($dia['cancelados'] > 0)
                                                    <span class="font-semibold text-rose-500">({{ $dia['cancelados'] }} canc.)</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="h-3 w-full rounded-full bg-slate-100 overflow-hidden flex">
                                            @if ($dia['total'] > 0)
                                                <div class="bg-brand-blue rounded-l-full" style="width: {{ $pctTotal }}%" title="Total: {{ $dia['total'] }}">
                                                    <div class="h-full bg-emerald-500" style="width: {{ $pctEntregados }}%" title="Entregados: {{ $dia['entregados'] }}"></div>
                                                </div>
                                            @else
                                                <div class="h-full w-full bg-slate-100"></div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-end gap-4 mt-4 pt-3 border-t border-slate-100 text-xs text-slate-500">
                                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-brand-blue"></span> Despachos Asignados</span>
                                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> Entregas Exitosas</span>
                            </div>
                        @else
                            <div class="py-12 text-center text-sm text-slate-400">Sin despachos en este rango</div>
                        @endif
                    </div>

                    <!-- Turnos de 90 Minutos (12:00 a 18:00) -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Por turno</h3>
                        </div>

                        @php
                            $totalTurnos = max(collect($datosReporte['despachos_por_turno'])->sum('conteo'), 1);
                        @endphp

                        <div class="space-y-3 pt-1">
                            @foreach ($datosReporte['despachos_por_turno'] as $turnoKey => $tInfo)
                                @php
                                    $pctTurno = round(($tInfo['conteo'] / $totalTurnos) * 100, 1);
                                @endphp
                                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                    <div class="flex justify-between items-center text-xs mb-1">
                                        <span class="font-semibold text-slate-800">{{ $tInfo['etiqueta'] }}</span>
                                        <span class="font-bold text-brand-blue">{{ $tInfo['conteo'] }} <span class="font-normal text-slate-400">({{ $pctTurno }}%)</span></span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-slate-200 overflow-hidden">
                                        <div class="h-full bg-brand-blue rounded-full" style="width: {{ $pctTurno }}%"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px] text-slate-500 mt-1.5 pt-1 border-t border-slate-100">
                                        <span class="text-emerald-700 font-medium"><x-lucide-check /> {{ $tInfo['entregados'] }} exitosos</span>
                                        @if ($tInfo['cancelados'] > 0)
                                            <span class="text-rose-600 font-semibold"><x-lucide-x /> {{ $tInfo['cancelados'] }} cancelados</span>
                                        @else
                                            <span class="text-slate-400">0 incidencias</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- DISTRIBUCIÓN TERRITORIAL Y RENDIMIENTO DE CHOFERES -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Zonas de La Paz -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800">Por zona</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Concentración geográfica de clientes</p>
                            </div>
                            <span class="text-xs font-semibold text-brand-orange">{{ count($datosReporte['despachos_por_zona']) }} Zonas</span>
                        </div>

                        <div class="space-y-3">
                            @forelse ($datosReporte['despachos_por_zona'] as $zd)
                                <div>
                                    <div class="flex justify-between text-xs mb-1 font-semibold">
                                        <span class="text-slate-800">{{ $zd['zona'] }}</span>
                                        <span class="text-slate-900 font-semibold">{{ $zd['total'] }} despachos <span class="text-slate-400 font-normal">({{ $zd['porcentaje'] }}%)</span></span>
                                    </div>
                                    <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                        <div class="h-full bg-brand-orange rounded-full" style="width: {{ $zd['porcentaje'] }}%"></div>
                                    </div>
                                    <div class="flex justify-between text-[10px] text-slate-400 mt-0.5">
                                        <span>Exitosos: {{ $zd['entregados'] }}</span>
                                        @if ($zd['cancelados'] > 0)
                                            <span class="text-rose-500 font-semibold">Cancelados: {{ $zd['cancelados'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 py-6 text-center">Sin zonas en este rango</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Rendimiento por Chofer -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-800">Por repartidor</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Tasa de éxito y volumen completado</p>
                            </div>
                            <span class="text-xs font-semibold text-slate-500">{{ count($datosReporte['despachos_por_chofer']) }} Repartidores</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-700">
                                <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="px-3 py-2">Chofer</th>
                                        <th class="px-3 py-2 text-center">Asignados</th>
                                        <th class="px-3 py-2 text-center">Entregados</th>
                                        <th class="px-3 py-2 text-center">Canc.</th>
                                        <th class="px-3 py-2 text-right">Efectividad</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse ($datosReporte['despachos_por_chofer'] as $chf)
                                        <tr>
                                            <td class="px-3 py-2.5">
                                                <div class="font-semibold text-slate-900">{{ $chf['nombre'] }}</div>
                                            </td>
                                            <td class="px-3 py-2.5 text-center font-semibold text-slate-700">{{ $chf['total'] }}</td>
                                            <td class="px-3 py-2.5 text-center font-semibold text-emerald-600">{{ $chf['entregados'] }}</td>
                                            <td class="px-3 py-2.5 text-center font-semibold {{ $chf['cancelados'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                                                {{ $chf['cancelados'] }}
                                            </td>
                                            <td class="px-3 py-2.5 text-right font-bold">
                                                @if ($chf['tasa_exito'] >= 85)
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">
                                                        {{ $chf['tasa_exito'] }}%
                                                    </span>
                                                @elseif ($chf['tasa_exito'] >= 70)
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                                                        {{ $chf['tasa_exito'] }}%
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-800">
                                                        {{ $chf['tasa_exito'] }}%
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-3 py-6 text-center text-slate-400">Sin choferes con despachos en este periodo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MOTIVOS DE CANCELACIÓN -->
                @if (count($datosReporte['motivos_cancelacion']) > 0)
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/30 p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-rose-900 mb-2 flex items-center gap-2">
                            <span><x-lucide-triangle-alert /></span>
                            <span>Análisis de Incidencias y Cancelaciones</span>
                        </h3>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($datosReporte['motivos_cancelacion'] as $motivo => $conteo)
                                <div class="rounded-xl border border-rose-200 bg-white p-3 shadow-2xs">
                                    <div class="text-xs font-semibold text-slate-800">{{ $motivo }}</div>
                                    <div class="mt-1 flex items-center justify-between text-xs">
                                        <span class="text-rose-600 font-bold text-base">{{ $conteo }} incidentes</span>
                                        <span class="text-[10px] text-slate-400 font-medium">{{ round(($conteo / max($datosReporte['kpis']['total_cancelados'], 1)) * 100, 1) }}% de cancelaciones</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- REGISTRO DETALLADO DE DESPACHOS RECIENTES -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-800">Despachos</h3>
                        </div>
                        <span class="text-xs font-semibold text-slate-400">Mostrando {{ count($datosReporte['entregas_registros']) }} registros</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 text-[10px] font-semibold text-slate-500 border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2.5">Código / Fecha</th>
                                    <th class="px-3 py-2.5">Cliente</th>
                                    <th class="px-3 py-2.5">Chofer</th>
                                    <th class="px-3 py-2.5">Zona / Destino</th>
                                    <th class="px-3 py-2.5 text-center">Turno</th>
                                    <th class="px-3 py-2.5 text-right">Monto</th>
                                    <th class="px-3 py-2.5 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($datosReporte['entregas_registros'] as $entrega)
                                    <tr>
                                        <td class="px-3 py-2.5">
                                            <div class="font-semibold text-brand-ink">{{ $entrega->venta?->numero_venta ?? ('ENT-#'.$entrega->id) }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $entrega->fecha_programada ? \Carbon\Carbon::parse($entrega->fecha_programada)->format('d/m/Y') : '-' }}</div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-semibold text-slate-900">{{ $entrega->venta?->cliente?->name ?? 'Cliente Web' }}</div>
                                            <div class="text-[10px] text-slate-400">{{ $entrega->venta?->cliente?->telefono ?? 'Sin teléfono' }}</div>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <span class="font-medium text-slate-700">{{ $entrega->empleado?->name ?? 'Sin asignar' }}</span>
                                        </td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-semibold text-slate-800">{{ $entrega->direccion?->zona ?? ($entrega->sucursal?->zona ?? 'La Paz') }}</div>
                                            <div class="text-[10px] text-slate-400 truncate max-w-xs">{{ $entrega->direccion?->direccion_completa ?? ($entrega->sucursal?->direccion ?? '-') }}</div>
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                                {{ $entrega->hora_inicio ? substr((string) $entrega->hora_inicio, 0, 5) : '-' }}
                                                @if ($entrega->hora_fin)
                                                    - {{ substr((string) $entrega->hora_fin, 0, 5) }}
                                                @endif
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold text-slate-900">
                                            Bs. {{ number_format((float) ($entrega->venta?->total ?? 0), 2) }}
                                        </td>
                                        <td class="px-3 py-2.5 text-center">
                                            @php
                                                $badgeClass = match ($entrega->estado) {
                                                    'entregado' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    'en_camino' => 'bg-sky-100 text-sky-800 border-sky-200',
                                                    'pendiente' => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    'cancelado' => 'bg-rose-100 text-rose-800 border-rose-200',
                                                    default => 'bg-slate-100 text-slate-800 border-slate-200',
                                                };
                                                [$badgeIcono, $badgeLabel] = match ($entrega->estado) {
                                                    'entregado' => ['check', 'Entregado'],
                                                    'en_camino' => ['truck', 'En Camino'],
                                                    'pendiente' => ['hourglass', 'Pendiente'],
                                                    'cancelado' => ['x', 'Cancelado'],
                                                    default => [null, ucfirst((string) $entrega->estado)],
                                                };
                                            @endphp
                                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $badgeClass }}">
                                                @if ($badgeIcono)
                                                    <x-dynamic-component :component="'lucide-'.$badgeIcono" />
                                                @endif
                                                {{ $badgeLabel }}
                                            </span>
                                            @if ($entrega->estado === 'cancelado' && $entrega->motivo_cancelacion)
                                                <div class="text-[9px] text-rose-600 font-semibold mt-0.5 italic truncate max-w-[140px]" title="{{ $entrega->motivo_cancelacion }}">
                                                    {{ $entrega->motivo_cancelacion }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-3 py-8 text-center text-slate-400">Sin despachos</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        @if ($pestanaActiva !== 'reportes')
            @include('livewire.admin.partials.logistica-detalle-modal')
            @include('livewire.admin.partials.logistica-asignar-modal')
            @include('livewire.admin.partials.logistica-cancelar-modal')
            @include('livewire.admin.partials.logistica-confirmar-modal')
        @endif
    </div>
</x-admin-shell>

