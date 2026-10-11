<div class="mx-auto max-w-[1700px]">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-3xl font-semibold text-brand-ink sm:text-4xl">{{ $titulo }}</h1></div>
        <button type="button" wire:click="crear" class="min-h-11 rounded-xl bg-brand-red px-5 py-3 text-sm font-semibold text-white shadow-sm"><x-lucide-plus /> Registrar {{ $singular }}</button>
    </header>
    <section class="mt-6 rounded-2xl border border-orange-100 bg-white p-5 shadow-sm" aria-label="Filtros">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="md:col-span-2"><x-admin-field label="Buscar" name="buscar"><input wire:model.live.debounce.350ms="buscar" type="search" maxlength="100" placeholder="Nombre o datos del registro" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field></div>
            <x-admin-field label="Estado" name="filtroEstado"><select wire:model.live="filtroEstado" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="activo">Activos</option><option value="inactivo">Inactivos</option><option value="todos">Activos e inactivos</option><option value="eliminados">Papelera</option></select></x-admin-field>
            <x-admin-field label="Ordenar por" name="orden"><select wire:model.live="orden" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="recientes">Más recientes</option><option value="antiguos">Más antiguos</option><option value="nombre_asc">Nombre A–Z</option><option value="nombre_desc">Nombre Z–A</option></select></x-admin-field>
            @if ($modulo === 'almacenes')
                <x-admin-field label="Sucursal" name="filtroSucursal"><select wire:model.live="filtroSucursal" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Todas</option>@foreach ($sucursales as $sucursal)<option value="{{ $sucursal['id'] }}" wire:key="filtro-sucursal-{{ $sucursal['id'] }}">{{ $sucursal['nombre'] }}{{ $sucursal['estado'] ? '' : ' (inactiva)' }}</option>@endforeach</select></x-admin-field>
                <x-admin-field label="Tipo" name="filtroTipo"><select wire:model.live="filtroTipo" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm"><option value="">Todos</option><option value="principal">Principal</option><option value="secundario">Secundario</option></select></x-admin-field>
            @endif
        </div>
        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4 text-sm"><p><strong>{{ $registros->total() }}</strong> registros</p><button type="button" wire:click="limpiarFiltros" class="rounded-lg px-3 py-2 font-semibold text-brand-blue hover:bg-blue-50">Limpiar filtros</button></div>
    </section>
    <p wire:loading role="status" class="mt-3 text-sm text-brand-blue">Actualizando…</p>
    <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[750px] text-left text-sm">
            <thead class="bg-blue-50 text-brand-ink"><tr><th scope="col" class="px-5 py-3">{{ $modulo === 'proveedores' ? 'Empresa / contacto' : 'Nombre' }}</th><th scope="col" class="px-5 py-3">{{ $modulo === 'almacenes' ? 'Sucursal / tipo' : ($modulo === 'sucursales' ? 'Zona / dirección' : 'Teléfono / correo') }}</th><th scope="col" class="px-5 py-3">Estado</th><th scope="col" class="px-5 py-3">Acciones</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($registros as $registro)
                    <tr wire:key="{{ $modulo }}-{{ $registro->id }}" class="hover:bg-orange-50/40">
                        <td class="px-5 py-4"><strong class="block text-brand-ink">{{ $registro->{$nombreCampo} }}</strong>@if ($modulo === 'proveedores')<span class="mt-1 block text-xs text-slate-500">{{ $registro->nombre_contacto }}</span>@endif</td>
                        <td class="px-5 py-4">
                            @if ($modulo === 'almacenes'){{ $registro->sucursal?->nombre ?? 'Sucursal no disponible' }}<span class="mt-1 block text-xs text-slate-500">{{ ucfirst($registro->tipo) }} · {{ $registro->inventarios_count }} inventarios</span>
                            @elseif ($modulo === 'sucursales'){{ $registro->zona }}<span class="mt-1 block max-w-sm text-xs text-slate-500">{{ $registro->direccion }}</span>
                            @else{{ $registro->telefono }}<span class="mt-1 block text-xs text-slate-500">{{ $registro->email ?: 'Sin correo' }}</span>@endif
                        </td>
                        <td class="px-5 py-4"><span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-800' => !$registro->trashed() && $registro->estado, 'bg-slate-100 text-slate-600' => !$registro->trashed() && !$registro->estado, 'bg-red-50 text-brand-red' => $registro->trashed()])>{{ $registro->trashed() ? 'En papelera' : ($registro->estado ? 'Activo' : 'Inactivo') }}</span></td>
                        <td class="px-5 py-4"><div class="flex gap-2" x-data>
                            <button type="button" wire:click="verDetalle({{ $registro->id }})" class="rounded-xl bg-orange-50 px-3 py-2 font-semibold text-brand-orange">Detalles</button>
                            @if ($registro->trashed())
                                <button type="button" x-on:click="window.confirmarCrud($wire, 'restaurar', {{ $registro->id }}, '¿Restaurar este registro?', 'Se conservarán sus datos y relaciones históricas.')" class="rounded-xl bg-blue-50 px-3 py-2 font-semibold text-brand-blue">Restaurar</button>
                            @else
                                <button type="button" wire:click="editar({{ $registro->id }})" class="rounded-xl bg-blue-50 px-3 py-2 font-semibold text-brand-blue">Editar</button>
                                <button type="button" x-on:click="window.confirmarCrud($wire, 'eliminar', {{ $registro->id }}, '¿Enviar este registro a la papelera?', 'La baja es recuperable y solo se permitirá si no afecta operaciones activas.')" class="rounded-xl bg-red-50 px-3 py-2 font-semibold text-brand-red">Dar de baja</button>
                            @endif
                        </div></td>
                    </tr>
                @empty<tr><td colspan="4" class="px-6 py-16 text-center"><span class="text-4xl"><x-lucide-clipboard-list /></span><p class="mt-3 font-semibold text-slate-600">Sin registros</p></td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if ($registros->hasPages())<div class="mt-5 rounded-xl bg-white p-4">{{ $registros->links() }}</div>@endif
</div>
