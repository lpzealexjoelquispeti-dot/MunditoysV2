<div class="mx-auto grid max-w-4xl overflow-hidden rounded-[1.75rem] border border-red-100 bg-white shadow-xl shadow-red-100/60 lg:grid-cols-[.8fr_1.2fr]">
    <section class="toy-dot-grid hidden min-h-[35rem] bg-brand-red p-8 text-white lg:flex lg:flex-col lg:justify-between">
        <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="MundiToys" class="size-24 drop-shadow-lg">
        <p class="text-4xl font-bold leading-tight">Construyendo sueños</p>
    </section>

    <section class="p-6 sm:p-8">
        <div class="mx-auto max-w-lg">
            <a href="{{ route('welcome') }}" class="text-sm font-semibold text-brand-blue hover:underline" wire:navigate><x-lucide-arrow-left /> Tienda</a>
            <h1 class="mt-6 text-3xl font-bold text-brand-ink">Crea tu cuenta</h1>

            <form wire:submit="register" class="mt-4 grid gap-x-3 gap-y-2 sm:grid-cols-2" novalidate>
                <div class="sm:col-span-2"><label for="name" class="text-sm font-semibold text-slate-700">Nombre</label><input wire:model.live.debounce.350ms="name" id="name" autocomplete="given-name" placeholder="Tu nombre" class="mt-1 w-full rounded-xl border px-4 py-2.5 outline-none @error('name') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('name') {{ $message }} @enderror</p></div>
                <div><label for="apellidoPaterno" class="text-sm font-semibold text-slate-700">Apellido paterno</label><input wire:model.live.debounce.350ms="apellidoPaterno" id="apellidoPaterno" autocomplete="family-name"  class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('apellidoPaterno') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('apellidoPaterno') {{ $message }} @enderror</p></div>
                <div><label for="apellidoMaterno" class="text-sm font-semibold text-slate-700">Apellido materno</label><input wire:model.live.debounce.350ms="apellidoMaterno" id="apellidoMaterno" autocomplete="additional-name"  class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('apellidoMaterno') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('apellidoMaterno') {{ $message }} @enderror</p></div>
                <div><label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico</label><input wire:model.live.debounce.350ms="email" id="email" type="email" autocomplete="email" placeholder="nombre@correo.com" class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('email') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('email') {{ $message }} @enderror</p></div>
                <div><label for="telefono" class="text-sm font-semibold text-slate-700">Celular</label><input wire:model.live.debounce.350ms="telefono" id="telefono" type="tel" inputmode="tel" autocomplete="tel" placeholder="71234567" class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('telefono') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('telefono') {{ $message }} @enderror</p></div>
                <div><label for="password" class="text-sm font-semibold text-slate-700">Contraseña</label><input wire:model.live.debounce.350ms="password" id="password" type="password" autocomplete="new-password" placeholder="8+ caracteres, Aa y número" class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('password') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('password') {{ $message }} @enderror</p></div>
                <div><label for="passwordConfirmation" class="text-sm font-semibold text-slate-700">Confirmar contraseña</label><input wire:model.live.debounce.350ms="passwordConfirmation" id="passwordConfirmation" type="password" autocomplete="new-password" class="mt-1 w-full rounded-xl border px-4 py-2.5 text-sm outline-none @error('passwordConfirmation') border-red-400 bg-red-50 @else border-slate-200 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('passwordConfirmation') {{ $message }} @enderror</p></div>
                <button class="w-full rounded-xl bg-brand-blue px-5 py-3 font-semibold text-white shadow-md shadow-blue-200 transition hover:-translate-y-0.5 hover:bg-brand-blue-dark sm:col-span-2" wire:loading.attr="disabled" wire:target="register"><span wire:loading.remove wire:target="register">Crear cuenta</span><span wire:loading wire:target="register">Creando…</span></button>
            </form>
            <p class="mt-3 text-center text-sm text-slate-600">¿Ya tienes cuenta? <a href="{{ route('login') }}" class="font-semibold text-brand-blue hover:underline" wire:navigate>Ingresa</a></p>
        </div>
    </section>
</div>
