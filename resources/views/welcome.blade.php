<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MundiToys · Juguetes que inspiran sonrisas e imaginación</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-brand-surface text-brand-ink selection:bg-brand-orange selection:text-white">

    <!-- BARRA SUPERIOR DE PROMOCIÓN -->
    <div class="bg-brand-blue px-4 py-2 text-center text-sm text-white">
        Retiro gratis en sucursal con tu código QR
    </div>

    <!-- NAVEGACIÓN PRINCIPAL -->
    <header class="sticky top-0 z-40 border-b border-orange-100 bg-white/95 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <!-- LOGO -->
            <a href="{{ route('welcome') }}" class="shrink-0">
                <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="MundiToys" class="size-14 sm:size-16">
            </a>

            <!-- ENLACES DE NAVEGACIÓN -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-slate-600">
                <a href="{{ route('tienda.catalogo') }}" class="hover:text-brand-blue transition" wire:navigate>Catálogo</a>
                <a href="{{ route('tienda.seguimiento') }}" class="hover:text-brand-blue transition" wire:navigate>Mis pedidos</a>
                <a href="#novedades" class="hover:text-brand-blue transition">Novedades</a>
                <a href="#sobre-nosotros" class="hover:text-brand-blue transition">Nosotros</a>
            </nav>

            <!-- ACCESO USUARIO / INVITADO -->
            <div class="flex items-center gap-3 text-sm font-semibold">
                <a href="{{ route('tienda.catalogo') }}" class="grid size-10 place-items-center rounded-full bg-slate-100 text-slate-700 transition hover:bg-slate-200 md:hidden" wire:navigate aria-label="Catálogo">
                    <x-lucide-toy-brick class="text-lg" />
                </a>

                <livewire:cliente.icono-carrito />

                @auth
                    <livewire:cliente.icono-notificaciones />
                    <!-- MENÚ DESPLEGABLE DE PERFIL -->
                    <div x-data="{ abierto: false }" class="relative">
                        <button
                            @click="abierto = !abierto"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-2 pr-3.5 text-xs font-semibold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 transition"
                        >
                            <span class="grid size-7 place-items-center rounded-full bg-brand-blue text-white text-xs font-bold">
                                {{ mb_substr(auth()->user()->name, 0, 1) }}
                            </span>
                            <span class="max-w-[120px] truncate hidden sm:inline">{{ auth()->user()->name }}</span>
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
                                <p class="text-xs font-semibold text-slate-900 truncate">{{ auth()->user()->name }}</p>
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
                                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition text-left">
                                        <span class="text-sm"><x-lucide-log-out /></span>
                                        <span>Cerrar sesión</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 transition hover:text-brand-blue" wire:navigate>
                        Ingresar
                    </a>
                    <a href="{{ route('register') }}" class="hidden rounded-full bg-brand-orange px-4 py-2 text-white transition hover:bg-orange-600 sm:inline-flex" wire:navigate>
                        Crear cuenta
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- HERO: BUSCADOR A LA IZQUIERDA, LOGO QUE SIGUE AL MOUSE A LA DERECHA -->
        <section class="bg-brand-surface pt-10 pb-16 lg:pt-14 lg:pb-24">
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[1.05fr_1fr] lg:gap-16">
                <div>
                    <h1 class="max-w-xl text-4xl font-bold leading-[1.05] tracking-tight text-brand-ink sm:text-5xl lg:text-[3.5rem]">
                        Busca el juguete, míralo en 3D y retíralo hoy en tu sucursal.
                    </h1>
                    <p class="mt-5 max-w-md text-lg text-slate-600">
                        Pagas en línea y lo recoges con tu código QR. Sin costo de envío.
                    </p>

                    <form action="{{ route('tienda.catalogo') }}" method="GET" class="mt-8 max-w-xl">
                        <label for="buscar-hero" class="sr-only">Buscar juguetes</label>
                        <div class="flex items-center gap-2 rounded-2xl border-2 border-brand-ink bg-white p-2 shadow-[5px_5px_0_var(--color-brand-ink)] transition focus-within:translate-x-[2px] focus-within:translate-y-[2px] focus-within:shadow-[3px_3px_0_var(--color-brand-ink)]">
                            <svg class="ml-3 size-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                            <input
                                id="buscar-hero"
                                type="search"
                                name="buscar"
                                placeholder="Lego, dinosaurio, Iron Man…"
                                class="w-full border-none bg-transparent px-2 py-3 text-base text-brand-ink placeholder-slate-400 focus:shadow-none focus:ring-0 focus:outline-none"
                                required
                            >
                            <button type="submit" class="shrink-0 rounded-xl bg-brand-orange px-6 py-3 text-sm font-bold text-white transition hover:bg-orange-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-blue">
                                Buscar
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold">
                        <a href="{{ route('tienda.catalogo') }}" class="text-brand-blue underline decoration-2 underline-offset-4 hover:text-brand-blue-dark" wire:navigate>Ver todo el catálogo</a>
                        <a href="{{ route('tienda.seguimiento') }}" class="text-slate-600 underline decoration-slate-300 decoration-2 underline-offset-4 hover:text-brand-ink" wire:navigate>Seguir mi pedido</a>
                    </div>
                </div>

                <!-- PANEL AZUL CON EL LOGO COMO STICKER QUE SE INCLINA HACIA EL CURSOR -->
                <div
                    class="relative"
                    x-data="{
                        activo: true,
                        init() {
                            if (matchMedia('(prefers-reduced-motion: reduce)').matches || matchMedia('(hover: none)').matches) return;
                            const sticker = this.$refs.sticker;
                            const brillo = this.$refs.brillo;
                            const objetivo = { rx: 0, ry: 0 };
                            const actual = { rx: 0, ry: 0 };
                            this.alMover = (evento) => {
                                const caja = sticker.getBoundingClientRect();
                                const dx = (evento.clientX - (caja.left + caja.width / 2)) / innerWidth;
                                const dy = (evento.clientY - (caja.top + caja.height / 2)) / innerHeight;
                                objetivo.ry = Math.max(-1, Math.min(1, dx * 2)) * 24;
                                objetivo.rx = Math.max(-1, Math.min(1, dy * 2)) * -18;
                            };
                            window.addEventListener('pointermove', this.alMover);
                            const animar = () => {
                                if (!this.activo || !sticker.isConnected) return;
                                actual.rx += (objetivo.rx - actual.rx) * 0.08;
                                actual.ry += (objetivo.ry - actual.ry) * 0.08;
                                sticker.style.transform = `perspective(900px) rotateX(${actual.rx}deg) rotateY(${actual.ry}deg) rotate(-4deg)`;
                                sticker.style.setProperty('--sombra-x', `${12 - actual.ry * 0.5}px`);
                                sticker.style.setProperty('--sombra-y', `${16 + actual.rx * 0.5}px`);
                                brillo.style.background = `radial-gradient(circle at ${50 + actual.ry * 2}% ${35 - actual.rx * 2}%, rgb(255 255 255 / 0.55), transparent 55%)`;
                                requestAnimationFrame(animar);
                            };
                            animar();
                        },
                        destroy() {
                            this.activo = false;
                            window.removeEventListener('pointermove', this.alMover);
                        },
                    }"
                >
                    <div class="relative grid min-h-[380px] place-items-center overflow-hidden rounded-[2rem] bg-brand-blue sm:min-h-[480px]">
                        <div class="toy-dot-grid absolute inset-0 opacity-25" aria-hidden="true"></div>
                        <div class="absolute -right-16 -top-16 size-64 rounded-full bg-brand-blue-dark" aria-hidden="true"></div>

                        <div x-ref="sticker" class="relative size-64 rotate-[-4deg] will-change-transform sm:size-80 lg:size-96" style="--sombra-x: 12px; --sombra-y: 16px;">
                            <img
                                src="{{ asset('images/marca/logo-munditoys.png') }}"
                                alt="MundiToys, construyendo sueños"
                                class="size-full select-none [filter:drop-shadow(var(--sombra-x)_var(--sombra-y)_0_rgb(0_30_70_/_0.45))]"
                                draggable="false"
                            >
                            <div x-ref="brillo" class="pointer-events-none absolute inset-[4%] rounded-full mix-blend-overlay" aria-hidden="true"></div>
                        </div>
                    </div>

                    @if ($modeloEscaneado)
                        <a
                            href="{{ route('tienda.producto.detalle', ['codigo' => $productoEscaneado->codigo_producto]) }}"
                            class="relative -mt-10 ml-4 flex w-72 rotate-[-2deg] items-center gap-3 rounded-2xl border-2 border-brand-ink bg-white p-3 shadow-[5px_5px_0_var(--color-brand-ink)] transition hover:rotate-0 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-blue lg:absolute lg:-bottom-10 lg:-left-12 lg:mt-0 lg:ml-0"
                            wire:navigate
                        >
                            <model-viewer
                                src="{{ asset('storage/'.ltrim($modeloEscaneado->modelo_glb_path, '/')) }}"
                                alt="Modelo 3D de {{ $productoEscaneado->nombre }}"
                                auto-rotate
                                rotation-per-second="30deg"
                                disable-zoom
                                interaction-prompt="none"
                                loading="lazy"
                                shadow-intensity="1"
                                class="size-24 shrink-0 rounded-xl bg-brand-orange-soft"
                            ></model-viewer>
                            <span class="min-w-0">
                                <span class="block truncate font-bold text-brand-ink">{{ $productoEscaneado->nombre }}</span>
                                <span class="block text-sm font-semibold text-brand-orange">Bs. {{ number_format((float) $productoEscaneado->precio_venta, 2) }}</span>
                                <span class="mt-1 block text-xs text-slate-500">Gíralo en 3D antes de comprarlo</span>
                            </span>
                        </a>
                    @endif
                </div>
            </div>
        </section>

        <!-- CATEGORÍAS -->
        @if($categorias->isNotEmpty())
            <section class="border-y border-orange-100/80 bg-white py-12">
                <div class="mx-auto max-w-7xl px-5 sm:px-8">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Categorías</h2>
                        <a href="{{ route('tienda.catalogo') }}" class="text-sm font-semibold text-brand-blue hover:underline" wire:navigate>Ver todas</a>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach($categorias as $cat)
                            <a
                                href="{{ route('tienda.catalogo', ['categoria' => $cat->id]) }}"
                                class="group flex flex-col items-center rounded-3xl border border-slate-100 bg-orange-50/30 p-5 text-center transition hover:-translate-y-1 hover:border-brand-orange hover:bg-white hover:shadow-md"
                                wire:navigate
                            >
                                <span class="text-4xl text-brand-orange transition group-hover:scale-110">
                                    <x-dynamic-component :component="'lucide-'.match (mb_strtolower($cat->nombre)) {
                                        'educativos' => 'graduation-cap',
                                        'construccion', 'construcción' => 'blocks',
                                        'acción y aventuras', 'figuras de accion', 'figuras de acción' => 'swords',
                                        'muñecas', 'muñecas y figuras' => 'person-standing',
                                        'primera infancia' => 'baby',
                                        'juegos de mesa' => 'dices',
                                        'peluches' => 'heart',
                                        'vehiculos', 'vehículos' => 'car',
                                        'arte y manualidades' => 'palette',
                                        default => 'gift',
                                    }" />
                                </span>
                                <span class="mt-3 text-sm font-semibold text-slate-800 transition group-hover:text-brand-orange">{{ $cat->nombre }}</span>
                                <span class="mt-0.5 text-xs text-slate-400">{{ $cat->productos_count }} juguetes</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- NOVEDADES -->
        <section id="novedades" class="py-16">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="flex items-end justify-between gap-4">
                    <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Novedades</h2>
                    <a href="{{ route('tienda.catalogo') }}" class="text-sm font-semibold text-brand-blue hover:underline" wire:navigate>Ver catálogo</a>
                </div>

                <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse($productosRecientes as $prod)
                        @php
                            $imagen = $prod->imagenes->sortBy('orden')->first()?->ruta_archivo;
                            $tiene3D = $prod->modelos3D->whereNotNull('modelo_glb_path')->isNotEmpty();
                        @endphp
                        <a
                            href="{{ route('tienda.producto.detalle', ['codigo' => $prod->codigo_producto]) }}"
                            wire:navigate
                            class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-3 shadow-sm transition duration-300 hover:border-brand-orange/40 hover:shadow-lg"
                        >
                            <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-100">
                                @if($imagen)
                                    <img
                                        src="{{ asset('storage/' . ltrim($imagen, '/')) }}"
                                        alt="{{ $prod->nombre }}"
                                        class="size-full object-cover transition duration-500 group-hover:scale-105"
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                    >
                                    <div style="display:none;" class="size-full place-items-center text-6xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                                @else
                                    <div class="grid size-full place-items-center text-6xl"><x-lucide-toy-brick class="text-slate-300" /></div>
                                @endif

                                @if($tiene3D)
                                    <span class="absolute left-2.5 top-2.5 inline-flex items-center gap-1 rounded-full bg-slate-900/80 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur-sm">
                                        <x-lucide-box /> 3D
                                    </span>
                                @endif

                                @if($prod->edad_recomendada)
                                    <span class="absolute bottom-2.5 right-2.5 rounded-full bg-white/90 px-2 py-0.5 text-xs font-semibold text-slate-700 backdrop-blur-sm">
                                        +{{ $prod->edad_recomendada }} años
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-1 flex-col px-1 pb-1 pt-3">
                                <p class="text-xs text-slate-500">{{ $prod->categoria?->nombre ?? 'Juguete' }}</p>
                                <h3 class="mt-0.5 line-clamp-2 font-semibold text-slate-900 transition group-hover:text-brand-orange">{{ $prod->nombre }}</h3>
                                <p class="mt-auto pt-3 text-lg font-bold text-brand-orange">Bs. {{ number_format((float) ($prod->precio_venta ?? 0), 2) }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="col-span-full rounded-3xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center text-slate-500">
                            <x-lucide-toy-brick class="mx-auto text-4xl text-slate-300" />
                            <p class="mt-2 text-sm">Pronto habrá juguetes nuevos</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- SOBRE NOSOTROS -->
        <section id="sobre-nosotros" class="border-t border-orange-100 bg-white py-16">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900 sm:text-3xl">Sobre Nosotros</h2>
                    <p class="mt-3 max-w-lg text-lg text-slate-600">Juguetería boliviana. Compras en línea, ves el juguete en 3D y lo recoges en tu sucursal.</p>
                </div>

                <ul class="grid gap-3 sm:grid-cols-3">
                    <li class="rounded-3xl bg-orange-50 p-5">
                        <x-lucide-qr-code class="text-2xl text-brand-orange" />
                        <p class="mt-3 font-semibold text-slate-900">Retiro con QR</p>
                    </li>
                    <li class="rounded-3xl bg-blue-50 p-5">
                        <x-lucide-rotate-3d class="text-2xl text-brand-blue" />
                        <p class="mt-3 font-semibold text-slate-900">Juguetes en 3D</p>
                    </li>
                    <li class="rounded-3xl bg-emerald-50 p-5">
                        <x-lucide-credit-card class="text-2xl text-emerald-600" />
                        <p class="mt-3 font-semibold text-slate-900">Pago seguro</p>
                    </li>
                </ul>
            </div>
        </section>
    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="border-t border-slate-200 bg-slate-900 text-slate-300 py-12">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2 text-xl font-semibold text-white">
                        <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="" class="size-10">
                        MundiToys
                    </div>
                    <p class="mt-3 text-sm text-slate-400">Construyendo sueños</p>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">Tienda</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-400">
                        <li><a href="{{ route('tienda.catalogo') }}" class="hover:text-white transition" wire:navigate>Catálogo</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white transition" wire:navigate>Ingresar</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white transition" wire:navigate>Crear cuenta</a></li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">Sucursales</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-400">
                        <li>La Paz: Central y Calacoto</li>
                        <li>Cochabamba: América</li>
                        <li>Santa Cruz: Equipetrol</li>
                        <li>Lun a sáb, 09:00 a 19:30</li>
                    </ul>
                </div>

                <div>
                    <p class="text-sm font-semibold text-white">Contacto</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-400">
                        <li><x-lucide-phone /> (+591) 4-4123456</li>
                        <li><x-lucide-message-circle /> (+591) 70000000</li>
                        <li><x-lucide-mail /> soporte@munditoys.com</li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 border-t border-slate-800 pt-6 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} MundiToys Bolivia S.R.L.
            </div>
        </div>
    </footer>
</body>
</html>
