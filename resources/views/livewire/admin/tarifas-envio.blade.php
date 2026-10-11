<x-admin-shell active="tarifas-envio">
    <form wire:submit="guardar" class="max-w-3xl space-y-6 rounded-2xl border border-slate-200 bg-white p-6 font-sans shadow-sm">
        <div>
            <h1 class="text-2xl font-semibold text-brand-ink">Configurar envío a domicilio</h1>
        </div>
        @if(session('tarifas_guardadas')) <p role="status" class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('tarifas_guardadas') }}</p> @endif
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach(['tarifa_km_usd' => 'Tarifa por km (USD)', 'minimo_usd' => 'Cobro mínimo (USD)', 'cambio_paralelo' => 'Cambio paralelo (Bs por USD)'] as $campo => $etiqueta)
                <div>
                    <label for="{{ $campo }}" class="text-sm font-semibold">{{ $etiqueta }}</label>
                    <input id="{{ $campo }}" wire:model="{{ $campo }}" inputmode="decimal" maxlength="10" class="mt-2 w-full rounded-xl border-slate-200" autocomplete="off">
                    @error($campo) <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
            @endforeach
        </div>
        <p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">Costo = máx(mínimo, km × tarifa) × tipo de cambio</p>
        <label class="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" wire:model="habilitado" class="rounded border-slate-300"> Habilitar delivery</label>
        <button type="submit" wire:loading.attr="disabled" class="rounded-xl bg-brand-blue px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Guardar configuración</button>
    </form>
</x-admin-shell>
