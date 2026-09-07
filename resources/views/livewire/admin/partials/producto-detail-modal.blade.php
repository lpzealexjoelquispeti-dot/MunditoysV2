<div x-data="{ actual: 0 }" x-show="$wire.modalDetalleAbierto" x-effect="if (! $wire.modalDetalleAbierto) actual = 0" x-cloak x-on:keydown.escape.window="$wire.cerrarDetalle()" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="detalle-producto-titulo">
    <div class="flex min-h-full items-center justify-center p-4 sm:p-6">
        <button type="button" class="fixed inset-0 bg-brand-ink/60 backdrop-blur-sm" x-on:click="$wire.cerrarDetalle()" aria-label="Cerrar detalle"></button>

        @if ($modalDetalleAbierto && $productoDetalle !== [])
            <section x-show="$wire.modalDetalleAbierto" x-transition class="relative w-full max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl">
                <button type="button" wire:click="cerrarDetalle" class="absolute right-4 top-4 z-20 grid size-10 place-items-center rounded-full bg-white/95 text-xl text-slate-500 shadow-md hover:text-brand-red" aria-label="Cerrar">×</button>
                <div class="grid lg:grid-cols-2">
                    <div class="bg-gradient-to-br from-orange-50 to-blue-50 p-5 sm:p-7">
                        @if (count($productoDetalle['imagenes']) > 0)
                            <div class="relative overflow-hidden rounded-2xl bg-white shadow-sm">
                                @foreach ($productoDetalle['imagenes'] as $indice => $imagen)
                                    <img x-show="actual === {{ $indice }}" x-transition.opacity src="{{ $imagen['url'] }}" alt="{{ $productoDetalle['nombre'] }} - imagen {{ $indice + 1 }}" class="aspect-square w-full object-contain">
                                @endforeach
                                @if (count($productoDetalle['imagenes']) > 1)
                                    <button type="button" x-on:click="actual = actual === 0 ? {{ count($productoDetalle['imagenes']) - 1 }} : actual - 1" class="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-brand-blue shadow">‹</button>
                                    <button type="button" x-on:click="actual = actual === {{ count($productoDetalle['imagenes']) - 1 }} ? 0 : actual + 1" class="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-brand-blue shadow">›</button>
                                @endif
                            </div>
                            <div class="mt-3 flex justify-center gap-2">
                                @foreach ($productoDetalle['imagenes'] as $indice => $imagen)
                                    <button type="button" x-on:click="actual = {{ $indice }}" x-bind:class="actual === {{ $indice }} ? 'bg-brand-blue' : 'bg-slate-300'" class="size-2.5 rounded-full" aria-label="Ver imagen {{ $indice + 1 }}"></button>
                                @endforeach
                            </div>
                        @else
                            <div class="grid aspect-square place-items-center rounded-2xl bg-white text-7xl">🧸</div>
                        @endif
                    </div>

                    <div class="max-h-[85vh] overflow-y-auto p-6 sm:p-8">
                        <p class="text-xs font-bold uppercase tracking-[.18em] text-brand-orange">{{ $productoDetalle['categoria'] }} · {{ $productoDetalle['marca'] }}</p>
                        <h2 id="detalle-producto-titulo" class="mt-2 pr-10 text-3xl font-bold text-brand-ink">{{ $productoDetalle['nombre'] }}</h2>
                        <p class="mt-5 leading-7 text-slate-600">{{ $productoDetalle['descripcion'] }}</p>

                        <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Edad recomendada</dt><dd class="mt-1 font-bold text-brand-ink">{{ $productoDetalle['edad_recomendada'] }} años</dd></div>
                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Dimensiones</dt><dd class="mt-1 font-bold text-brand-ink">{{ $productoDetalle['dimensiones'] }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Código interno</dt><dd class="mt-1 break-all font-bold text-brand-ink">{{ $productoDetalle['codigo_producto'] }}</dd></div>
                            <div class="rounded-xl bg-slate-50 p-3"><dt class="text-xs text-slate-500">Identificador QR</dt><dd class="mt-1 break-all font-bold text-brand-ink">{{ $productoDetalle['codigo_qr'] }}</dd></div>
                        </dl>

                        @if ($productoDetalle['modelo_glb_url'])
                            <div class="mt-6">
                                <h3 class="mb-2 text-lg font-bold text-brand-ink">Vista 3D</h3>
                                <model-viewer src="{{ $productoDetalle['modelo_glb_url'] }}" alt="Modelo 3D de {{ $productoDetalle['nombre'] }}" camera-controls auto-rotate shadow-intensity="1" class="h-72 w-full rounded-2xl bg-slate-100"></model-viewer>
                                <p class="mt-2 text-center text-xs text-slate-500">Arrastra para rotar y usa la rueda para acercar.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    </div>
</div>
