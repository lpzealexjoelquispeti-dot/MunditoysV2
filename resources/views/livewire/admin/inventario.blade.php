<x-admin-shell active="inventario">
    <div class="mx-auto max-w-[1700px]">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-bold uppercase tracking-[.2em] text-brand-orange">Existencias y lotes</p><h1 class="mt-1 text-3xl font-bold text-brand-ink sm:text-4xl">Inventario</h1><p class="mt-1 text-sm text-slate-600">Cada ingreso conserva su costo, precio y movimiento. Sin eliminación definitiva.</p></div>
            <button type="button" wire:click="abrirIngreso" class="min-h-11 rounded-xl bg-brand-red px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-brand-red-dark">＋ Registrar ingreso</button>
        </header>
        <section class="mt-6 rounded-2xl border border-orange-100 bg-white p-5 shadow-sm" aria-label="Filtros de inventario">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="md:col-span-2"><x-admin-field label="Buscar producto" name="buscar"><input wire:model.live.debounce.350ms="buscar" type="search" maxlength="100" placeholder="Nombre o código del juguete" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field></div>
                <x-admin-field label="Sucursal" name="filtroSucursal"><select wire:model.live="filtroSucursal" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Todas</option>@foreach ($sucursales as $sucursal)<option value="{{ $sucursal['id'] }}">{{ $sucursal['nombre'] }}</option>@endforeach</select></x-admin-field>
                <x-admin-field label="Almacén" name="filtroAlmacen"><select wire:model.live="filtroAlmacen" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Todos</option>@foreach ($almacenes as $almacen)@if (!$filtroSucursal || (string) $almacen['sucursal_id'] === $filtroSucursal)<option value="{{ $almacen['id'] }}">{{ $almacen['nombre'] }}</option>@endif@endforeach</select></x-admin-field>
                <x-admin-field label="Existencias" name="filtroStock"><select wire:model.live="filtroStock" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Todas</option><option value="disponible">Con existencias</option><option value="bajo">En mínimo o por debajo</option><option value="agotado">Sin existencias</option></select></x-admin-field>
                <x-admin-field label="Estado del registro" name="filtroEstado"><select wire:model.live="filtroEstado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="activos">Activos</option><option value="archivados">Archivados</option></select></x-admin-field>
                <x-admin-field label="Ordenar por" name="orden"><select wire:model.live="orden" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="recientes">Más recientes</option><option value="antiguos">Más antiguos</option><option value="stock_asc">Menor stock</option><option value="stock_desc">Mayor stock</option></select></x-admin-field>
                <button type="button" wire:click="limpiarFiltros" class="self-end rounded-xl px-4 py-3 text-sm font-semibold text-brand-blue hover:bg-blue-50">Limpiar filtros</button>
            </div>
        </section>
        <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 text-sm"><p><strong>{{ $inventarios->total() }}</strong> registros encontrados</p><span wire:loading class="text-brand-blue" role="status">Actualizando…</span></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="bg-blue-50 text-brand-ink"><tr><th class="px-5 py-3">Producto</th><th class="px-5 py-3">Ubicación</th><th class="px-5 py-3 text-right">Existencias</th><th class="px-5 py-3 text-right">Mínimo</th><th class="px-5 py-3 text-right">Lotes</th><th class="px-5 py-3">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($inventarios as $inventario)
                            <tr wire:key="inventario-{{ $inventario->id }}" class="hover:bg-orange-50/40">
                                <td class="px-5 py-4"><p class="font-semibold text-brand-ink">{{ $inventario->producto?->nombre ?? 'Producto no disponible' }}</p><p class="mt-1 text-xs text-slate-500">{{ $inventario->producto?->codigo_producto }}</p></td>
                                <td class="px-5 py-4">{{ $inventario->almacen?->sucursal?->nombre ?? 'Sucursal no disponible' }}<span class="mt-1 block text-xs text-slate-500">{{ $inventario->almacen?->nombre }}</span></td>
                                <td class="px-5 py-4 text-right"><span @class(['rounded-full px-3 py-1 font-semibold', 'bg-orange-100 text-orange-800' => (int) $inventario->stock_disponible <= $inventario->stock_minimo, 'bg-emerald-50 text-emerald-800' => (int) $inventario->stock_disponible > $inventario->stock_minimo])>{{ (int) $inventario->stock_disponible }}</span></td>
                                <td class="px-5 py-4 text-right">{{ $inventario->stock_minimo }}</td><td class="px-5 py-4 text-right">{{ $inventario->lotes_count }}</td>
                                <td class="px-5 py-4"><div class="flex gap-2" x-data>
                                    <button type="button" wire:click="verDetalle({{ $inventario->id }})" class="rounded-xl bg-orange-50 px-3 py-2 font-semibold text-brand-orange">Lotes e historial</button>
                                    @if ($inventario->trashed())
                                        <button type="button" x-on:click="window.confirmarCrud($wire, 'restaurar', {{ $inventario->id }}, '¿Restaurar este inventario?', 'Se conservará su historial y podrás agregar nuevos lotes.')" class="rounded-xl bg-blue-50 px-3 py-2 font-semibold text-brand-blue">Restaurar</button>
                                    @else
                                        <button type="button" wire:click="abrirIngreso({{ $inventario->producto_id }}, {{ $inventario->almacen?->sucursal_id ?? 'null' }}, {{ $inventario->almacen_id }})" @disabled(!$inventario->producto || $inventario->producto->trashed() || $inventario->producto->estado !== 'activo') class="rounded-xl bg-blue-50 px-3 py-2 font-semibold text-brand-blue disabled:opacity-40">Añadir lote</button>
                                        <button type="button" wire:click="abrirBaja({{ $inventario->id }})" @disabled((int) $inventario->stock_disponible !== 0) title="Solo se puede archivar sin existencias" class="rounded-xl bg-slate-100 px-3 py-2 font-semibold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40">Archivar</button>
                                    @endif
                                </div></td>
                            </tr>
                        @empty<tr><td colspan="6" class="px-6 py-16 text-center"><p class="text-4xl">📦</p><h2 class="mt-4 text-lg font-bold text-brand-ink">No hay inventarios para mostrar</h2><p class="mt-2 text-slate-500">Revisa los filtros o registra el primer ingreso por lote.</p></td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            @if ($inventarios->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $inventarios->links() }}</div>@endif
        </div>
    </div>
    @include('livewire.admin.partials.inventario-form-modal')
    @include('livewire.admin.partials.inventario-detail-modal')
    @include('livewire.admin.partials.inventario-edit-lote-modal')
    @include('livewire.admin.partials.inventario-descuento-modal')
    <x-admin-modal property="mostrarBaja" close="cerrarBaja" title="Archivar inventario sin existencias">
        <p class="mb-4 text-sm text-slate-600">Se conservarán todos los lotes y movimientos. Archivar no registra una salida de juguetes ni cambia cantidades.</p>
        <x-admin-field label="Motivo del archivado *" name="motivoBaja" hint="Explica el motivo entre 10 y 500 caracteres."><textarea wire:model.blur="motivoBaja" maxlength="500" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></textarea></x-admin-field>
        <div class="mt-5 flex justify-end gap-3" x-data><button type="button" wire:click="cerrarBaja" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold">Cancelar</button><button type="button" x-on:click="window.confirmarCrud($wire, 'archivar', null, '¿Confirmar archivado?', 'El motivo y tu usuario quedarán registrados. No se eliminarán los lotes ni sus movimientos.')" wire:loading.attr="disabled" class="rounded-xl bg-brand-blue px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Confirmar archivado</button></div>
    </x-admin-modal>
</x-admin-shell>
