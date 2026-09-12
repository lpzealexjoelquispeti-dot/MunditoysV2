<x-admin-shell active="ventas">
    <style>
        @media print {
            body * {
                visibility: hidden !important;
            }
            #recibo-imprimible, #recibo-imprimible * {
                visibility: visible !important;
            }
            #recibo-imprimible {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 340px !important;
                margin: 0 auto !important;
                padding: 12px !important;
                background: white !important;
            }
        }
    </style>

    {{-- Encabezado del Módulo --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="grid size-10 place-items-center rounded-2xl bg-brand-orange/10 text-xl text-brand-orange">🧾</span>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900">Punto de Venta (POS Mostrador)</h1>
                    <p class="text-xs font-medium text-slate-500">Realiza ventas presenciales, consulta existencias multisede y emite comprobantes al instante.</p>
                </div>
            </div>
        </div>

        {{-- Selector de Pestaña --}}
        <div class="flex items-center rounded-2xl bg-slate-100 p-1 text-sm font-semibold">
            <button type="button" wire:click="cambiarPestana('pos')" @class([
                'flex items-center gap-2 rounded-xl px-4 py-2 transition',
                'bg-white text-brand-blue shadow-sm' => $pestana === 'pos',
                'text-slate-600 hover:text-slate-900' => $pestana !== 'pos',
            ])>
                <span>🛒</span> Mostrador POS
            </button>
            <button type="button" wire:click="cambiarPestana('historial')" @class([
                'flex items-center gap-2 rounded-xl px-4 py-2 transition',
                'bg-white text-brand-blue shadow-sm' => $pestana === 'historial',
                'text-slate-600 hover:text-slate-900' => $pestana !== 'historial',
            ])>
                <span>📋</span> Historial de Ventas
            </button>
        </div>
    </div>

    @if ($pestana === 'pos')
        {{-- Barra de Selección de Sucursal y Almacén --}}
        <div class="mt-5 rounded-2xl border border-blue-100 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <span class="text-lg">🏪</span>
                        <span>Sucursal de Venta:</span>
                        <select wire:model.live="sucursalId" class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm font-bold text-brand-blue focus:border-brand-blue focus:ring-brand-blue">
                            @foreach ($sucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }} ({{ $suc->ciudad }})</option>
                            @endforeach
                        </select>
                    </div>

                    @php
                        $sucursalActual = $sucursales->firstWhere('id', $sucursalId);
                    @endphp
                    @if ($sucursalActual && $sucursalActual->almacenes->isNotEmpty())
                        <div class="flex items-center gap-2 text-sm font-medium text-slate-600">
                            <span class="text-lg">🏬</span>
                            <span>Almacén / Mostrador:</span>
                            <select wire:model.live="almacenId" class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm text-slate-800 focus:border-brand-blue focus:ring-brand-blue">
                                @foreach ($sucursalActual->almacenes as $alm)
                                    <option value="{{ $alm->id }}">{{ $alm->nombre }} ({{ ucfirst($alm->tipo) }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="size-2.5 rounded-full bg-emerald-500"></span>
                    <span>Vendedor activo: <strong class="text-slate-800">{{ auth()->user()->name }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Layout Principal POS (Izquierda: Catálogo y Stock / Derecha: Carrito y Cobro) --}}
        <div class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Columna Izquierda: Búsqueda de Juguetes y Stock (7 cols) --}}
            <div class="space-y-4 lg:col-span-7">
                {{-- Barra de Búsqueda y Filtro de Categoría --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <div class="relative flex-1">
                            <input type="text" wire:model.live.debounce.300ms="buscarJuguete" placeholder="Buscar por nombre, código SKU o escanear QR..." class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2.5 pl-10 pr-4 text-sm text-slate-800 placeholder-slate-400 focus:border-brand-blue focus:bg-white focus:ring-brand-blue" />
                            <span class="pointer-events-none absolute left-3.5 top-3 text-slate-400">🔍</span>
                            @if ($buscarJuguete)
                                <button type="button" wire:click="$set('buscarJuguete', '')" class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-600">✕</button>
                            @endif
                        </div>

                        <select wire:model.live="categoriaFiltro" class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-brand-blue focus:ring-brand-blue">
                            <option value="">Todas las categorías</option>
                            @foreach ($categorias as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Catálogo de Juguetes --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @forelse ($juguetes as $juguete)
                        <div wire:key="pos-toy-{{ $juguete['id'] }}" class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:shadow-md">
                            <div>
                                {{-- Imagen y Badges --}}
                                <div class="relative mb-3 flex aspect-video w-full items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                    @if ($juguete['imagen_url'])
                                        <img src="{{ $juguete['imagen_url'] }}" alt="{{ $juguete['nombre'] }}" class="h-full w-full object-cover" />
                                    @else
                                        <span class="text-4xl text-slate-300">🧸</span>
                                    @endif

                                    {{-- Badge Gemelo 3D --}}
                                    @if ($juguete['tiene_modelado_3d'])
                                        <span class="absolute left-2 top-2 flex items-center gap-1 rounded-lg bg-cyan-600/90 px-2 py-0.5 text-[11px] font-bold text-white shadow-sm backdrop-blur-sm">
                                            <span>🧊</span> 3D Twin
                                        </span>
                                    @endif

                                    {{-- Badge Stock Sucursal Actual --}}
                                    <div class="absolute bottom-2 right-2">
                                        @if ($juguete['stock_sucursal_actual'] > 5)
                                            <span class="rounded-lg bg-emerald-600 px-2 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                                ✓ {{ $juguete['stock_sucursal_actual'] }} disp.
                                            </span>
                                        @elseif ($juguete['stock_sucursal_actual'] > 0)
                                            <span class="rounded-lg bg-amber-500 px-2 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                                ⚠️ {{ $juguete['stock_sucursal_actual'] }} disp. (Bajo)
                                            </span>
                                        @else
                                            <span class="rounded-lg bg-rose-600 px-2 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                                ✕ Agotado aquí
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Datos del Juguete --}}
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $juguete['categoria'] }}</span>
                                        <h3 class="font-bold text-slate-900 line-clamp-1" title="{{ $juguete['nombre'] }}">{{ $juguete['nombre'] }}</h3>
                                    </div>
                                </div>
                                <div class="mt-1 flex items-center gap-2 text-xs text-slate-500">
                                    <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded text-[11px]">{{ $juguete['codigo_producto'] }}</span>
                                    <span>•</span>
                                    <span>{{ $juguete['marca'] }}</span>
                                </div>

                                {{-- Desglose en Otras Sucursales MundiToys (Acordeón / Detalle) --}}
                                <div x-data="{ abierto: false }" class="mt-3 rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs">
                                    <button type="button" @click="abierto = !abierto" class="flex w-full items-center justify-between text-left font-semibold text-slate-600 hover:text-brand-blue">
                                        <span class="flex items-center gap-1.5">
                                            <span>🏢</span> Existencia en otras sucursales
                                        </span>
                                        <span x-text="abierto ? '▲' : '▼'" class="text-[10px]">▼</span>
                                    </button>
                                    <div x-show="abierto" x-cloak class="mt-2 space-y-1 border-t border-slate-200/60 pt-2">
                                        @forelse ($juguete['desglose_otras_sucursales'] as $otra)
                                            <div class="flex items-center justify-between text-slate-600">
                                                <span class="truncate pr-2">{{ $otra['sucursal_nombre'] }}</span>
                                                <span @class([
                                                    'font-bold',
                                                    'text-emerald-700' => $otra['stock'] > 0,
                                                    'text-slate-400' => $otra['stock'] === 0,
                                                ])>
                                                    {{ $otra['stock'] > 0 ? $otra['stock'].' disp.' : '0 disp.' }}
                                                </span>
                                            </div>
                                        @empty
                                            <p class="text-slate-400">No hay otras sucursales registradas.</p>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            {{-- Pie de Tarjeta: Precio y Botón de Añadir --}}
                            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                <div>
                                    <span class="text-[10px] uppercase tracking-wider text-slate-400">Precio</span>
                                    <div class="text-lg font-black text-brand-ink">
                                        Bs. {{ number_format($juguete['precio_venta'], 2) }}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="agregarAlCarrito({{ $juguete['id'] }})"
                                    @disabled($juguete['stock_sucursal_actual'] <= 0)
                                    @class([
                                        'flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition',
                                        'bg-brand-blue text-white shadow-sm hover:bg-blue-700 hover:shadow active:scale-95' => $juguete['stock_sucursal_actual'] > 0,
                                        'bg-slate-200 text-slate-400 cursor-not-allowed' => $juguete['stock_sucursal_actual'] <= 0,
                                    ])
                                >
                                    <span>🛒</span> Añadir
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full rounded-2xl border-2 border-dashed border-slate-200 p-8 text-center">
                            <span class="text-4xl">🧸</span>
                            <h3 class="mt-2 text-base font-bold text-slate-700">No se encontraron juguetes</h3>
                            <p class="mt-1 text-xs text-slate-500">Prueba ajustando el término de búsqueda o cambiando el filtro de categoría.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Columna Derecha: Gestión de Cliente, Carrito y Cobro (5 cols) --}}
            <div class="space-y-4 lg:col-span-5">
                {{-- Sección 1: Cliente --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">1. Datos del Cliente</h2>
                        @if ($clienteSeleccionado)
                            <button type="button" wire:click="deseleccionarCliente" class="text-xs font-semibold text-rose-600 hover:underline">
                                ✕ Cambiar a mostrador
                            </button>
                        @endif
                    </div>

                    {{-- Botones de Modo Cliente --}}
                    <div class="mt-3 grid grid-cols-3 gap-1.5 rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                        <button type="button" wire:click="$set('modoCliente', 'mostrador')" @class([
                            'rounded-lg py-1.5 transition text-center',
                            'bg-white text-brand-blue shadow-sm font-bold' => $modoCliente === 'mostrador',
                            'text-slate-600 hover:text-slate-900' => $modoCliente !== 'mostrador',
                        ])>
                            Mostrador / CF
                        </button>
                        <button type="button" wire:click="$set('modoCliente', 'buscar')" @class([
                            'rounded-lg py-1.5 transition text-center',
                            'bg-white text-brand-blue shadow-sm font-bold' => $modoCliente === 'buscar',
                            'text-slate-600 hover:text-slate-900' => $modoCliente !== 'buscar',
                        ])>
                            Buscar Cliente
                        </button>
                        <button type="button" wire:click="$set('modoCliente', 'nuevo')" @class([
                            'rounded-lg py-1.5 transition text-center',
                            'bg-white text-brand-blue shadow-sm font-bold' => $modoCliente === 'nuevo',
                            'text-slate-600 hover:text-slate-900' => $modoCliente !== 'nuevo',
                        ])>
                            + Nuevo Express
                        </button>
                    </div>

                    {{-- Contenido según modo --}}
                    <div class="mt-3">
                        @if ($modoCliente === 'mostrador')
                            <div class="flex items-center gap-3 rounded-xl border border-emerald-100 bg-emerald-50/70 p-3">
                                <span class="text-2xl">👤</span>
                                <div>
                                    <p class="text-xs font-bold text-emerald-900">Venta rápida a Mostrador (Consumidor Final)</p>
                                    <p class="text-[11px] text-emerald-700">NIT / CI: 0 • Sin registro previo</p>
                                </div>
                            </div>
                        @elseif ($modoCliente === 'buscar')
                            @if ($clienteSeleccionado)
                                <div class="flex items-center justify-between rounded-xl border border-blue-200 bg-blue-50/60 p-3">
                                    <div class="flex items-center gap-3">
                                        <span class="text-2xl">👤</span>
                                        <div>
                                            <p class="text-xs font-bold text-slate-900">{{ $clienteSeleccionado['nombre_completo'] }}</p>
                                            <p class="text-[11px] text-slate-600">CI/NIT: {{ $clienteSeleccionado['ci_nit'] }} • Cel: {{ $clienteSeleccionado['telefono'] }}</p>
                                        </div>
                                    </div>
                                    <button type="button" wire:click="deseleccionarCliente" class="rounded-lg bg-white p-1 text-slate-400 hover:text-rose-600 shadow-sm" title="Quitar cliente">
                                        ✕
                                    </button>
                                </div>
                            @else
                                <div class="space-y-2">
                                    <div class="relative">
                                        <input type="text" wire:model.live.debounce.250ms="buscarClienteTermino" placeholder="Buscar por CI, NIT, celular o nombre..." class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pl-8 pr-3 text-xs text-slate-800 placeholder-slate-400 focus:border-brand-blue focus:bg-white" />
                                        <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
                                    </div>

                                    @if (! empty($clientesBusqueda))
                                        <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg divide-y divide-slate-100">
                                            @foreach ($clientesBusqueda as $cli)
                                                <button type="button" wire:click="seleccionarCliente({{ $cli['id'] }})" class="flex w-full items-center justify-between p-2.5 text-left text-xs transition hover:bg-blue-50">
                                                    <div>
                                                        <span class="font-bold text-slate-900">{{ $cli['nombre_completo'] }}</span>
                                                        <div class="text-[11px] text-slate-500">CI/NIT: {{ $cli['ci_nit'] }} • Cel: {{ $cli['telefono'] }}</div>
                                                    </div>
                                                    <span class="font-semibold text-brand-blue">Seleccionar →</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @elseif (strlen(trim($buscarClienteTermino)) > 1)
                                        <p class="text-[11px] text-slate-500 text-center py-2">No se encontraron clientes con ese criterio. Puedes registrarlo como nuevo express.</p>
                                    @endif
                                </div>
                            @endif
                        @elseif ($modoCliente === 'nuevo')
                            {{-- Formulario Registro Express con Validación en Español --}}
                            <form wire:submit="registrarClienteExpress" class="space-y-2.5 rounded-xl border border-blue-100 bg-blue-50/30 p-3">
                                <div class="flex items-center justify-between border-b border-blue-100 pb-1.5">
                                    <span class="text-xs font-bold text-brand-blue">Registro Express de Nuevo Cliente</span>
                                    <span class="text-[10px] text-slate-500">Se guardará como Cliente</span>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700">Nombres <span class="text-rose-500">*</span></label>
                                    <input type="text" wire:model="nuevo_nombre" placeholder="Ej: Marcela" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                    @error('nuevo_nombre') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">Apellido Paterno</label>
                                        <input type="text" wire:model="nuevo_apellido_paterno" placeholder="Ej: Condori" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                        @error('nuevo_apellido_paterno') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">Apellido Materno</label>
                                        <input type="text" wire:model="nuevo_apellido_materno" placeholder="Ej: Rojas" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                        @error('nuevo_apellido_materno') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">CI / NIT <span class="text-rose-500">*</span></label>
                                        <input type="text" wire:model="nuevo_ci_nit" placeholder="Ej: 6842104" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                        @error('nuevo_ci_nit') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">Celular / Teléfono <span class="text-rose-500">*</span></label>
                                        <input type="text" wire:model="nuevo_telefono" placeholder="Ej: 71234567" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                        @error('nuevo_telefono') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-semibold text-slate-700">Correo Electrónico <span class="text-slate-400 font-normal">(Opcional)</span></label>
                                    <input type="email" wire:model="nuevo_email" placeholder="cliente@ejemplo.com" class="mt-0.5 w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-brand-blue focus:ring-brand-blue" />
                                    @error('nuevo_email') <span class="text-[10px] text-rose-600 font-medium">{{ $message }}</span> @enderror
                                </div>

                                <div class="flex justify-end gap-2 pt-1">
                                    <button type="button" wire:click="$set('modoCliente', 'mostrador')" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="rounded-lg bg-brand-blue px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                                        💾 Guardar y Asignar
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Sección 2: Carrito POS --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">2. Carrito de Venta</h2>
                            <span class="rounded-full bg-brand-blue px-2 py-0.5 text-xs font-bold text-white">{{ count($carrito) }}</span>
                        </div>
                        @if (! empty($carrito))
                            <button type="button" wire:click="limpiarCarrito" class="text-xs font-semibold text-rose-600 hover:underline">
                                Vaciar carrito
                            </button>
                        @endif
                    </div>

                    {{-- Lista de Ítems --}}
                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto divide-y divide-slate-100">
                        @forelse ($carrito as $id => $item)
                            <div wire:key="cart-item-{{ $id }}" class="flex items-center justify-between gap-2 pt-2 first:pt-0">
                                <div class="min-w-0 flex-1">
                                    <h4 class="truncate text-xs font-bold text-slate-800" title="{{ $item['nombre'] }}">{{ $item['nombre'] }}</h4>
                                    <p class="text-[11px] text-slate-500">Bs. {{ number_format($item['precio_unitario'], 2) }} c/u</p>
                                </div>

                                {{-- Controles de Cantidad --}}
                                <div class="flex items-center gap-1.5">
                                    <button type="button" wire:click="decrementarCantidad({{ $id }})" class="grid size-6 place-items-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600 hover:bg-slate-200">
                                        -
                                    </button>
                                    <span class="w-6 text-center text-xs font-bold text-slate-900">{{ $item['cantidad'] }}</span>
                                    <button type="button" wire:click="incrementarCantidad({{ $id }})" class="grid size-6 place-items-center rounded-lg bg-slate-100 text-xs font-bold text-slate-600 hover:bg-slate-200">
                                        +
                                    </button>
                                </div>

                                {{-- Subtotal y Eliminar --}}
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-900">Bs. {{ number_format($item['cantidad'] * $item['precio_unitario'], 2) }}</p>
                                    <button type="button" wire:click="eliminarDelCarrito({{ $id }})" class="text-[10px] text-rose-500 hover:underline">
                                        Quitar
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center">
                                <span class="text-3xl text-slate-300">🛒</span>
                                <p class="mt-2 text-xs font-medium text-slate-500">El carrito está vacío.</p>
                                <p class="text-[11px] text-slate-400">Haz clic en "+ Añadir" en cualquier juguete del catálogo.</p>
                            </div>
                        @endforelse
                    </div>

                    {{-- Totales --}}
                    @if (! empty($carrito))
                        <div class="mt-4 border-t border-slate-200 pt-3 space-y-1">
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>Subtotal</span>
                                <span>Bs. {{ number_format($this->totalCarrito, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-xs text-slate-500">
                                <span>Descuento</span>
                                <span>Bs. 0.00</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                                <span class="text-sm font-bold text-slate-900">TOTAL A COBRAR</span>
                                <span class="text-xl font-black text-brand-orange">Bs. {{ number_format($this->totalCarrito, 2) }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Sección 3: Métodos de Pago y Cobro --}}
                @if (! empty($carrito))
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-700">3. Método de Pago</h2>

                        {{-- Selector de Métodos --}}
                        <div class="grid grid-cols-4 gap-1.5 rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                            <button type="button" wire:click="$set('metodoPago', 'efectivo')" @class([
                                'rounded-lg py-1.5 transition text-center',
                                'bg-white text-brand-blue shadow-sm font-bold' => $metodoPago === 'efectivo',
                                'text-slate-600 hover:text-slate-900' => $metodoPago !== 'efectivo',
                            ])>
                                💵 Efectivo
                            </button>
                            <button type="button" wire:click="$set('metodoPago', 'qr')" @class([
                                'rounded-lg py-1.5 transition text-center',
                                'bg-white text-brand-blue shadow-sm font-bold' => $metodoPago === 'qr',
                                'text-slate-600 hover:text-slate-900' => $metodoPago !== 'qr',
                            ])>
                                📱 QR Simple
                            </button>
                            <button type="button" wire:click="$set('metodoPago', 'tarjeta')" @class([
                                'rounded-lg py-1.5 transition text-center',
                                'bg-white text-brand-blue shadow-sm font-bold' => $metodoPago === 'tarjeta',
                                'text-slate-600 hover:text-slate-900' => $metodoPago !== 'tarjeta',
                            ])>
                                💳 Tarjeta POS
                            </button>
                            <button type="button" wire:click="$set('metodoPago', 'stripe')" @class([
                                'rounded-lg py-1.5 transition text-center',
                                'bg-white text-brand-blue shadow-sm font-bold' => $metodoPago === 'stripe',
                                'text-slate-600 hover:text-slate-900' => $metodoPago !== 'stripe',
                            ])>
                                🌐 Stripe
                            </button>
                        </div>

                        {{-- Opciones del Método Seleccionado --}}
                        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3">
                            @if ($metodoPago === 'efectivo')
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <label class="block text-xs font-semibold text-slate-700">Monto Recibido del Cliente (Bs.)</label>
                                            <button type="button" wire:click="establecerEfectivoExacto" class="text-[11px] font-bold text-brand-blue hover:underline">
                                                Cobrar Exacto (Bs. {{ number_format($this->totalCarrito, 2) }})
                                            </button>
                                        </div>
                                        <div class="mt-1 flex items-center gap-2">
                                            <input type="number" step="0.5" min="0" wire:model.live.debounce.150ms="montoRecibido" placeholder="0.00" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-base font-bold text-slate-900 focus:border-brand-blue focus:ring-brand-blue" />
                                        </div>
                                        @error('montoRecibido') <span class="text-xs text-rose-600 font-medium">{{ $message }}</span> @enderror
                                    </div>

                                    {{-- Accesos Rápidos de Billetes en Bolivia --}}
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ([20, 50, 100, 200] as $billete)
                                            <button type="button" wire:click="establecerEfectivo({{ $billete }})" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:border-brand-blue hover:bg-blue-50">
                                                Bs. {{ $billete }}
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- Cálculo de Cambio / Vuelto --}}
                                    <div class="flex items-center justify-between rounded-xl bg-emerald-500/10 p-3 text-emerald-900 border border-emerald-500/20">
                                        <span class="text-xs font-bold">Cambio / Vuelto a entregar:</span>
                                        <span class="text-lg font-black text-emerald-700">Bs. {{ number_format($this->cambio, 2) }}</span>
                                    </div>
                                </div>
                            @elseif ($metodoPago === 'qr')
                                <div class="space-y-3 text-center">
                                    <div class="mx-auto flex size-36 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-indigo-300 bg-white p-2 shadow-sm">
                                        {{-- Mock QR Visual con SVG estilizado --}}
                                        <svg viewBox="0 0 100 100" class="size-28 text-slate-800" fill="currentColor">
                                            <path d="M10 10h30v30H10V10zm5 5v20h20V15H15zm45-5h30v30H60V10zm5 5v20h20V15H65zM10 60h30v30H10V60zm5 5v20h20V65H15zm45 0h10v10H60V65zm20 0h10v10H80V65zm-20 20h10v10H60V85zm20 20h10v10H80V85zm-10-10h10v10H70V75zm-25-30h10v10H45V45zm0 20h10v10H45V65zm10-10h10v10H55V55zm-20-10h10v10H35V45zm20-20h10v10H55V25zm-20 0h10v10H35V25z"/>
                                        </svg>
                                        <span class="text-[10px] font-bold text-indigo-700">QR Simple BCB</span>
                                    </div>

                                    <p class="text-xs font-medium text-slate-600">
                                        Monto: <strong class="text-slate-900">Bs. {{ number_format($this->totalCarrito, 2) }}</strong>
                                    </p>

                                    <div>
                                        <label class="block text-left text-[11px] font-semibold text-slate-700">Nº de Operación / Referencia Bancaria</label>
                                        <input type="text" wire:model="referenciaQr" placeholder="Ej: QR-BCB-984210" class="mt-0.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900" />
                                    </div>

                                    <div class="flex items-center justify-between gap-2 pt-1">
                                        <button type="button" wire:click="simularAcreditacionQr" class="flex-1 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700">
                                            ⚡ Simular Acreditación Exitosa
                                        </button>
                                        @if ($qrAcreditado)
                                            <span class="rounded-lg bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">
                                                ✓ Acreditado
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @elseif ($metodoPago === 'tarjeta')
                                <div class="space-y-3">
                                    <p class="text-xs text-slate-600">Deslice o aproxime la tarjeta en el terminal físico POS de la sucursal.</p>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">Nº de Autorización / Voucher POS</label>
                                        <input type="text" wire:model="referenciaTarjeta" placeholder="Ej: POS-VISA-48291" class="mt-0.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-900" />
                                    </div>
                                    <button type="button" wire:click="simularCobroTarjeta" class="w-full rounded-xl bg-slate-800 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-slate-900">
                                        ⚡ Simular Voucher POS Aprobado
                                    </button>
                                </div>
                            @elseif ($metodoPago === 'stripe')
                                <div class="space-y-3">
                                    <div class="flex items-center gap-2 text-xs text-slate-600">
                                        <span class="text-lg">💳</span>
                                        <span>Pasarela de pagos con tarjeta en línea / Stripe Webhook.</span>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700">Stripe Payment Intent ID</label>
                                        <input type="text" wire:model="stripePaymentId" placeholder="pi_xxxxxxxxxxxx" class="mt-0.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-mono text-slate-900" />
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <button type="button" wire:click="simularStripe" class="flex-1 rounded-xl bg-violet-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-violet-700">
                                            ⚡ Simular Cobro Stripe Aprobado
                                        </button>
                                        @if ($stripeAprobado)
                                            <span class="rounded-lg bg-emerald-100 px-2 py-1 text-xs font-bold text-emerald-800">
                                                ✓ Pagado
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Botón Principal de Completar Venta --}}
                        <button
                            type="button"
                            wire:click="procesarVenta"
                            wire:loading.attr="disabled"
                            class="w-full rounded-2xl bg-brand-orange py-3.5 text-center text-sm font-black text-white shadow-md transition hover:bg-orange-600 hover:shadow-lg active:scale-98 disabled:opacity-50"
                        >
                            <span wire:loading.remove>
                                🧾 COMPLETAR VENTA Y EMITIR RECIBO (Bs. {{ number_format($this->totalCarrito, 2) }})
                            </span>
                            <span wire:loading>
                                Procesando inventario FIFO...
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- Pestaña 2: Historial de Ventas --}}
        <div class="mt-5 space-y-4">
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="relative flex-1 max-w-md">
                    <input type="text" wire:model.live.debounce.300ms="buscarHistorial" placeholder="Buscar por número de venta o cliente..." class="w-full rounded-xl border border-slate-300 bg-slate-50 py-2 pl-9 pr-3 text-sm focus:border-brand-blue focus:bg-white" />
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    Historial de ventas registradas en el sistema.
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase font-bold text-slate-600">
                        <tr>
                            <th class="px-4 py-3">Nº Venta</th>
                            <th class="px-4 py-3">Fecha y Hora</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Vendedor</th>
                            <th class="px-4 py-3">Método Pago</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3 text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @if ($ventasHistorial)
                            @forelse ($ventasHistorial as $v)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-mono font-bold text-brand-blue">{{ $v->numero_venta }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-xs">{{ $v->fecha_venta?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ $v->cliente?->name ?? 'Consumidor Final' }}</div>
                                        @if ($v->cliente?->ci_nit)
                                            <div class="text-[11px] text-slate-400">CI/NIT: {{ $v->cliente->ci_nit }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 text-xs">{{ $v->vendedor?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-xs capitalize text-slate-600">
                                        <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-medium">
                                            {{ $v->pago?->metodo_pago ?? 'efectivo' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-slate-900">
                                        Bs. {{ number_format((float) $v->total, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                            {{ ucfirst($v->estado) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                        No se encontraron ventas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>

            @if ($ventasHistorial)
                <div>{{ $ventasHistorial->links() }}</div>
            @endif
        </div>
    @endif

    {{-- MODAL DE RECIBO / TICKET DE VENTA IMPRIMIBLE --}}
    @if ($mostrarModalRecibo && $reciboActual)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm">
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
                {{-- Botón Cerrar --}}
                <button type="button" wire:click="cerrarModalRecibo" class="absolute right-4 top-4 grid size-8 place-items-center rounded-full bg-slate-100 text-slate-400 hover:bg-slate-200 hover:text-slate-700">
                    ✕
                </button>

                {{-- Contenido Imprimible (Ticket de Venta) --}}
                <div id="recibo-imprimible" class="space-y-4 font-sans text-xs text-slate-800">
                    {{-- Encabezado del Recibo --}}
                    <div class="text-center border-b border-dashed border-slate-300 pb-4">
                        <div class="flex items-center justify-center gap-1.5 text-lg font-black text-brand-blue">
                            <span class="text-brand-orange">★</span> MundiToys S.R.L.
                        </div>
                        <p class="text-[11px] font-semibold text-slate-600">Juguetería Mágica y Modelado 3D</p>
                        <p class="mt-1 text-[11px] text-slate-500">{{ $reciboActual['sucursal_nombre'] }} • {{ $reciboActual['sucursal_ciudad'] }}</p>
                        <p class="text-[10px] text-slate-400">NIT: 1028391024 • Tel: {{ $reciboActual['sucursal_telefono'] }}</p>
                    </div>

                    {{-- Metadatos de la Venta --}}
                    <div class="space-y-1 text-[11px]">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Nº Comprobante:</span>
                            <span class="font-mono font-bold text-slate-900">{{ $reciboActual['numero_venta'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Fecha y Hora:</span>
                            <span class="text-slate-700">{{ $reciboActual['fecha_hora'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Atendido por:</span>
                            <span class="text-slate-700">{{ $reciboActual['vendedor_nombre'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Cliente:</span>
                            <span class="font-semibold text-slate-900">{{ $reciboActual['cliente_nombre'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">CI / NIT:</span>
                            <span class="text-slate-700">{{ $reciboActual['cliente_ci_nit'] }}</span>
                        </div>
                    </div>

                    {{-- Tabla de Productos Despachados --}}
                    <div class="border-t border-b border-dashed border-slate-300 py-3">
                        <table class="w-full text-left text-[11px]">
                            <thead>
                                <tr class="text-slate-400 uppercase text-[10px] border-b border-slate-100">
                                    <th class="pb-1">Cant.</th>
                                    <th class="pb-1">Producto</th>
                                    <th class="pb-1 text-right">P.U.</th>
                                    <th class="pb-1 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reciboActual['items'] as $item)
                                    <tr>
                                        <td class="py-1.5 font-bold">{{ $item['cantidad'] }}</td>
                                        <td class="py-1.5">
                                            <div class="font-medium text-slate-800 line-clamp-1">{{ $item['producto'] }}</div>
                                            <div class="text-[9px] text-slate-400 font-mono">{{ $item['codigo'] }}</div>
                                        </td>
                                        <td class="py-1.5 text-right">{{ number_format($item['precio_unitario'], 2) }}</td>
                                        <td class="py-1.5 text-right font-semibold">{{ number_format($item['subtotal'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Resumen Económico --}}
                    <div class="space-y-1 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Subtotal:</span>
                            <span>Bs. {{ number_format($reciboActual['subtotal'], 2) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Descuento:</span>
                            <span>Bs. 0.00</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-slate-900 border-t border-slate-200 pt-1.5">
                            <span>TOTAL:</span>
                            <span class="text-brand-orange">Bs. {{ number_format($reciboActual['total'], 2) }}</span>
                        </div>

                        {{-- Información de Pago --}}
                        <div class="mt-2 rounded-xl bg-slate-50 p-2.5 text-[11px] space-y-1">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Forma de Pago:</span>
                                <span class="font-bold uppercase text-slate-800">{{ $reciboActual['metodo_pago'] }}</span>
                            </div>
                            @if ($reciboActual['metodo_pago'] === 'efectivo')
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Efectivo Recibido:</span>
                                    <span>Bs. {{ number_format($reciboActual['monto_recibido'], 2) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-emerald-700">
                                    <span>Cambio / Vuelto:</span>
                                    <span>Bs. {{ number_format($reciboActual['cambio'], 2) }}</span>
                                </div>
                            @else
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Referencia:</span>
                                    <span class="font-mono text-slate-800">{{ $reciboActual['referencia_pago'] }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Pie de Recibo --}}
                    <div class="border-t border-dashed border-slate-300 pt-3 text-center text-[10px] text-slate-500 space-y-1">
                        <p class="font-bold text-slate-700">¡Gracias por tu compra en MundiToys!</p>
                        <p>Sonrisas e imaginación para los más pequeños.</p>
                        <p class="text-[9px] text-slate-400">Conserve este ticket para cualquier consulta o reclamo.</p>
                    </div>
                </div>

                {{-- Botones de Acción --}}
                <div class="mt-6 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
                    <button type="button" onclick="window.print()" class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50">
                        <span>🖨️</span> Imprimir Ticket
                    </button>
                    <button type="button" wire:click="iniciarNuevaVenta" class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-brand-blue py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700">
                        <span>✨</span> Nueva Venta
                    </button>
                </div>
            </div>
        </div>
    @endif
</x-admin-shell>
