<section class="mx-auto max-w-lg rounded-3xl border border-blue-100 bg-white p-6 shadow-sm sm:p-8">
    <p class="text-xs font-semibold uppercase tracking-[.18em] text-brand-orange">Bienvenido al equipo</p>
    <h1 class="mt-2 text-3xl font-bold">Activa tu cuenta</h1>
    @if ($invitacion)
        <p class="mt-3 text-sm leading-6 text-slate-600">Hola, {{ $invitacion['name'] }}. Te invitamos como <strong>{{ $invitacion['rol'] }}</strong>. Tu correo de acceso es {{ $invitacion['email'] }}.</p>
        @auth
            <p class="mt-4 rounded-xl bg-orange-50 p-3 text-sm text-orange-800">Hay otra sesión abierta. Activa la cuenta desde una ventana privada o cierra sesión antes de continuar.</p>
        @else
            <form wire:submit="activar" novalidate class="mt-5 space-y-4">
                <x-admin-field label="Nueva contraseña *" name="password" hint="De 12 a 72 caracteres, con mayúscula, minúscula, número y símbolo."><input id="password" type="password" wire:model.blur="password" autocomplete="new-password" maxlength="72" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5"></x-admin-field>
                <x-admin-field label="Repite la contraseña *" name="passwordConfirmation"><input id="passwordConfirmation" type="password" wire:model.blur="passwordConfirmation" autocomplete="new-password" maxlength="72" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2.5"></x-admin-field>
                @error('enlace')<p role="alert" class="text-sm text-brand-red">{{ $message }}</p>@enderror
                <button wire:loading.attr="disabled" type="submit" class="w-full rounded-xl bg-brand-red px-5 py-3 font-semibold text-white disabled:opacity-50"><span wire:loading.remove wire:target="activar">Activar cuenta</span><span wire:loading wire:target="activar">Activando…</span></button>
            </form>
        @endauth
    @else
        <p class="mt-5 rounded-xl bg-orange-50 p-4 text-sm leading-6 text-orange-800">Esta invitación no es válida, venció o ya fue utilizada. Solicita una nueva al administrador.</p>
    @endif
    <a href="{{ route('login') }}" class="mt-5 inline-block text-sm font-semibold text-brand-blue">Volver a iniciar sesión</a>
</section>
