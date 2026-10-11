<x-admin-modal property="modalDetalleAbierto" close="cerrarModalDetalle" :title="'Detalle del Despacho ' . ($entregaDetalle?->venta?->numero_venta ?? '')" :wide="true">
    @if ($entregaDetalle)
        <div class="space-y-6">
            <!-- ESTADO Y HORARIO PROGRAMADO -->
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center gap-3">
                    @php
                        $estadoClases = match($entregaDetalle->estado) {
                            'entregado' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
                            'en_camino' => 'bg-blue-100 text-brand-blue border-blue-300',
                            'cancelado' => 'bg-rose-100 text-brand-red border-rose-300',
                            default => 'bg-amber-100 text-amber-800 border-amber-300',
                        };
                        $estadoIcono = match($entregaDetalle->estado) {
                            'entregado' => 'check',
                            'en_camino' => 'truck',
                            'cancelado' => 'x',
                            default => 'timer',
                        };
                        $estadoTexto = match($entregaDetalle->estado) {
                            'entregado' => 'Entregado con Éxito',
                            'en_camino' => 'En Camino / Ruta Activa',
                            'cancelado' => 'Entrega Cancelada',
                            default => 'Pendiente de Despacho',
                        };
                    @endphp
                    <span class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold {{ $estadoClases }}">
                        <x-dynamic-component :component="'lucide-'.$estadoIcono" />
                        <span>{{ $estadoTexto }}</span>
                    </span>

                    <span class="text-xs text-slate-500 font-medium">
                        Fecha: <strong class="text-brand-ink">{{ $entregaDetalle->fecha_programada?->format('d/m/Y') ?? 'Sin programar' }}</strong>
                    </span>
                </div>

                <!-- INTERVALO MATEMÁTICO DE 90 MINUTOS -->
                <div class="flex items-center gap-2 text-xs text-slate-600 bg-white px-3 py-1.5 rounded-xl border border-slate-200 shadow-2xs">
                    <span class="text-brand-orange font-semibold"><x-lucide-timer /> Turno:</span>
                    <span class="font-semibold text-brand-ink">{{ $entregaDetalle->hora_inicio ? substr((string) $entregaDetalle->hora_inicio, 0, 5) : '--:--' }} a {{ $entregaDetalle->hora_fin ? substr((string) $entregaDetalle->hora_fin, 0, 5) : '--:--' }}</span>
                    <span class="text-[11px] text-slate-400 font-normal">(40 min entrega + 50 min margen)</span>
                </div>
            </div>

            @if ($entregaDetalle->estado === 'cancelado')
                <div class="rounded-2xl border border-rose-200 bg-rose-50/80 p-4 text-xs text-rose-900">
                    <div class="flex items-center gap-2 font-semibold text-brand-red text-sm">
                        <span><x-lucide-triangle-alert /></span>
                        <span>Motivo de Cancelación Registrado</span>
                    </div>
                    <p class="mt-1.5 text-xs font-medium leading-relaxed">{{ $entregaDetalle->motivo_cancelacion }}</p>
                    <p class="mt-2 text-[11px] text-rose-600">
                        Cancelado el: {{ $entregaDetalle->cancelado_at?->format('d/m/Y H:i') ?? 'Registrado' }} · Notificación emitida al Administrador.
                    </p>
                </div>
            @endif

            <!-- CUADÍCULA: CLIENTE, DESTINO Y REPARTIDOR -->
            <div class="grid gap-4 md:grid-cols-2">
                <!-- DATOS DEL CLIENTE -->
                <div class="rounded-2xl border border-orange-100 bg-white p-4 shadow-xs">
                    <h4 class="text-xs font-semibold tracking-[.18em] text-brand-orange">Información del Cliente</h4>
                    <dl class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">Nombre Completo:</dt>
                            <dd class="font-semibold text-brand-ink text-right">{{ $entregaDetalle->venta?->cliente?->name ?? 'Cliente' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">Teléfono de Contacto:</dt>
                            <dd class="font-semibold text-brand-blue text-right">
                                @if ($entregaDetalle->venta?->cliente?->telefono)
                                    <a href="https://wa.me/591{{ preg_replace('/\D/', '', $entregaDetalle->venta->cliente->telefono) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:underline">
                                        <span><x-lucide-message-circle /></span>
                                        <span>{{ $entregaDetalle->venta->cliente->telefono }}</span>
                                    </a>
                                @else
                                    <span class="text-slate-400">No registrado</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">CI / NIT:</dt>
                            <dd class="font-semibold text-slate-700 text-right">{{ $entregaDetalle->venta?->cliente?->ci_nit ?? 'S/N' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Correo Electrónico:</dt>
                            <dd class="font-semibold text-slate-700 text-right truncate max-w-[200px]">{{ $entregaDetalle->venta?->cliente?->email ?? 'S/E' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- LUGAR DE ENTREGA & SUCURSAL -->
                <div class="rounded-2xl border border-blue-100 bg-white p-4 shadow-xs">
                    <h4 class="text-xs font-semibold tracking-[.18em] text-brand-blue">Lugar de Despacho en La Paz</h4>
                    <dl class="mt-3 space-y-2 text-xs">
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">Zona / Macrodistrito:</dt>
                            <dd class="font-semibold text-brand-ink text-right">{{ $entregaDetalle->direccion?->zona ?? 'La Paz' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">Dirección Completa:</dt>
                            <dd class="font-semibold text-slate-700 text-right max-w-[220px]">{{ $entregaDetalle->direccion?->direccion_completa ?? 'Sin dirección' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-1.5">
                            <dt class="text-slate-500 font-medium">Sucursal Origen:</dt>
                            <dd class="font-semibold text-slate-700 text-right">{{ $entregaDetalle->sucursal?->nombre ?? 'Almacén Central' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-slate-500 font-medium">Chofer / Repartidor:</dt>
                            <dd class="font-semibold text-brand-ink text-right">
                                {{ $entregaDetalle->empleado?->name ?? 'Sin asignar' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- TABLA DE JUGUETES SOLICITADOS -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                <div class="border-b border-slate-100 bg-slate-50 px-4 py-3">
                    <h4 class="text-xs font-semibold tracking-[.18em] text-slate-700">Juguetes para este Envío</h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-700">
                        <thead class="border-b border-slate-100 bg-slate-50/50 text-[11px] font-semibold text-slate-400">
                            <tr>
                                <th class="px-4 py-2.5">Juguete</th>
                                <th class="px-4 py-2.5 text-center">Cant.</th>
                                <th class="px-4 py-2.5 text-right">P. Unitario</th>
                                <th class="px-4 py-2.5 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($entregaDetalle->venta?->detalles ?? [] as $det)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            @php
                                                $imgPrincipal = $det->producto?->imagenes?->firstWhere('es_principal', true) ?? $det->producto?->imagenes?->first();
                                            @endphp
                                            <div class="size-10 shrink-0 overflow-hidden rounded-lg bg-slate-100">
                                                @if ($imgPrincipal)
                                                    <img src="{{ asset('storage/'.$imgPrincipal->ruta_imagen) }}" alt="{{ $det->producto?->nombre }}" class="size-full object-cover">
                                                @else
                                                    <div class="grid size-full place-items-center text-xs"><x-lucide-toy-brick class="text-slate-300" /></div>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="font-semibold text-brand-ink">{{ $det->producto?->nombre ?? 'Juguete' }}</p>
                                                <p class="text-[11px] text-slate-400">Cód: {{ $det->producto?->codigo_producto ?? 'S/C' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center font-semibold text-brand-ink">{{ $det->cantidad }}</td>
                                    <td class="px-4 py-3 text-right">Bs. {{ number_format((float) $det->precio_unitario, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-brand-blue">Bs. {{ number_format((float) $det->subtotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-slate-400">Sin detalles registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold text-brand-ink">
                            <tr>
                                <td colspan="3" class="px-4 py-2.5 text-right text-[11px] text-slate-500">Total del Pedido:</td>
                                <td class="px-4 py-2.5 text-right text-sm text-brand-orange">
                                    Bs. {{ number_format((float) ($entregaDetalle->venta?->total ?? 0), 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($entregaDetalle->notas_despacho)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                    <strong class="font-semibold text-slate-800">Notas de Despacho:</strong>
                    <span class="ml-1">{{ $entregaDetalle->notas_despacho }}</span>
                </div>
            @endif

            <!-- ACCIONES DEL MODAL -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                <button type="button" wire:click="cerrarModalDetalle" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-brand-ink shadow-2xs hover:bg-slate-50 transition">
                    Cerrar
                </button>

                <div class="flex items-center gap-2">
                    @if ($entregaDetalle->estado !== 'entregado')
                        <button type="button" wire:click="abrirAsignar({{ $entregaDetalle->id }})" class="inline-flex items-center gap-1.5 rounded-xl border border-brand-blue/30 bg-blue-50 px-4 py-2.5 text-xs font-semibold text-brand-blue hover:bg-blue-100 transition">
                            <span><x-lucide-timer /></span>
                            <span>{{ $entregaDetalle->hora_inicio ? 'Reasignar Turno' : 'Asignar Horario' }}</span>
                        </button>
                    @endif

                    @if ($entregaDetalle->estado === 'pendiente')
                        <button type="button" wire:click="iniciarRuta({{ $entregaDetalle->id }})" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-blue px-4 py-2.5 text-xs font-semibold text-white shadow-2xs hover:bg-blue-700 transition">
                            <span><x-lucide-truck /></span>
                            <span>Iniciar Reparto</span>
                        </button>
                    @endif

                    @if ($entregaDetalle->estado === 'en_camino')
                        <button type="button" wire:click="abrirConfirmarEntrega({{ $entregaDetalle->id }})" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-2xs hover:bg-emerald-700 transition">
                            <span><x-lucide-check /></span>
                            <span>Confirmar Entrega</span>
                        </button>
                    @endif

                    @if ($entregaDetalle->estado !== 'cancelado' && $entregaDetalle->estado !== 'entregado')
                        <button type="button" wire:click="abrirCancelar({{ $entregaDetalle->id }})" class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-xs font-semibold text-brand-red hover:bg-rose-50 transition">
                            <span><x-lucide-x /></span>
                            <span>Cancelar Envío</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-admin-modal>
