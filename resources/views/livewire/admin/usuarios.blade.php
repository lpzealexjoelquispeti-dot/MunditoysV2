<x-admin-shell active="usuarios">
    <div class="mx-auto max-w-[1600px]">
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><h1 class="text-3xl font-semibold">Usuarios y accesos</h1></div>
            <button wire:click="crear" type="button" class="rounded-xl bg-brand-red px-5 py-3 text-sm font-semibold text-white"><x-lucide-plus /> Invitar empleado</button>
        </header>
        <div class="mt-6 flex gap-2" role="group" aria-label="Tipo de usuarios">
            @foreach (['empleados' => 'Empleados', 'clientes' => 'Clientes'] as $clave => $etiqueta)
                <button type="button" wire:click="$set('grupo', '{{ $clave }}')" wire:key="grupo-{{ $clave }}" aria-pressed="{{ $grupo === $clave ? 'true' : 'false' }}" @class(['rounded-xl px-5 py-2.5 text-sm font-semibold', 'bg-brand-blue text-white' => $grupo === $clave, 'bg-white text-slate-600' => $grupo !== $clave])>{{ $etiqueta }}</button>
            @endforeach
        </div>
        <section class="mt-4 grid gap-3 rounded-2xl border border-blue-100 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-5" aria-label="Filtros de usuarios">
            <x-admin-field label="Buscar" name="buscar"><input id="buscar" type="search" wire:model.live.debounce.400ms="buscar" maxlength="100" placeholder="Nombre, correo o celular" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></x-admin-field>
            <x-admin-field label="Rol" name="rol"><select id="rol" wire:model.live="rol" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="">Todos</option>@foreach ($grupo === 'clientes' ? ['Cliente'] : $roles as $nombreRol)<option wire:key="filtro-{{ $nombreRol }}" value="{{ $nombreRol }}">{{ $nombreRol === 'Logistica' ? 'Logística' : $nombreRol }}</option>@endforeach</select></x-admin-field>
            <x-admin-field label="Estado" name="estado"><select id="estado" wire:model.live="estado" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">@foreach (['todos'=>'Todos', 'activo'=>'Activos', 'inactivo'=>'Inactivos', 'pendiente'=>'Invitaciones pendientes', 'revocada'=>'Invitaciones revocadas'] as $valor=>$texto)<option wire:key="estado-{{ $valor }}" value="{{ $valor }}">{{ $texto }}</option>@endforeach</select></x-admin-field>
            <x-admin-field label="Ordenar" name="orden"><select id="orden" wire:model.live="orden" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"><option value="recientes">Más recientes</option><option value="antiguos">Más antiguos</option><option value="nombre">Nombre A–Z</option></select></x-admin-field>
            <button wire:click="limpiarFiltros" type="button" class="self-start rounded-xl border border-slate-200 px-4 py-3 text-sm text-brand-blue md:mt-6">Limpiar filtros</button>
        </section>
        <p class="mt-4 text-sm text-slate-500" role="status">{{ $usuarios->total() }} cuentas</p>
        <p wire:loading class="mt-2 text-sm text-brand-blue" role="status">Procesando solicitud…</p>
        <div class="mt-3 overflow-x-auto rounded-2xl border border-blue-100 bg-white shadow-sm">
            <table class="w-full text-left text-sm">
                <thead class="bg-blue-50 text-brand-blue"><tr><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Rol</th><th class="px-5 py-4">Sucursal</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Acciones</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($usuarios as $usuario)
                        <tr wire:key="usuario-{{ $usuario['id'] }}">
                            <td class="px-5 py-4"><p class="font-semibold">{{ $usuario['name'] }} {{ $usuario['apellido_paterno'] }} {{ $usuario['apellido_materno'] }}</p><p class="mt-1 text-slate-500">{{ $usuario['email'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $usuario['telefono'] }}</p></td>
                            <td class="px-5 py-4">{{ $usuario['rolUsuario'] === 'Logistica' ? 'Logística' : $usuario['rolUsuario'] }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-700">
                                    <span><x-lucide-map-pin /></span> {{ $usuario['sucursal_nombre'] ?? 'Sin sucursal' }}
                                </span>
                            </td>
                            <td class="px-5 py-4"><span @class(['inline-flex rounded-full px-3 py-1 text-xs font-semibold', 'bg-emerald-50 text-emerald-700'=>$usuario['estado'], 'bg-orange-50 text-orange-800'=>!$usuario['estado']])>{{ $usuario['situacion'] }}</span>@if($usuario['invitacion_pendiente'] && !$usuario['enviada'])<p class="mt-2 text-xs text-slate-500">Sin envío confirmado</p>@endif</td>
                            <td class="px-5 py-4"><div class="flex flex-wrap gap-2">
                                @php
                                    $esOtroAdmin = $usuario['rolUsuario'] === 'Administrador' && $usuario['id'] !== auth()->id();
                                @endphp
                                <button type="button" wire:click="verDetalle({{ $usuario['id'] }})" class="rounded-lg bg-blue-50 px-3 py-2 font-medium text-brand-blue">Detalles</button>
                                @if (! $esOtroAdmin)
                                    <button type="button" wire:click="editar({{ $usuario['id'] }})" class="rounded-lg border border-blue-100 px-3 py-2 font-medium text-brand-blue">Editar</button>
                                @endif
                                @if ($usuario['invitacion_pendiente'])
                                    <button type="button" wire:loading.attr="disabled" x-on:click="window.confirmarAccesoUsuario($wire, 'reenviar', {{ $usuario['id'] }})" class="rounded-lg bg-orange-50 px-3 py-2 font-medium text-orange-800">Reenviar invitación</button>
                                    @if (!$usuario['revocada'])<button type="button" wire:loading.attr="disabled" x-on:click="window.confirmarAccesoUsuario($wire, 'revocar', {{ $usuario['id'] }})" class="rounded-lg bg-red-50 px-3 py-2 font-medium text-brand-red">Revocar</button>@endif
                                @elseif ($usuario['id'] !== auth()->id() && ! $esOtroAdmin)
                                    <button type="button" wire:loading.attr="disabled" x-on:click="window.confirmarAccesoUsuario($wire, '{{ $usuario['estado'] ? 'desactivar' : 'reactivar' }}', {{ $usuario['id'] }})" class="rounded-lg bg-orange-50 px-3 py-2 font-medium text-orange-800">{{ $usuario['estado'] ? 'Desactivar' : 'Reactivar' }}</button>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-14 text-center text-slate-500">No hay usuarios que coincidan con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">{{ $usuarios->links() }}</div>
    </div>
    <x-admin-modal property="mostrarFormulario" close="cerrarFormulario" :title="$registroId ? 'Editar usuario' : 'Invitar empleado'">
        <form x-on:submit.prevent="window.confirmarAccesoUsuario($wire, 'guardar')" novalidate class="space-y-4">
            <x-admin-field label="Nombres *" name="name"><input id="name" wire:model.blur="name" autocomplete="given-name" maxlength="100" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin-field label="Apellido paterno" name="apellido_paterno"><input id="apellido_paterno" wire:model.blur="apellido_paterno" maxlength="100" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
                <x-admin-field label="Apellido materno" name="apellido_materno"><input id="apellido_materno" wire:model.blur="apellido_materno" maxlength="100" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
            </div>
            <p class="text-xs text-slate-500">Es obligatorio al menos uno de los dos apellidos.</p>
            <x-admin-field label="Celular *" name="telefono" hint="8 dígitos; comienza con 6 o 7. +591 opcional."><input id="telefono" type="tel" wire:model.blur="telefono" autocomplete="tel" maxlength="13" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"></x-admin-field>
            <x-admin-field label="Correo de acceso *" name="email" :hint="$registroId ? 'El correo se conserva para proteger la identidad de la cuenta.' : 'La invitación llegará a este correo; en Sandbox se captura dentro de Mailtrap.'"><input id="email" type="email" wire:model.blur="email" @readonly($registroId) autocomplete="email" maxlength="254" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm read-only:bg-slate-100"></x-admin-field>
            <x-admin-field label="Rol *" name="rolUsuario"><select id="rolUsuario" wire:model.live="rolUsuario" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">@foreach ($rolUsuario === 'Cliente' && $registroId ? ['Cliente'] : $roles as $nombreRol)<option value="{{ $nombreRol }}" wire:key="asignar-{{ $nombreRol }}">{{ $nombreRol === 'Logistica' ? 'Logística' : $nombreRol }}</option>@endforeach</select></x-admin-field>
            <x-admin-field label="Sucursal de trabajo" name="sucursal_id" hint="Obligatoria para Vendedor; define la sucursal autorizada para ventas POS.">
                <select id="sucursal_id" wire:model.live="sucursal_id" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                    <option value="">Selecciona una sucursal...</option>
                    @foreach ($sucursales as $suc)
                        <option value="{{ $suc->id }}" wire:key="asignar-suc-{{ $suc->id }}">{{ $suc->nombre }} ({{ $suc->ciudad }})</option>
                    @endforeach
                </select>
            </x-admin-field>
            <p class="rounded-xl bg-blue-50 p-3 text-sm leading-6 text-brand-blue">La contraseña personal la establece el destinatario. Nunca se envía por correo ni se muestra al administrador. El usuario puede gestionar su foto desde Mi perfil.</p>
            @error('passwordAdministrador')<p class="text-sm text-brand-red" role="alert">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4"><button type="button" wire:click="cerrarFormulario" class="rounded-xl border border-slate-300 px-4 py-3 text-sm">Cancelar</button><button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-red px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ $registroId ? 'Guardar cambios' : 'Crear invitación' }}</button></div>
        </form>
    </x-admin-modal>
    <x-admin-modal property="mostrarDetalle" close="cerrarDetalle" title="Usuario y auditoría" :wide="true">
        @if ($detalle)
            <h3 class="text-xl font-semibold">{{ $detalle['name'] }} {{ $detalle['apellido_paterno'] }} {{ $detalle['apellido_materno'] }}</h3>
            <p class="mt-2 text-sm text-slate-600">{{ $detalle['email'] }} · {{ $detalle['telefono'] }}</p>
            <p class="mt-2 text-sm text-brand-blue">{{ $detalle['rolUsuario'] }} · {{ $detalle['situacion'] }} · <x-lucide-map-pin /> {{ $detalle['sucursal_nombre'] ?? 'Sin sucursal' }}</p>
            @if ($detalle['invitacion_pendiente'])<p class="mt-2 text-xs text-slate-500">Envío confirmado: {{ $detalle['enviada'] ?? 'No registrado' }} · Vence: {{ $detalle['expira'] ?? 'Sin enlace vigente' }}</p>@endif
            <h4 class="mt-6 font-semibold">Últimos 20 eventos</h4>
            <div class="mt-3 space-y-3">
                @forelse ($historial as $indice => $evento)
                    <details wire:key="auditoria-{{ $indice }}" class="rounded-xl border border-slate-200 p-3 text-sm">
                        <summary class="cursor-pointer font-medium">{{ str_replace('_', ' ', ucfirst($evento['accion'])) }} · {{ $evento['fecha'] }} · {{ $evento['responsable'] }}</summary>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach (['anterior'=>'Antes', 'nuevo'=>'Después'] as $clave=>$texto)
                                <div wire:key="auditoria-{{ $indice }}-{{ $clave }}"><p class="font-semibold text-brand-blue">{{ $texto }}</p><dl class="mt-2 space-y-1">
                                    @foreach (($evento[$clave] ?? []) as $campo=>$valor)
                                        <div wire:key="dato-{{ $indice }}-{{ $clave }}-{{ $campo }}" class="break-words"><dt class="inline text-slate-500">{{ str_replace('_', ' ', $campo) }}:</dt> <dd class="inline">{{ is_array($valor) ? implode(', ', $valor) : (is_bool($valor) ? ($valor ? 'Sí' : 'No') : ($valor ?? '—')) }}</dd></div>
                                    @endforeach
                                </dl></div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p class="text-sm text-slate-500">Todavía no hay eventos de este módulo para la cuenta.</p>
                @endforelse
            </div>
        @endif
    </x-admin-modal>
</x-admin-shell>
