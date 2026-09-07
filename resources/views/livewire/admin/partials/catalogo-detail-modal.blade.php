<x-admin-modal property="mostrarDetalle" close="cerrarDetalle" :title="'Detalle de '.$singular">
    @if ($detalle)
        @if ($detalle['imagen'])<img src="{{ asset('storage/'.$detalle['imagen']) }}" alt="Imagen de {{ $detalle['nombre'] }}" class="mx-auto h-48 w-full rounded-2xl bg-slate-50 object-contain">@else<div class="grid h-40 place-items-center rounded-2xl bg-slate-50 text-6xl">{{ $icono }}</div>@endif
        <h3 class="mt-5 text-xl font-bold text-brand-ink">{{ $detalle['nombre'] }}</h3><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $detalle['descripcion'] }}</p>
        <dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Estado</dt><dd class="mt-1 font-semibold">{{ $detalle['eliminado'] ? 'En papelera' : ($detalle['estado'] ? 'Activa' : 'Inactiva') }}</dd></div><div class="rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">Fecha de registro</dt><dd class="mt-1 font-semibold">{{ $detalle['creada'] }}</dd></div><div><dt class="text-slate-500">Productos asociados</dt><dd>{{ $detalle['productos'] }}</dd></div></dl>
    @endif
</x-admin-modal>
