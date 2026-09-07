<div class="mx-auto max-w-4xl">
    <div class="rounded-[2rem] bg-brand-blue p-7 text-white shadow-sm sm:p-10">
        <p class="text-sm font-semibold uppercase tracking-[.18em] text-orange-200">Mi espacio</p>
        <h1 class="mt-2 text-4xl font-bold">Hola, {{ $name }} 👋</h1>
        <p class="mt-3 max-w-xl text-blue-100">Este es tu rincón en MundiToys. Mantén tus datos al día para una experiencia más personal.</p>
    </div>

    <form wire:submit="guardar" class="mt-6 grid gap-6 rounded-[2rem] border border-blue-100 bg-white p-7 shadow-sm sm:grid-cols-[180px_1fr] sm:p-10" novalidate>
        <div class="text-center">
            <div class="mx-auto grid size-28 place-items-center overflow-hidden rounded-full bg-brand-orange-soft text-4xl ring-4 ring-orange-100">
                @if ($foto)
                    <img src="{{ $foto->temporaryUrl() }}" alt="Vista previa de foto" class="size-full object-cover">
                @elseif ($fotoActual)
                    <img src="{{ Storage::disk('public')->url($fotoActual) }}" alt="Foto de perfil" class="size-full object-cover">
                @else
                    🧸
                @endif
            </div>
            <label for="foto" class="mt-4 inline-block cursor-pointer rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-brand-blue hover:bg-blue-100">Cambiar foto</label>
            <input wire:model.live="foto" id="foto" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only">
            <p class="mt-2 text-xs text-slate-500">JPG, PNG o WEBP · máx. 2 MB</p>
            <p class="mt-1 text-sm text-brand-red">@error('foto') {{ $message }} @enderror</p>
        </div>

        <div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><label class="text-sm font-semibold">Nombre</label><input wire:model.live.debounce.350ms="name" class="mt-1.5 w-full rounded-xl border px-4 py-3 @error('name') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-1 min-h-5 text-sm text-brand-red">@error('name') {{ $message }} @enderror</p></div>
                <div><label class="text-sm font-semibold">Apellido paterno</label><input wire:model.live.debounce.350ms="apellidoPaterno" class="mt-1.5 w-full rounded-xl border px-4 py-3 @error('apellidoPaterno') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-1 min-h-5 text-sm text-brand-red">@error('apellidoPaterno') {{ $message }} @enderror</p></div>
                <div><label class="text-sm font-semibold">Apellido materno</label><input wire:model.live.debounce.350ms="apellidoMaterno" class="mt-1.5 w-full rounded-xl border px-4 py-3 @error('apellidoMaterno') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-1 min-h-5 text-sm text-brand-red">@error('apellidoMaterno') {{ $message }} @enderror</p></div>
                <div><label class="text-sm font-semibold">Celular</label><input wire:model.live.debounce.350ms="telefono" type="tel" inputmode="tel" autocomplete="tel" placeholder="Ej.: 71234567" class="mt-1.5 w-full rounded-xl border px-4 py-3 @error('telefono') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-1 min-h-5 text-sm text-brand-red">@error('telefono') {{ $message }} @enderror</p></div>
                <div><label class="text-sm font-semibold">CI / NIT</label><input wire:model.live.debounce.350ms="ciNit" class="mt-1.5 w-full rounded-xl border px-4 py-3 @error('ciNit') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-1 min-h-5 text-sm text-brand-red">@error('ciNit') {{ $message }} @enderror</p></div>
                <div class="sm:col-span-2"><label class="text-sm font-semibold">Correo electrónico</label><input value="{{ $email }}" readonly class="mt-1.5 w-full cursor-not-allowed rounded-xl border border-slate-100 bg-slate-50 px-4 py-3 text-slate-500"><p class="mt-1 text-xs text-slate-500">El correo no se cambia desde este formulario.</p></div>
            </div>

            @if (session('status'))
                <p class="mt-4 rounded-xl bg-blue-50 px-4 py-3 text-sm font-semibold text-brand-blue">{{ session('status') }}</p>
            @endif

            <button class="mt-6 rounded-xl bg-brand-red px-5 py-3 font-semibold text-white shadow-sm hover:bg-brand-red-dark" wire:loading.attr="disabled" wire:target="guardar">Guardar cambios</button>
        </div>
    </form>
</div>
