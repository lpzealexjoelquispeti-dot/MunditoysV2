<div class="mx-auto grid max-w-4xl overflow-hidden rounded-[1.75rem] border border-blue-100 bg-white shadow-xl shadow-blue-100/60 lg:grid-cols-[.9fr_1.1fr]">
    <section class="toy-dot-grid hidden min-h-[30rem] bg-brand-blue p-8 text-white lg:flex lg:flex-col lg:justify-between">
        <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="MundiToys" class="size-24 drop-shadow-lg">
        <p class="text-4xl font-bold leading-tight">Construyendo sueños</p>
    </section>
    <section class="p-6 sm:p-8"><div class="mx-auto max-w-sm"><a href="{{ route('welcome') }}" class="text-sm font-semibold text-brand-blue hover:underline" wire:navigate><x-lucide-arrow-left /> Tienda</a><h1 class="mt-6 text-3xl font-bold text-brand-ink">Ingresa a tu cuenta</h1>
        @if (session('status'))
            <p role="status" class="mt-4 rounded-xl bg-blue-50 p-3 text-sm text-brand-blue">{{ session('status') }}</p>
        @endif
        <form wire:submit="login" class="mt-5 space-y-3" novalidate>
            <div><label for="email" class="text-sm font-semibold text-slate-700">Correo electrónico</label><input wire:model.live.debounce.350ms="email" id="email" type="email" autocomplete="email" placeholder="nombre@correo.com" class="mt-1 w-full rounded-xl border px-4 py-2.5 outline-none transition @error('email') border-red-400 bg-red-50 @else border-slate-200 focus:border-brand-blue focus:ring-4 focus:ring-blue-100 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('email') {{ $message }} @enderror</p></div>
            <div><label for="password" class="text-sm font-semibold text-slate-700">Contraseña</label><input wire:model.live.debounce.350ms="password" id="password" type="password" autocomplete="current-password" class="mt-1 w-full rounded-xl border px-4 py-2.5 outline-none transition @error('password') border-red-400 bg-red-50 @else border-slate-200 focus:border-brand-blue focus:ring-4 focus:ring-blue-100 @enderror"><p class="mt-0.5 min-h-5 text-sm text-brand-red">@error('password') {{ $message }} @enderror</p></div>
            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600"><input wire:model="remember" type="checkbox" class="size-4 rounded border-slate-300 text-brand-blue focus:ring-brand-blue"> Recordarme</label>
            <button class="w-full rounded-xl bg-brand-red px-5 py-3 font-semibold text-white shadow-md shadow-red-200 transition hover:-translate-y-0.5 hover:bg-brand-red-dark" wire:loading.attr="disabled" wire:target="login"><span wire:loading.remove wire:target="login">Ingresar</span><span wire:loading wire:target="login">Ingresando…</span></button>
        </form><p class="mt-4 text-center text-sm text-slate-600">¿Aún no tienes cuenta? <a href="{{ route('register') }}" class="font-semibold text-brand-blue hover:underline" wire:navigate>Regístrate</a></p></div></section>
</div>
