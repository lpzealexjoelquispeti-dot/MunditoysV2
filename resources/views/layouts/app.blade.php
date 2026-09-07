<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="referrer" content="no-referrer">
        <title>{{ config('app.name', 'MundiToys') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-brand-surface text-brand-ink">
        <header class="border-b border-orange-100 bg-white/95 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
                <a href="{{ route('welcome') }}" class="flex items-center gap-2 text-xl font-bold tracking-tight text-brand-red" wire:navigate><span class="grid size-9 place-items-center rounded-xl bg-brand-orange text-lg text-white">★</span>MundiToys</a>
                <div class="flex items-center gap-3 text-sm font-bold">
                    <a href="{{ route('tienda.catalogo') }}" class="hidden text-slate-600 hover:text-brand-red sm:block" wire:navigate>Catálogo</a>
                    @auth
                        <a href="{{ auth()->user()->can('acceder panel') ? route('admin.dashboard') : route('perfil') }}" class="rounded-full bg-brand-blue px-4 py-2 text-white hover:bg-brand-blue-dark" wire:navigate>Mi espacio</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-slate-500 hover:text-brand-red">Salir</button></form>
                    @else
                        <a href="{{ route('login') }}" class="text-slate-600 hover:text-brand-red" wire:navigate>Ingresar</a>
                        <a href="{{ route('register') }}" class="rounded-full bg-brand-red px-4 py-2 text-white shadow-sm hover:bg-brand-red-dark" wire:navigate>Crear cuenta</a>
                    @endauth
                </div>
            </nav>
        </header>
        <main @class([
            'min-h-[calc(100vh-73px)]' => request()->routeIs('admin.*'),
            'mx-auto max-w-7xl p-5 sm:p-8' => ! request()->routeIs('admin.*'),
        ])>
            {{ $slot }}
        </main>
    </body>
</html>
