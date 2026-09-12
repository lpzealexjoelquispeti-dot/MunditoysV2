<x-admin-modal property="modalAsignarAbierto" close="cerrarModalAsignar" title="Programación y Asignación de Turno" :wide="true">
    <form wire:submit="guardarAsignacion" class="space-y-6">
        <!-- AVISO MATEMÁTICO DE REGLA DE 90 MINUTOS -->
        <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-brand-blue">
            <div class="flex items-center gap-2 font-bold text-sm">
                <span>📐</span>
                <span>Motor de Asignación Matemática (Intervalos de 1h 30m)</span>
            </div>
            <p class="mt-1 text-slate-700 leading-relaxed">
                Cada entrega contempla un tiempo máximo de <strong>40 minutos de traslado y recepción</strong> en el punto del cliente, complementado con <strong>50 minutos de holgura y retorno a la sucursal</strong>. El sistema genera matemáticamente los turnos disponibles para evitar cualquier solapamiento.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <!-- FECHA DE DESPACHO -->
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Fecha del Despacho *</label>
                <div class="flex gap-2">
                    <input
                        wire:model.live="asignarFecha"
                        type="date"
                        min="{{ now()->toDateString() }}"
                        class="min-h-11 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-brand-ink focus:border-brand-blue"
                    >
                    <button
                        type="button"
                        wire:click="$set('asignarFecha', '{{ now()->toDateString() }}')"
                        class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100"
                    >
                        Hoy
                    </button>
                    <button
                        type="button"
                        wire:click="$set('asignarFecha', '{{ now()->addDay()->toDateString() }}')"
                        class="rounded-xl border border-slate-300 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100"
                    >
                        Mañana
                    </button>
                </div>
                @error('asignarFecha') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
            </div>

            <!-- CHOFER O ENCARGADO DE LOGÍSTICA -->
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Chofer / Repartidor Asignado</label>
                <select
                    wire:model.live="asignarEmpleadoId"
                    class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-brand-ink focus:border-brand-blue"
                >
                    <option value="">Cualquier repartidor disponible</option>
                    @foreach ($empleados as $emp)
                        <option value="{{ $emp->id }}">{{ $emp->name }} {{ $emp->apellido_paterno }} ({{ $emp->email }})</option>
                    @endforeach
                </select>
                @error('asignarEmpleadoId') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- SELECTOR INTERACTIVO DE SLOTS MATEMÁTICOS -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Turnos Disponibles del Día (12:00 a 18:00) *</label>
                <span class="text-[11px] text-slate-400">Selecciona el slot que ocupará el repartidor</span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($slotsDisponibles as $slot)
                    @php
                        $esSeleccionado = $asignarHoraInicio === $slot['inicio'];
                        $estaDisponible = $slot['disponible'];
                    @endphp
                    <div
                        @if ($estaDisponible)
                            wire:click="seleccionarSlot('{{ $slot['inicio'] }}')"
                        @endif
                        @class([
                            'relative rounded-2xl border p-3.5 transition select-none',
                            'cursor-pointer hover:border-brand-blue hover:shadow-xs' => $estaDisponible,
                            'border-brand-blue bg-blue-50/80 ring-2 ring-brand-blue shadow-xs' => $esSeleccionado && $estaDisponible,
                            'border-emerald-200 bg-emerald-50/40' => $estaDisponible && ! $esSeleccionado,
                            'border-slate-200 bg-slate-100/70 opacity-60 cursor-not-allowed' => ! $estaDisponible,
                        ])
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-base font-black text-brand-ink">{{ $slot['inicio'] }}</span>
                                <span class="text-xs text-slate-500 font-medium">a {{ $slot['fin'] }}</span>
                            </div>
                            @if ($estaDisponible)
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold',
                                    'bg-brand-blue text-white' => $esSeleccionado,
                                    'bg-emerald-100 text-emerald-800' => ! $esSeleccionado,
                                ])>
                                    {{ $esSeleccionado ? '✓ Seleccionado' : 'Disponible' }}
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                    Ocupado
                                </span>
                            @endif
                        </div>

                        <div class="mt-2 text-[11px] text-slate-500">
                            <p><strong>Ventana:</strong> {{ $slot['ventana'] }}</p>
                            @if (! $estaDisponible)
                                <p class="mt-1 truncate font-medium text-rose-600">
                                    {{ $slot['numero_venta'] ?? 'Pedido' }} ({{ $slot['cliente'] ?? 'Reservado' }})
                                </p>
                            @else
                                <p class="mt-1 text-slate-400">40 min viaje + 50 min retorno</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-500">
                        Cargando slots matemáticos...
                    </div>
                @endforelse
            </div>
            @error('asignarHoraInicio') <span class="mt-1.5 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
        </div>

        <!-- NOTAS DE DESPACHO -->
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Notas o Instrucciones para el Despacho (Opcional)</label>
            <textarea
                wire:model="asignarNotas"
                rows="2"
                maxlength="500"
                placeholder="Ej. Timbre blanco en reja negra, llamar 10 min antes de llegar..."
                class="w-full rounded-xl border border-slate-300 bg-white p-3 text-sm text-brand-ink focus:border-brand-blue"
            ></textarea>
            @error('asignarNotas') <span class="mt-1 block text-xs font-medium text-brand-red">{{ $message }}</span> @enderror
        </div>

        <!-- ACCIONES -->
        <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
            <button
                type="button"
                wire:click="cerrarModalAsignar"
                class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-brand-ink shadow-2xs hover:bg-slate-50 transition"
            >
                Cancelar
            </button>
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-xl bg-brand-orange px-5 py-2.5 text-xs font-bold text-white shadow-2xs hover:bg-orange-600 transition"
            >
                <span>⏱</span>
                <span>Confirmar Turno y Asignación</span>
            </button>
        </div>
    </form>
</x-admin-modal>
