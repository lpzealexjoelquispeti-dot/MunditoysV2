<x-admin-modal property="mostrarIngreso" close="cerrarIngreso" title="Registrar ingreso por lote" :wide="true">
    <form wire:submit="guardar">
        <ol class="mb-5 grid grid-cols-2 gap-2 text-sm font-semibold"><li @class(['rounded-xl px-4 py-3', 'bg-brand-blue text-white' => $paso === 1, 'bg-blue-50 text-brand-blue' => $paso !== 1])>1 · Producto y ubicación</li><li @class(['rounded-xl px-4 py-3', 'bg-brand-blue text-white' => $paso === 2, 'bg-blue-50 text-brand-blue' => $paso !== 2])>2 · Lote y precio de venta</li></ol>
        @if ($paso === 1)
            <div class="grid gap-6 lg:grid-cols-2">
                <section>
                    <x-admin-field label="Buscar juguete *" name="productoId" hint="Selecciona un resultado. Se muestran hasta 8 productos activos; escribe para acotar."><input wire:model.live.debounce.350ms="buscarProducto" type="search" maxlength="100" placeholder="Nombre, código interno o QR" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                    @error('buscarProducto')<p class="mt-1 text-sm text-brand-red">{{ $message }}</p>@enderror
                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto">
                        @forelse ($productosDisponibles as $producto)
                            <button type="button" wire:click="seleccionarProducto({{ $producto['id'] }})" wire:key="selector-producto-{{ $producto['id'] }}" @class(['flex w-full items-center gap-3 rounded-xl border p-3 text-left', 'border-brand-blue bg-blue-50' => $productoId === (string) $producto['id'], 'border-slate-200 hover:bg-orange-50' => $productoId !== (string) $producto['id']])>
                                @if ($producto['imagenes'])<img src="{{ $producto['imagenes'][0] }}" alt="Imagen de {{ $producto['nombre'] }}" class="size-14 rounded-lg bg-white object-contain">@else<span class="grid size-14 place-items-center rounded-lg bg-orange-50 text-3xl"><x-lucide-toy-brick class="text-slate-300" /></span>@endif
                                <span><strong class="block text-sm text-brand-ink">{{ $producto['nombre'] }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $producto['codigo'] }} · {{ $producto['marca'] }}</span></span>
                            </button>
                        @empty<p class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">No encontramos productos activos. Registra el juguete en Productos o cambia la búsqueda.</p>@endforelse
                    </div>
                </section>
                <section>
                    @if ($productoSeleccionado)
                        <div class="rounded-2xl border border-blue-100 bg-blue-50/40 p-4" x-data="{ actual: 0 }" wire:key="preview-producto-{{ $productoSeleccionado['id'] }}">
                            @foreach ($productoSeleccionado['imagenes'] as $indice => $url)<img x-show="actual === {{ $indice }}" src="{{ $url }}" alt="Vista {{ $indice + 1 }} de {{ $productoSeleccionado['nombre'] }}" class="h-40 w-full rounded-xl bg-white object-contain" wire:key="preview-{{ $productoSeleccionado['id'] }}-{{ $indice }}">@endforeach
                            @if (count($productoSeleccionado['imagenes']) > 1)<div class="mt-2 flex justify-center gap-2">@foreach ($productoSeleccionado['imagenes'] as $indice => $url)<button type="button" x-on:click="actual = {{ $indice }}" :class="actual === {{ $indice }} ? 'bg-brand-blue text-white' : 'bg-white text-brand-blue'" class="size-8 rounded-full text-xs font-semibold" aria-label="Ver imagen {{ $indice + 1 }}" wire:key="punto-{{ $indice }}">{{ $indice + 1 }}</button>@endforeach</div>@endif
                            <h3 class="mt-3 font-semibold text-brand-ink">{{ $productoSeleccionado['nombre'] }}</h3><p class="mt-1 text-xs text-slate-500">{{ $productoSeleccionado['categoria'] }} · {{ $productoSeleccionado['edad'] }} años · {{ $productoSeleccionado['dimensiones'] }}</p>
                        </div>
                    @else<div class="grid min-h-40 place-items-center rounded-2xl border-2 border-dashed border-orange-200 p-6 text-center text-sm text-slate-500">Selecciona un juguete para revisar sus imágenes y datos.</div>@endif
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-admin-field label="Sucursal de destino *" name="sucursalId"><select wire:model.live="sucursalId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Selecciona una sucursal</option>@foreach ($sucursales as $sucursal)<option value="{{ $sucursal['id'] }}">{{ $sucursal['nombre'] }}</option>@endforeach</select></x-admin-field>
                        <x-admin-field label="Almacén *" name="almacenId"><select wire:model.live="almacenId" @disabled(!$sucursalId) class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm disabled:bg-slate-100"><option value="">Selecciona un almacén</option>@foreach ($almacenes as $almacen)@if ((string) $almacen['sucursal_id'] === $sucursalId)<option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>@endif@endforeach</select></x-admin-field>
                    </div>
                    @if (!$sucursales || !$almacenes)<p class="mt-3 rounded-xl bg-orange-50 p-3 text-sm text-orange-800">Necesitas una sucursal y un almacén activos para registrar ingresos.</p>@endif
                </section>
            </div>
        @else
            <div class="mb-5 rounded-xl bg-blue-50 p-3 text-sm text-brand-blue"><strong>{{ $productoSeleccionado['nombre'] ?? '' }}</strong> · El código del lote se generará automáticamente. Cada lote conserva sus propios precios.</div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <x-admin-field label="Cantidad de ingreso *" name="cantidad"><input wire:model.blur="cantidad" type="number" min="1" max="1000000" step="1" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-slate-700">Stock mínimo *</label>
                        <button type="button" wire:click="sugerirStockMinimoIO" class="text-xs font-semibold text-brand-blue hover:underline"><x-lucide-calculator /> Calcular con IO</button>
                    </div>
                    <input wire:model.blur="stockMinimo" type="number" min="0" max="1000000" step="1" class="mt-1 min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    @if ($explicacionStockMinimo)
                        <p class="mt-1 text-[11px] text-blue-700 font-medium">{{ $explicacionStockMinimo }}</p>
                    @else
                        <p class="mt-1 text-[11px] text-slate-500">Solo al crear el inventario; si ya existe, se conserva su mínimo.</p>
                    @endif
                    @error('stockMinimo')<p class="mt-1 text-sm text-brand-red">{{ $message }}</p>@enderror
                </div>
                <x-admin-field label="Fecha de ingreso *" name="fechaIngreso"><input wire:model.blur="fechaIngreso" type="date" max="{{ now()->format('Y-m-d') }}" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <x-admin-field label="Costo unitario (Bs) *" name="costoUnitario"><input wire:model.live.debounce.450ms="costoUnitario" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" placeholder="80.00" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <x-admin-field label="Margen sobre venta (%) *" name="margen" hint="Mayor que 0 % y menor que 100 %."><input wire:model.live.debounce.450ms="margen" type="number" min="0.01" max="99.99" step="0.01" inputmode="decimal" placeholder="20.00" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <div class="rounded-2xl border border-orange-100 bg-orange-50 p-4" aria-live="polite"><p class="text-xs font-semibold text-brand-orange">Precio de venta por unidad</p><p class="mt-2 text-2xl font-semibold text-brand-ink">{{ $precioCalculado ? 'Bs '.$precioCalculado['precio_venta'] : 'Por calcular' }}</p>@if ($precioCalculado)<p class="mt-1 text-xs text-slate-600">Ganancia bruta: Bs {{ $precioCalculado['ganancia_unitaria'] }}/unidad</p>@endif</div>
                <x-admin-field label="Proveedor (opcional)" name="proveedorId"><select wire:model.blur="proveedorId" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Sin proveedor</option>@foreach ($proveedores as $proveedor)<option value="{{ $proveedor['id'] }}">{{ $proveedor['empresa'] }}</option>@endforeach</select></x-admin-field>
                <x-admin-field label="Vencimiento (si corresponde)" name="fechaVencimiento"><input wire:model.blur="fechaVencimiento" type="date" min="{{ now()->addDay()->format('Y-m-d') }}" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
            </div>
            @if (!empty($lotesAnterioresDetectados))
                <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs">
                    <div class="flex items-start gap-3">
                        <span class="text-xl"><x-lucide-shield-check /></span>
                        <div class="flex-1">
                            <h4 class="font-semibold text-amber-900">Protección de capital: Lotes anteriores con precio inferior</h4>
                            <p class="mt-1 text-amber-800 leading-relaxed">
                                Hay <strong>{{ count($lotesAnterioresDetectados) }}</strong> lote(s) anterior(es) activo(s) con existencias a menor precio. Para evitar descapitalización y poder financiar reposiciones futuras, se recomienda adecuar su precio de venta al nuevo valor (<strong>Bs {{ $precioCalculado['precio_venta'] ?? '0.00' }}</strong>).
                            </p>
                            <label class="mt-2.5 flex items-center gap-2 font-semibold text-amber-950 cursor-pointer">
                                <input type="checkbox" wire:model.live="ajustarPreciosAnteriores" class="size-4 rounded border-amber-300 text-brand-blue focus:ring-brand-blue">
                                Adecuar existencias de lotes anteriores al nuevo precio de reposición (Bs {{ $precioCalculado['precio_venta'] ?? '0.00' }})
                            </label>
                        </div>
                    </div>
                </div>
            @endif
            <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600">Margen bruto sobre venta: precio = costo ÷ (1 − margen/100). Ejemplo: costo Bs 80 y margen 20 % → precio Bs 100. Redondeo hacia arriba al centavo. No es recargo sobre costo ni incluye otros gastos.</p>
            @error('claveIngreso')<p class="mt-2 text-sm text-brand-red">{{ $message }}</p>@enderror
            @error('productoId')<p class="mt-2 text-sm text-brand-red">{{ $message }}</p>@enderror
            @error('almacenId')<p class="mt-2 text-sm text-brand-red">{{ $message }}</p>@enderror
            @error('sucursalId')<p class="mt-2 text-sm text-brand-red">{{ $message }}</p>@enderror
        @endif
        <footer class="mt-6 flex flex-wrap justify-end gap-3 border-t border-slate-100 bg-white pt-4">
            <button type="button" wire:click="cerrarIngreso" class="min-h-11 rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold">Cancelar</button>
            @if ($paso === 1)<button type="button" wire:click="siguientePaso" class="min-h-11 rounded-xl bg-brand-blue px-5 py-2.5 text-sm font-semibold text-white">Validar y continuar <x-lucide-arrow-right /></button>
            @else<button type="button" wire:click="anteriorPaso" class="min-h-11 rounded-xl border border-brand-blue px-5 py-2.5 text-sm font-semibold text-brand-blue">Anterior</button><button type="submit" wire:loading.attr="disabled" wire:target="guardar" class="min-h-11 rounded-xl bg-brand-red px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"><span wire:loading.remove wire:target="guardar">Registrar lote</span><span wire:loading wire:target="guardar">Registrando…</span></button>@endif
        </footer>
    </form>
</x-admin-modal>
