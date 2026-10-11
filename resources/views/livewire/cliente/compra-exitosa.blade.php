<div class="mx-auto max-w-4xl space-y-8 py-6">
    <!-- ÉXITO -->
    <div class="space-y-3 text-center">
        <div class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-50 text-3xl text-emerald-600">
            <x-lucide-circle-check />
        </div>
        <h1 class="text-3xl font-bold text-slate-900">¡Gracias por tu compra!</h1>
        <p class="text-slate-600">Pedido <span class="font-mono font-semibold text-brand-blue">{{ $venta->numero_venta }}</span>. Te enviamos la factura por correo.</p>
    </div>

    <div class="grid grid-cols-1 items-start gap-6 md:grid-cols-12">
        <!-- QR DE RECOJO -->
        <div class="space-y-4 rounded-3xl border-2 border-brand-blue/20 bg-white p-6 text-center shadow-sm md:col-span-5">
            <h2 class="font-semibold text-slate-900">Muestra este QR en la sucursal</h2>

            @if($mostrarQr)
                <img src="{{ $qrDataUri }}" alt="Código QR de compra {{ $venta->numero_venta }}" class="mx-auto size-56 rounded-2xl border border-slate-200 bg-white p-2">
            @else
                <div class="mx-auto grid size-56 place-items-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 text-slate-400">
                    <div>
                        <x-lucide-lock class="mx-auto text-3xl" />
                        <p class="mt-2 text-sm font-semibold text-slate-600">Código QR Oculto</p>
                    </div>
                </div>
            @endif

            <button type="button" wire:click="toggleQr" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-blue hover:underline">
                @if($mostrarQr)
                    <x-lucide-eye-off /> Ocultar QR
                @else
                    <x-lucide-eye /> Ver QR
                @endif
            </button>
        </div>

        <!-- RETIRO Y JUGUETES -->
        <div class="space-y-4 md:col-span-7">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start gap-3">
                    <x-lucide-store class="mt-0.5 text-xl text-brand-blue" />
                    <div class="text-sm">
                        <p class="font-semibold text-slate-900">{{ $sucursal?->nombre ?? 'Sucursal MundiToys' }}</p>
                        <p class="text-slate-600">{{ $sucursal?->direccion ?? 'Av. Principal' }}, {{ $sucursal?->ciudad ?? 'Bolivia' }}</p>
                        <p class="mt-1 text-slate-500">Lun a sáb, 09:00 a 19:00{{ $sucursal?->telefono ? ', tel. '.$sucursal->telefono : '' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="divide-y divide-slate-100">
                    @foreach($detalles as $detalle)
                        @php
                            $prod = $detalle->producto;
                            $imgObj = $prod?->imagenes->firstWhere('principal', true)
                                ?? $prod?->imagenes->sortBy('orden')->first()
                                ?? $prod?->imagenes->first();
                            $rutaArchivo = $imgObj?->ruta_archivo;
                            $urlFoto = $rutaArchivo
                                ? (str_starts_with($rutaArchivo, 'http') ? $rutaArchivo : asset('storage/' . ltrim($rutaArchivo, '/')))
                                : null;
                        @endphp
                        <div class="flex items-center gap-3 py-3 first:pt-0">
                            <div class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-slate-50 p-1">
                                @if($urlFoto)
                                    <img src="{{ $urlFoto }}" alt="{{ $prod?->nombre }}" class="size-full object-contain" onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');">
                                    <span class="hidden text-xl"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @else
                                    <span class="text-xl"><x-lucide-toy-brick class="text-slate-300" /></span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $prod?->nombre }}</p>
                                <p class="text-xs text-slate-500">{{ $detalle->cantidad }} × Bs. {{ number_format($detalle->precio_unitario, 2) }}</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-900">Bs. {{ number_format($detalle->subtotal, 2) }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-3">
                    <span class="font-semibold text-slate-900">Total pagado</span>
                    <span class="text-xl font-bold text-brand-blue">Bs. {{ number_format($venta->total, 2) }}</span>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" wire:click="descargarFactura" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brand-orange py-3 text-sm font-semibold text-white transition hover:bg-orange-600">
                    <x-lucide-download /> Descargar factura
                </button>
                <a href="{{ route('tienda.seguimiento') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" wire:navigate>
                    <x-lucide-package /> Ver mis pedidos
                </a>
            </div>
        </div>
    </div>
</div>
