<div class="space-y-6 py-4">
    <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-slate-900 sm:text-3xl">Tu carrito</h1>
        @if($items->isNotEmpty())
            <button
                type="button"
                wire:click="vaciar"
                wire:confirm="¿Vaciar el carrito?"
                class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50"
            >
                <x-lucide-trash-2 /> Vaciar
            </button>
        @endif
    </div>

    @if($items->isEmpty())
        <div class="rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-16 text-center">
            <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-orange-50 text-4xl text-brand-orange">
                <x-lucide-shopping-cart />
            </div>
            <h2 class="mt-5 text-xl font-bold text-slate-900">Tu carrito está vacío</h2>
            <a href="{{ route('tienda.catalogo') }}" class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-brand-orange px-6 py-3 text-sm font-semibold text-white transition hover:bg-orange-600" wire:navigate>
                Ver catálogo
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- JUGUETES -->
            <div class="divide-y divide-slate-100 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm lg:col-span-8">
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
                    <div class="flex items-center gap-4 p-4 sm:p-5">
                        <a href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}" class="shrink-0" wire:navigate>
                            <div class="grid size-20 place-items-center overflow-hidden rounded-2xl bg-slate-50 p-1.5">
                                @if($urlFoto)
                                    <img src="{{ $urlFoto }}" alt="{{ $producto->nombre }}" class="size-full object-contain" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                                    <span class="hidden text-3xl"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @else
                                    <span class="text-3xl"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @endif
                            </div>
                        </a>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('tienda.producto.detalle', ['codigo' => $producto->codigo_producto]) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-blue" wire:navigate>
                                {{ $producto->nombre }}
                            </a>
                            <p class="text-sm text-slate-500">Bs. {{ number_format($item['precio_unitario'], 2) }}</p>

                            <div class="mt-2 flex items-center gap-1 sm:hidden">
                                @include('livewire.cliente.partials.contador-carrito')
                            </div>
                        </div>

                        <div class="hidden items-center gap-1 sm:flex">
                            @include('livewire.cliente.partials.contador-carrito')
                        </div>

                        <p class="min-w-[90px] text-right font-bold text-brand-blue">Bs. {{ number_format($item['subtotal'], 2) }}</p>

                        <button type="button" wire:click="eliminar({{ $producto->id }})" class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 transition hover:bg-red-50 hover:text-red-600" aria-label="Quitar {{ $producto->nombre }}" title="Quitar">
                            <x-lucide-x />
                        </button>
                    </div>
                @endforeach
            </div>

            <!-- RESUMEN -->
            <div class="sticky top-24 space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-4">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <dt>Juguetes ({{ $totalArticulos }})</dt>
                        <dd>Bs. {{ number_format($total, 2) }}</dd>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Recojo en sucursal</dt>
                        <dd class="font-semibold text-emerald-600">Gratis</dd>
                    </div>
                    <div class="flex items-baseline justify-between border-t border-slate-100 pt-3">
                        <dt class="font-semibold text-slate-900">Total</dt>
                        <dd class="text-2xl font-bold text-brand-blue">Bs. {{ number_format($total, 2) }}</dd>
                    </div>
                </dl>

                <a href="{{ route('tienda.checkout') }}" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-orange py-3.5 text-sm font-semibold text-white transition hover:bg-orange-600" wire:navigate>
                    Continuar al pago
                </a>

                <a href="{{ route('tienda.catalogo') }}" class="block text-center text-sm font-semibold text-slate-500 hover:text-brand-blue" wire:navigate>
                    Seguir comprando
                </a>

                <p class="flex items-center justify-center gap-1.5 text-xs text-slate-400"><x-lucide-lock /> Pago seguro con Stripe</p>
            </div>
        </div>
    @endif
</div>
