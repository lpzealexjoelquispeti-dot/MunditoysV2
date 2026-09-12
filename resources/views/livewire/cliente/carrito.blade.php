<div class="space-y-8 py-4">
    <!-- ENCABEZADO Y BREADCRUMB -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-orange-100 pb-6">
        <div>
            <div class="flex items-center gap-3">
                <span class="grid size-11 place-items-center rounded-2xl bg-brand-orange/10 text-2xl text-brand-orange">🛒</span>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900">Carrito de Compras</h1>
                    <p class="text-sm text-slate-500">Revisa tus juguetes seleccionados antes de pasar a la pasarela de pago.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a
                href="{{ route('tienda.catalogo') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:bg-slate-50 hover:text-brand-blue"
                wire:navigate
            >
                <span>←</span> Seguir Explorando
            </a>
            @if($items->isNotEmpty())
                <button
                    type="button"
                    wire:click="vaciar"
                    wire:confirm="¿Estás seguro de que deseas vaciar todos los productos del carrito?"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50/50 px-3.5 py-2.5 text-xs font-bold text-red-600 transition hover:bg-red-100"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    <span>Vaciar</span>
                </button>
            @endif
        </div>
    </div>

    @if($items->isEmpty())
        <!-- ESTADO VACÍO -->
        <div class="rounded-3xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-orange-50 text-4xl text-brand-orange shadow-inner">
                🧸
            </div>
            <h2 class="mt-5 text-xl font-black text-slate-900">Tu carrito está actualmente vacío</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                ¡Aún no has agregado ningún juguete a tu carrito! Explora nuestro catálogo mágico con figuras interactivas y modelos 3D.
            </p>
            <div class="mt-6">
                <a
                    href="{{ route('tienda.catalogo') }}"
                    class="inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-6 py-3.5 text-sm font-black text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600"
                    wire:navigate
                >
                    <span>Ver Catálogo de Juguetes</span>
                    <span>→</span>
                </a>
            </div>
        </div>
    @else
        <!-- CONTENIDO DEL CARRITO: 2 COLUMNAS EFICIENTES -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 items-start">
            <!-- COLUMNA IZQUIERDA: LISTADO DE PRODUCTOS (65%) -->
            <div class="lg:col-span-8 space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm divide-y divide-slate-100 overflow-hidden">
                    <div class="flex items-center justify-between bg-slate-50/70 px-6 py-3.5 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <span>Juguete / Producto</span>
                        <div class="flex items-center gap-12">
                            <span class="hidden sm:inline">Cantidad</span>
                            <span>Subtotal</span>
                        </div>
                    </div>

                    @foreach($items as $item)
                        @php
                            $producto = $item['producto'];
                            $imgObj = $producto->imagenes->firstWhere('principal', true) 
                                ?? $producto->imagenes->sortBy('orden')->first() 
                                ?? $producto->imagenes->first();
                            $rutaArchivo = $imgObj?->ruta_archivo;
                            $urlFoto = $rutaArchivo 
                                ? (str_starts_with($rutaArchivo, 'http') ? $rutaArchivo : asset('storage/' . ltrim($rutaArchivo, '/'))) 
                                : null;
                        @endphp
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 sm:p-6 transition hover:bg-slate-50/50">
                            <!-- INFO PRODUCTO -->
                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                <a href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}" class="shrink-0 group" wire:navigate>
                                    <div class="size-20 sm:size-24 rounded-2xl border border-slate-100 bg-slate-50 p-1.5 flex items-center justify-center overflow-hidden transition group-hover:border-brand-orange/40">
                                        @if($urlFoto)
                                            <img 
                                                src="{{ $urlFoto }}" 
                                                alt="{{ $producto->nombre }}" 
                                                class="size-full object-contain transition duration-300 group-hover:scale-105"
                                                onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"
                                            >
                                            <span class="text-3xl hidden">🧸</span>
                                        @else
                                            <span class="text-3xl">🧸</span>
                                        @endif
                                    </div>
                                </a>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        @if($producto->categoria)
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                                {{ $producto->categoria->nombre }}
                                            </span>
                                        @endif
                                        <span class="font-mono text-[11px] text-slate-400">#{{ $producto->codigo_producto }}</span>
                                    </div>
                                    <h3 class="mt-1 font-black text-slate-900 hover:text-brand-blue text-sm sm:text-base truncate">
                                        <a href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}" wire:navigate>
                                            {{ $producto->nombre }}
                                        </a>
                                    </h3>
                                    <div class="mt-1 text-xs text-slate-500">
                                        Precio unitario: <strong class="text-slate-800">Bs. {{ number_format($item['precio_unitario'], 2) }}</strong>
                                    </div>
                                </div>
                            </div>

                            <!-- CONTADOR Y SUBTOTAL -->
                            <div class="flex items-center justify-between sm:justify-end gap-6 pt-3 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                                <!-- CONTADOR [-] [N] [+] -->
                                <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 shadow-inner">
                                    <button
                                        type="button"
                                        wire:click="decrementar({{ $producto->id }})"
                                        class="grid size-7 place-items-center rounded-lg bg-white text-slate-700 font-bold shadow-xs hover:bg-slate-100 active:scale-95 transition"
                                        title="Reducir cantidad"
                                    >
                                        -
                                    </button>
                                    <span class="w-8 text-center text-xs font-black text-slate-900">
                                        {{ $item['cantidad'] }}
                                    </span>
                                    <button
                                        type="button"
                                        wire:click="incrementar({{ $producto->id }})"
                                        class="grid size-7 place-items-center rounded-lg bg-white text-slate-700 font-bold shadow-xs hover:bg-slate-100 active:scale-95 transition"
                                        title="Aumentar cantidad"
                                    >
                                        +
                                    </button>
                                </div>

                                <!-- MONTO SUBTOTAL -->
                                <div class="text-right min-w-[90px]">
                                    <div class="text-base font-black text-brand-blue">
                                        Bs. {{ number_format($item['subtotal'], 2) }}
                                    </div>
                                </div>

                                <!-- BOTÓN ELIMINAR -->
                                <button
                                    type="button"
                                    wire:click="eliminar({{ $producto->id }})"
                                    class="grid size-8 place-items-center rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 transition"
                                    title="Quitar juguete del carrito"
                                >
                                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- NOTA INFORMATIVA RECOJO -->
                <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900">
                    <span class="text-lg shrink-0">🏪</span>
                    <p>
                        <strong>Recojo en Sucursal:</strong> Podrás elegir la sucursal física más cercana en el siguiente paso. El costo de entrega es <strong>Bs. 0.00 (Totalmente Gratis)</strong>.
                    </p>
                </div>
            </div>

            <!-- COLUMNA DERECHA: RESUMEN DE COMPRA (35%) -->
            <div class="lg:col-span-4 sticky top-24 space-y-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-lg font-black text-slate-900">Resumen del Pedido</h2>
                        <p class="text-xs text-slate-500">Cálculo de productos e impuestos incluidos</p>
                    </div>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between text-slate-600">
                            <span>Artículos ({{ $totalArticulos }}):</span>
                            <span class="font-bold text-slate-900">Bs. {{ number_format($total, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Recojo en Sucursal:</span>
                            <span class="font-bold text-emerald-600">Gratis (Bs. 0.00)</span>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <span>Descuento aplicado:</span>
                            <span class="font-bold text-slate-400">Bs. 0.00</span>
                        </div>

                        <div class="border-t border-slate-200 pt-4 flex items-baseline justify-between">
                            <span class="text-base font-black text-slate-900">Total a Pagar:</span>
                            <div class="text-right">
                                <div class="text-2xl font-black text-brand-blue">
                                    Bs. {{ number_format($total, 2) }}
                                </div>
                                <span class="text-[10px] text-slate-400">Factura oficial incluida</span>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN CONTINUAR AL CHECKOUT -->
                    <div class="pt-2">
                        <a
                            href="{{ route('tienda.checkout') }}"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-orange py-4 text-center text-sm font-black text-white shadow-lg shadow-orange-500/25 transition hover:bg-orange-600 active:scale-98"
                            wire:navigate
                        >
                            <span>Continuar al Pago</span>
                            <span>→</span>
                        </a>
                    </div>

                    <!-- BENEFICIOS Y SEGURIDAD -->
                    <div class="space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        <div class="flex items-center gap-2">
                            <span class="text-emerald-500">🔒</span>
                            <span>Pasarela de pago 100% segura con <strong>Stripe</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-brand-orange">📜</span>
                            <span>Facturación electrónica boliviana con código QR</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-brand-blue">⚡</span>
                            <span>Recojo inmediato tras validación de compra</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
