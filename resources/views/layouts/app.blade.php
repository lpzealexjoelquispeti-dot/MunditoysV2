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
        <header class="sticky top-0 z-40 border-b border-orange-100 bg-white/95 backdrop-blur">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
                <!-- LOGO & ENLACES PRINCIPALES -->
                <div class="flex items-center gap-6">
                    <a href="{{ route('welcome') }}" class="shrink-0" wire:navigate>
                        <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="MundiToys" class="size-12">
                    </a>
                    <div class="hidden sm:flex items-center gap-5 text-sm font-semibold">
                        <a href="{{ route('welcome') }}" class="text-slate-600 hover:text-brand-orange transition" wire:navigate>Inicio</a>
                        <a href="{{ route('tienda.catalogo') }}" @class(['text-brand-orange font-semibold' => request()->routeIs('tienda.catalogo*'), 'text-slate-600 hover:text-brand-orange' => ! request()->routeIs('tienda.catalogo*'), 'transition']) wire:navigate>Catálogo</a>
                    </div>
                </div>

                <!-- ACCIONES: CARRITO + MENÚ PERFIL -->
                <div class="flex items-center gap-3 sm:gap-4">
                    <livewire:cliente.icono-carrito />

                    @auth
                        <livewire:cliente.icono-notificaciones />
                        <!-- MENÚ DESPLEGABLE DE PERFIL -->
                        <div x-data="{ abierto: false }" class="relative">
                            <button
                                @click="abierto = !abierto"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-2 pr-3.5 text-xs font-semibold text-brand-ink shadow-xs hover:border-slate-300 hover:bg-slate-50 transition cursor-pointer"
                            >
                                <span class="grid size-7 place-items-center rounded-full bg-brand-blue text-white text-xs font-semibold">
                                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                                </span>
                                <span class="max-w-[130px] truncate hidden sm:inline">{{ auth()->user()->name }}</span>
                                <svg class="size-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': abierto }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>

                            <div
                                x-show="abierto"
                                @click.outside="abierto = false"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-64 rounded-2xl border border-slate-100 bg-white p-2 shadow-xl ring-1 ring-black/5 z-50 divide-y divide-slate-100 text-left"
                                style="display: none;"
                            >
                                <div class="px-3 py-2.5">
                                    <p class="text-xs font-semibold text-brand-ink truncate">{{ auth()->user()->name }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</p>
                                </div>

                                <div class="py-1 text-xs font-medium text-slate-700 space-y-0.5">
                                    <a href="{{ route('tienda.seguimiento') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-orange-50 hover:text-brand-orange transition" wire:navigate @click="abierto = false">
                                        <span class="text-sm"><x-lucide-package /></span>
                                        <div>
                                            <p class="font-semibold">Mis pedidos</p>
                                        </div>
                                    </a>

                                    <a href="{{ route('tienda.direcciones') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-orange-50 hover:text-brand-orange transition" wire:navigate @click="abierto = false">
                                        <span class="text-sm"><x-lucide-map-pin /></span>
                                        <div>
                                            <p class="font-semibold">Mis direcciones</p>
                                        </div>
                                    </a>

                                    <a href="{{ route('perfil') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-slate-100 hover:text-slate-900 transition" wire:navigate @click="abierto = false">
                                        <span class="text-sm"><x-lucide-user /></span>
                                        <div>
                                            <p class="font-semibold">Mi perfil</p>
                                        </div>
                                    </a>

                                    @if (auth()->user()->can('acceder panel'))
                                        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-blue-50 hover:text-brand-blue transition" wire:navigate @click="abierto = false">
                                            <span class="text-sm"><x-lucide-settings /></span>
                                            <div>
                                                <p class="font-semibold">Panel administrativo</p>
                                            </div>
                                        </a>
                                    @endif
                                </div>

                                <div class="pt-1">
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-semibold text-brand-red hover:bg-red-50 transition text-left cursor-pointer">
                                            <span class="text-sm"><x-lucide-log-out /></span>
                                            <span>Cerrar sesión</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-2 text-xs font-semibold">
                            <a href="{{ route('login') }}" class="text-slate-600 hover:text-brand-orange px-3 py-2 transition" wire:navigate>Ingresar</a>
                            <a href="{{ route('register') }}" class="rounded-full bg-brand-orange px-4 py-2 text-white shadow-sm hover:bg-orange-600 transition" wire:navigate>Crear cuenta</a>
                        </div>
                    @endauth
                </div>
            </nav>
        </header>

        <main @class([
            'min-h-[calc(100vh-73px)]' => request()->routeIs('admin.*'),
            'mx-auto max-w-7xl px-4 py-3 sm:px-6' => request()->routeIs('tienda.checkout'),
            'mx-auto max-w-7xl p-5 sm:p-8' => ! request()->routeIs('admin.*', 'tienda.checkout'),
        ])>
            {{ $slot }}
        </main>

        @if (config('asistente.habilitado'))
            @php($canalAsistente = request()->routeIs('admin.*') && auth()->user()?->can('acceder panel') ? 'admin' : 'cliente')
            @persist('asistente-'.$canalAsistente)
                <livewire:asistente.chat :canal="$canalAsistente" :key="'asistente-'.$canalAsistente" />
            @endpersist
        @endif
    </body>
</html>
