<div class="space-y-8 py-6">
    <!-- ENCABEZADO DE ÉXITO -->
    <div class="text-center space-y-3 max-w-2xl mx-auto">
        <div class="mx-auto grid size-20 place-items-center rounded-3xl bg-emerald-50 text-4xl text-emerald-600 shadow-inner border border-emerald-200 animate-bounce">
            🎉
        </div>
        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-4 py-1 text-xs font-black text-emerald-800 uppercase tracking-wider">
            ¡Pago Aprobado con Éxito vía Stripe!
        </div>
        <h1 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight">
            ¡Gracias por tu compra en MundiToys!
        </h1>
        <p class="text-sm text-slate-600">
            Tu pedido <strong class="text-brand-blue font-mono">#{{ $venta->numero_venta }}</strong> ha sido registrado y preparado para su recojo en tienda física. Hemos emitido tu factura electrónica oficial.
        </p>
    </div>

    <!-- NOTA EMAIL -->
    <div class="max-w-4xl mx-auto flex items-center justify-between gap-4 rounded-2xl border border-blue-200 bg-blue-50/80 p-4 text-xs text-blue-900 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="text-2xl">📧</span>
            <div>
                <strong>Comprobante y Factura enviados:</strong> Hemos enviado la factura oficial en PDF y las instrucciones de retiro al correo electrónico registrado.
            </div>
        </div>
        <button
            type="button"
            wire:click="descargarFactura"
            class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-3.5 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition"
        >
            <span>📥 Descargar Factura PDF</span>
        </button>
    </div>

    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
        <!-- COLUMNA IZQUIERDA: RESUMEN DEL PEDIDO Y SUCURSAL (7/12) -->
        <div class="md:col-span-7 space-y-6">
            <!-- DETALLES DEL RETIRO -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <span>🏪</span> Sucursal de Retiro Asignada
                    </h2>
                    <span class="rounded-full bg-emerald-50 border border-emerald-200 px-3 py-0.5 text-[11px] font-bold text-emerald-700">
                        Listo para Recojo
                    </span>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100 space-y-2 text-xs">
                    <div class="text-sm font-black text-brand-blue">
                        {{ $sucursal?->nombre ?? 'Sucursal MundiToys' }}
                    </div>
                    <div class="text-slate-600">
                        <strong>Dirección:</strong> {{ $sucursal?->direccion ?? 'Av. Principal' }} ({{ $sucursal?->ciudad ?? 'Bolivia' }})
                    </div>
                    <div class="text-slate-600">
                        <strong>Teléfono:</strong> {{ $sucursal?->telefono ?? '2-2440000' }}
                    </div>
                    <div class="text-slate-500 pt-1">
                        <strong>Horario de Atención:</strong> Lunes a Sábado de 09:00 a 19:00
                    </div>
                </div>

                <!-- PERSONA AUTORIZADA -->
                <div class="pt-1 text-xs text-slate-600 space-y-1">
                    <div><strong>Retira:</strong> {{ $venta->cliente?->name ?? 'Cliente Registrado' }}</div>
                    <div><strong>CI / Documento:</strong> {{ $venta->cliente?->ci_nit ?? 'Presentar Documento' }}</div>
                    <div><strong>Fecha de Compra:</strong> {{ $venta->fecha_venta?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</div>
                </div>
            </div>

            <!-- JUGUETES COMPRADOS -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-black text-slate-900">Juguetes en este Pedido</h3>
                    <span class="text-xs text-slate-400 font-mono">{{ $detalles->count() }} producto(s)</span>
                </div>

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
                        <div class="flex items-center gap-4 py-3">
                            <div class="size-14 rounded-xl border border-slate-100 bg-slate-50 p-1 shrink-0 flex items-center justify-center overflow-hidden">
                                @if($urlFoto)
                                    <img 
                                        src="{{ $urlFoto }}" 
                                        alt="{{ $prod?->nombre }}" 
                                        class="size-full object-contain"
                                        onerror="this.style.display='none'; this.nextElementSibling.classList.remove('hidden');"
                                    >
                                    <span class="text-xl hidden">🧸</span>
                                @else
                                    <span class="text-xl">🧸</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs font-black text-slate-900 truncate">{{ $prod?->nombre }}</h4>
                                <div class="text-[11px] text-slate-500">
                                    {{ $detalle->cantidad }} unidad(es) × Bs. {{ number_format($detalle->precio_unitario, 2) }}
                                </div>
                            </div>
                            <div class="text-xs font-black text-brand-blue">
                                Bs. {{ number_format($detalle->subtotal, 2) }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- TOTALES -->
                <div class="border-t border-slate-200 pt-4 flex items-baseline justify-between">
                    <span class="text-sm font-black text-slate-900">Monto Total Pagado:</span>
                    <div class="text-right">
                        <div class="text-xl font-black text-brand-blue">
                            Bs. {{ number_format($venta->total, 2) }}
                        </div>
                        <span class="text-[10px] text-slate-400">Stripe Online (Aprobado)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CÓDIGO QR Y ACCIONES (5/12) -->
        <div class="md:col-span-5 space-y-6">
            <!-- TARJETA DEL CÓDIGO QR -->
            <div class="rounded-3xl border-2 border-brand-blue/30 bg-white p-6 shadow-md text-center space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-left">
                        <h3 class="text-sm font-black text-slate-900">Código QR de Recojo</h3>
                        <p class="text-[11px] text-slate-500">Exhíbelo en sucursal para entrega inmediata</p>
                    </div>
                    <!-- BOTÓN VISIBLE / OCULTO -->
                    <button
                        type="button"
                        wire:click="toggleQr"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition"
                        title="{{ $mostrarQr ? 'Ocultar código QR' : 'Mostrar código QR' }}"
                    >
                        @if($mostrarQr)
                            <span>👁️ Ocultar QR</span>
                        @else
                            <span>👁️ Ver QR</span>
                        @endif
                    </button>
                </div>

                <!-- DISPLAY DEL QR -->
                @if($mostrarQr)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 inline-block transition duration-300">
                        <img
                            src="{{ $qrDataUri }}"
                            alt="Código QR de Compra {{ $venta->numero_venta }}"
                            class="size-52 mx-auto rounded-lg shadow-xs bg-white p-2"
                        >
                        <div class="mt-3 font-mono text-xs font-bold text-slate-700 tracking-wider">
                            {{ $venta->numero_venta }}
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/80 p-8 text-center transition">
                        <div class="text-3xl opacity-40">🔒</div>
                        <div class="mt-2 text-xs font-bold text-slate-600">Código QR Oculto</div>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Haz clic en "Ver QR" para mostrar el código cuando llegues a la sucursal.
                        </p>
                        <button
                            type="button"
                            wire:click="toggleQr"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700"
                        >
                            <span>Mostrar QR</span>
                        </button>
                    </div>
                @endif

                <div class="rounded-xl bg-amber-50 border border-amber-200 p-3 text-left text-[11px] text-amber-900 leading-relaxed">
                    💡 <strong>Consejo:</strong> El vendedor escaneará este código con la cámara de su terminal en la sucursal para verificar tu compra y entregarte tus juguetes al instante.
                </div>
            </div>

            <!-- ACCIONES POST-COMPRA -->
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-3">
                <button
                    type="button"
                    wire:click="descargarFactura"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-brand-orange py-3.5 text-center text-xs font-black text-white shadow-md shadow-orange-500/20 hover:bg-orange-600 transition"
                >
                    <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span>Descargar Factura Oficial PDF</span>
                </button>

                <a
                    href="{{ route('tienda.seguimiento') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-brand-blue/30 bg-blue-50/50 py-3.5 text-center text-xs font-black text-brand-blue hover:bg-blue-100 transition"
                    wire:navigate
                >
                    <span>📦 Ir a Seguimiento y Registro</span>
                </a>

                <a
                    href="{{ route('tienda.catalogo') }}"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white py-3 text-center text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    wire:navigate
                >
                    <span>🧸 Volver a la Tienda</span>
                </a>
            </div>
        </div>
    </div>
</div>

