<x-admin-modal property="mostrarDetalle" close="cerrarDetalle" title="Detalles del registro">
    @if ($detalle)
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ($campos as $campo => $etiqueta)
                <div wire:key="dato-{{ $campo }}" class="rounded-xl bg-slate-50 p-3"><dt class="text-xs font-semibold text-slate-500">{{ $etiqueta }}</dt><dd class="mt-1 break-words text-sm text-brand-ink">{{ $detalle[$campo] ?? 'Sin registrar' }}</dd></div>
            @endforeach
            <div><dt class="text-xs text-slate-500">Estado</dt><dd class="mt-1 text-sm">{{ $detalle['eliminado'] ? 'En papelera' : ($detalle['estado'] ? 'Activo' : 'Inactivo') }}</dd></div>
            <div><dt class="text-xs text-slate-500">Fecha de registro</dt><dd class="mt-1 text-sm">{{ $detalle['creado'] }}</dd></div>
        </dl>
    @endif
</x-admin-modal>
