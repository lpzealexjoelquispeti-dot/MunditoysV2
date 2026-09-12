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
    <div class="bg-gradient-to-r from-brand-blue to-blue-700 px-4 py-2 text-center text-xs font-semibold text-white">
        <span>🎉 ¡Retiro express gratuito en todas nuestras sucursales con código QR! Compra online y retira al instante.</span>
    </div>

    <!-- NAVEGACIÓN PRINCIPAL -->
    <header class="sticky top-0 z-40 border-b border-orange-100 bg-white/95 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8">
            <!-- LOGO -->
            <a href="{{ route('welcome') }}" class="flex items-center gap-2.5 text-xl sm:text-2xl font-black tracking-tight text-brand-blue">
                <span class="grid size-10 place-items-center rounded-2xl bg-brand-orange text-xl text-white shadow-sm">★</span>
                <span>Mundi<span class="text-brand-orange">Toys</span></span>
            </a>

            <!-- ENLACES DE NAVEGACIÓN -->
            <nav class="hidden md:flex items-center gap-6 text-sm font-bold text-slate-600">
                <a href="{{ route('welcome') }}" class="text-brand-orange">Inicio</a>
                <a href="{{ route('tienda.catalogo') }}" class="hover:text-brand-blue transition" wire:navigate>Catálogo de Juguetes</a>
                <a href="{{ route('tienda.seguimiento') }}" class="hover:text-brand-blue transition" wire:navigate>Seguimiento y Registro</a>
                <a href="#novedades" class="hover:text-brand-blue transition">Novedades</a>
                <a href="#sobre-nosotros" class="hover:text-brand-blue transition">Sobre Nosotros</a>
            </nav>

            <!-- ACCESO USUARIO / INVITADO -->
            <div class="flex items-center gap-3 text-sm font-bold">
                <a href="{{ route('tienda.catalogo') }}" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition sm:hidden" wire:navigate>
                <a href="{{ route('tienda.catalogo') }}" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3.5 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-200 transition md:hidden" wire:navigate>
                    <span>🧸 Catálogo</span>
                </a>

                <a href="{{ route('tienda.seguimiento') }}" class="hidden lg:inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition" wire:navigate title="Seguimiento y Registro">
                    <span>📦 Seguimiento</span>
                </a>

                <livewire:cliente.icono-carrito />

                @auth
                    <a
                        href="{{ auth()->user()->can('acceder panel') ? route('admin.dashboard') : route('perfil') }}"
                        class="inline-flex items-center gap-2 rounded-full bg-brand-blue px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition"
                        wire:navigate
                    >
                        <span>👤</span>
                        <span>{{ auth()->user()->can('acceder panel') ? 'Panel Administrativo' : 'Mi Cuenta' }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:inline">
                        @csrf
                        <button class="text-xs font-bold text-slate-400 hover:text-red-600 transition">Salir</button>
                    </form>
                    <!-- MENÚ DESPLEGABLE DE PERFIL -->
                    <div x-data="{ abierto: false }" class="relative">
                        <button
                            @click="abierto = !abierto"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-2 pr-3.5 text-xs font-bold text-slate-700 shadow-xs hover:border-slate-300 hover:bg-slate-50 transition"
                        >
                            <span class="grid size-7 place-items-center rounded-full bg-brand-blue text-white text-xs font-black">
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
                                <p class="text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ auth()->user()->email }}</p>
                            </div>

                            <div class="py-1 text-xs font-medium text-slate-700 space-y-0.5">
                                <a href="{{ route('tienda.seguimiento') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-orange-50 hover:text-brand-orange transition" wire:navigate @click="abierto = false">
                                    <span class="text-sm">📦</span>
                                    <div>
                                        <p class="font-bold leading-tight">Seguimiento y Registro</p>
                                        <p class="text-[10px] text-slate-400">Mis compras y comprobantes</p>
                                    </div>
                                </a>

                                <a href="{{ route('tienda.direcciones') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-orange-50 hover:text-brand-orange transition" wire:navigate @click="abierto = false">
                                    <span class="text-sm">📍</span>
                                    <div>
                                        <p class="font-bold leading-tight">Mis Direcciones</p>
                                        <p class="text-[10px] text-slate-400">Lugares de entrega (máx. 3)</p>
                                    </div>
                                </a>

                                <a href="{{ route('perfil') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-slate-100 hover:text-slate-900 transition" wire:navigate @click="abierto = false">
                                    <span class="text-sm">👤</span>
                                    <div>
                                        <p class="font-bold leading-tight">Mi Perfil</p>
                                        <p class="text-[10px] text-slate-400">Datos personales</p>
                                    </div>
                                </a>

                                @if (auth()->user()->can('acceder panel'))
                                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 rounded-xl px-3 py-2 hover:bg-blue-50 hover:text-brand-blue transition" wire:navigate @click="abierto = false">
                                        <span class="text-sm">⚙️</span>
                                        <div>
                                            <p class="font-bold leading-tight">Panel Administrativo</p>
                                            <p class="text-[10px] text-slate-400">Gestión de la tienda</p>
                                        </div>
                                    </a>
                                @endif
                            </div>

                            <div class="pt-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition text-left">
                                        <span class="text-sm">🚪</span>
                                        <span>Cerrar Sesión</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('tienda.catalogo') }}" class="text-slate-600 hover:text-brand-orange font-bold text-xs transition" wire:navigate>
                        Explorar como invitado
                    </a>
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-brand-blue transition" wire:navigate>
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-brand-blue font-bold text-xs transition" wire:navigate>
                        Ingresar
                    </a>
                    <a href="{{ route('register') }}" class="rounded-full bg-brand-orange px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-orange-600 transition" wire:navigate>
                        Crear cuenta
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- SECCIÓN HERO CON BUSCADOR PRINCIPAL Y CARRUSEL -->
        <section class="relative bg-gradient-to-b from-orange-50/60 via-white to-brand-surface pt-8 pb-16">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <!-- BUSCADOR PRINCIPAL DESTACADO -->
                <div class="mx-auto max-w-3xl text-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-orange/10 px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-brand-orange">
                        <span>🧸</span> La juguetería más divertida de Bolivia
                    </span>
                    <h1 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 leading-[1.08]">
                        Encuentra el <span class="text-brand-orange">juguete perfecto</span> para cada sonrisa
                    </h1>
                    <p class="mt-3 text-base sm:text-lg text-slate-600">
                        Explora nuestro catálogo con cientos de opciones, modelos 3D interactivos y retiro express en sucursal.
                    </p>

                    <!-- CAJA DEL BUSCADOR QUE DIRIGE AL CATÁLOGO -->
                    <form action="{{ route('tienda.catalogo') }}" method="GET" class="mt-8">
                        <div class="relative flex items-center rounded-3xl bg-white p-2 shadow-xl shadow-orange-500/10 border-2 border-brand-orange/40 focus-within:border-brand-orange transition">
                            <div class="pl-4 text-xl text-slate-400">🔍</div>
                            <input
                                type="search"
                                name="buscar"
                                placeholder="¿Qué juguete estás buscando? (ej. Lego, Robot, Dinosaurio, Muñeca...)"
                                class="w-full border-none bg-transparent px-3 py-3 text-sm sm:text-base text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-0"
                                required
                            >
                            <button
                                type="submit"
                                class="rounded-2xl bg-brand-orange px-6 py-3 text-sm font-black text-white shadow-md hover:bg-orange-600 transition shrink-0"
                            >
                                Buscar Juguetes
                            </button>
                        </div>

                        <!-- SUGERENCIAS RÁPIDAS -->
                        <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-slate-500">
                            <span class="font-bold text-slate-400">Sugerencias populares:</span>
                            @foreach(['Lego', 'Educativos', 'Robots', 'Peluches', 'Bloques', 'Juegos de Mesa'] as $etiqueta)
                                <a
                                    href="{{ route('tienda.catalogo', ['buscar' => $etiqueta]) }}"
                                    class="rounded-full bg-white px-3 py-1 font-semibold text-slate-600 shadow-sm border border-slate-200 hover:border-brand-orange hover:text-brand-orange transition"
                                >
                                    {{ $etiqueta }}
                                </a>
                            @endforeach
                        </div>
                    </form>
                </div>

                <!-- CARRUSEL VISUAL INTERACTIVO -->
                <div
                    class="mt-12 overflow-hidden rounded-[2.5rem] shadow-xl border border-orange-100 bg-white"
                    x-data="{
                        diapositivaActual: 0,
                        totalDiapositivas: 3,
                        temporizador: null,
                        siguiente() {
                            this.diapositivaActual = (this.diapositivaActual + 1) % this.totalDiapositivas;
                        },
                        anterior() {
                            this.diapositivaActual = (this.diapositivaActual - 1 + this.totalDiapositivas) % this.totalDiapositivas;
                        },
                        iniciarAutoPlay() {
                            this.temporizador = setInterval(() => this.siguiente(), 6000);
                        },
                        detenerAutoPlay() {
                            if (this.temporizador) clearInterval(this.temporizador);
                        }
                    }"
                    x-init="iniciarAutoPlay()"
                    x-on:mouseenter="detenerAutoPlay()"
                    x-on:mouseleave="iniciarAutoPlay()"
                >
                    <div class="relative min-h-[340px] sm:min-h-[400px]">
                        <!-- SLIDE 1: NOVEDADES Y CATÁLOGO -->
                        <div
                            x-show="diapositivaActual === 0"
                            x-transition:enter="transition ease-out duration-500"
                            x-transition:enter-start="opacity-0 translate-x-12"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-12"
                            class="grid items-center gap-8 p-8 sm:p-14 lg:grid-cols-2 bg-gradient-to-r from-blue-900 via-blue-800 to-brand-blue text-white"
                        >
                            <div>
                                <span class="rounded-full bg-white/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-orange-300">
                                    ★ Nueva Temporada 2026
                                </span>
                                <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                                    Descubre juguetes que inspiran grandes sueños
                                </h2>
                                <p class="mt-3 text-sm sm:text-base text-blue-100 max-w-lg">
                                    Colecciones originales de construcción, creatividad y diversión para todas las edades.
                                </p>
                                <div class="mt-6 flex flex-wrap gap-3">
                                    <a href="{{ route('tienda.catalogo') }}" class="rounded-2xl bg-brand-orange px-6 py-3 font-bold text-white shadow-lg shadow-orange-900/40 hover:bg-orange-600 transition" wire:navigate>
                                        Explorar Catálogo →
                                    </a>
                                </div>
                            </div>
                            <div class="flex justify-center">
                                <div class="relative grid size-52 sm:size-64 place-items-center rounded-3xl bg-white/10 backdrop-blur-md shadow-2xl border border-white/20 text-8xl sm:text-9xl animate-bounce duration-1000">
                                    🚀
                                    <span class="absolute -bottom-4 -right-4 rounded-2xl bg-brand-orange px-4 py-1.5 text-xs font-black text-white shadow-md">
                                        +500 Novedades
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SLIDE 2: GEMELOS DIGITALES 3D -->
                        <div
                            x-show="diapositivaActual === 1"
                            x-transition:enter="transition ease-out duration-500"
                            x-transition:enter-start="opacity-0 translate-x-12"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-12"
                            class="grid items-center gap-8 p-8 sm:p-14 lg:grid-cols-2 bg-gradient-to-r from-amber-600 via-orange-600 to-brand-orange text-white"
                        >
                            <div>
                                <span class="rounded-full bg-white/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-amber-200">
                                    🧊 Tecnología 3D Interactiva
                                </span>
                                <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                                    Mira cada juguete en 3D antes de comprar
                                </h2>
                                <p class="mt-3 text-sm sm:text-base text-orange-100 max-w-lg">
                                    Gira, amplía y explora los detalles tridimensionales en tiempo real desde tu navegador.
                                </p>
                                <div class="mt-6 flex flex-wrap gap-3">
                                    <a href="{{ route('tienda.catalogo', ['solo_3d' => 1]) }}" class="rounded-2xl bg-slate-900 px-6 py-3 font-bold text-white shadow-lg hover:bg-black transition" wire:navigate>
                                        Ver Juguetes 3D 🧊
                                    </a>
                                </div>
                            </div>
                            <div class="flex justify-center">
                                <div class="relative grid size-52 sm:size-64 place-items-center rounded-3xl bg-white/10 backdrop-blur-md shadow-2xl border border-white/20 text-8xl sm:text-9xl">
                                    🧊
                                    <span class="absolute -top-3 -left-3 rounded-2xl bg-blue-600 px-3.5 py-1 text-xs font-black text-white shadow-md">
                                        Fotogrametría IA
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SLIDE 3: RECOJO EN SUCURSAL CON QR -->
                        <div
                            x-show="diapositivaActual === 2"
                            x-transition:enter="transition ease-out duration-500"
                            x-transition:enter-start="opacity-0 translate-x-12"
                            x-transition:enter-end="opacity-100 translate-x-0"
                            x-transition:leave="transition ease-in duration-300"
                            x-transition:leave-start="opacity-100 translate-x-0"
                            x-transition:leave-end="opacity-0 -translate-x-12"
                            class="grid items-center gap-8 p-8 sm:p-14 lg:grid-cols-2 bg-gradient-to-r from-emerald-800 via-teal-700 to-emerald-600 text-white"
                        >
                            <div>
                                <span class="rounded-full bg-white/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-emerald-200">
                                    🛍️ Retiro Inmediato en Tienda
                                </span>
                                <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                                    Compra online y retira con tu ticket QR
                                </h2>
                                <p class="mt-3 text-sm sm:text-base text-emerald-100 max-w-lg">
                                    Sin costo de envío. Pasa por tu sucursal más cercana, muestra tu código QR y llévate tu pedido en segundos.
                                </p>
                                <div class="mt-6 flex flex-wrap gap-3">
                                    <a href="{{ route('tienda.catalogo') }}" class="rounded-2xl bg-white px-6 py-3 font-bold text-emerald-900 shadow-lg hover:bg-emerald-50 transition" wire:navigate>
                                        Comprar y Retirar →
                                    </a>
                                </div>
                            </div>
                            <div class="flex justify-center">
                                <div class="relative grid size-52 sm:size-64 place-items-center rounded-3xl bg-white/10 backdrop-blur-md shadow-2xl border border-white/20 text-8xl sm:text-9xl">
                                    📱
                                    <span class="absolute -bottom-3 -right-3 rounded-2xl bg-amber-500 px-3.5 py-1 text-xs font-black text-white shadow-md">
                                        QR Verificado
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- CONTROLES DEL CARRUSEL -->
                        <button
                            type="button"
                            x-on:click="anterior()"
                            class="absolute left-4 top-1/2 -translate-y-1/2 grid size-11 place-items-center rounded-full bg-white/30 backdrop-blur-md text-white hover:bg-white/50 transition"
                            aria-label="Anterior"
                        >
                            ‹
                        </button>
                        <button
                            type="button"
                            x-on:click="siguiente()"
                            class="absolute right-4 top-1/2 -translate-y-1/2 grid size-11 place-items-center rounded-full bg-white/30 backdrop-blur-md text-white hover:bg-white/50 transition"
                            aria-label="Siguiente"
                        >
                            ›
                        </button>

                        <!-- PUNTOS INDICADORES -->
                        <div class="absolute bottom-4 inset-x-0 flex justify-center gap-2">
                            <template x-for="i in totalDiapositivas" :key="i">
                                <button
                                    type="button"
                                    x-on:click="diapositivaActual = i - 1"
                                    :class="diapositivaActual === (i - 1) ? 'w-8 bg-white' : 'w-2.5 bg-white/50'"
                                    class="h-2.5 rounded-full transition-all duration-300"
                                    :aria-label="'Diapositiva ' + i"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- EXPLORAR POR CATEGORÍAS -->
        @if($categorias->isNotEmpty())
            <section class="py-12 border-y border-orange-100/80 bg-white">
                <div class="mx-auto max-w-7xl px-5 sm:px-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-brand-orange">Explora por Temática</span>
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Categorías de Juguetes</h2>
                        </div>
                        <a href="{{ route('tienda.catalogo') }}" class="text-xs font-bold text-brand-blue hover:underline" wire:navigate>
                            Ver todas →
                        </a>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        @foreach($categorias as $cat)
                            <a
                                href="{{ route('tienda.catalogo', ['categoria' => $cat->id]) }}"
                                class="group flex flex-col items-center rounded-3xl border border-slate-100 bg-orange-50/30 p-5 text-center transition hover:-translate-y-1 hover:border-brand-orange hover:bg-white hover:shadow-md"
                                wire:navigate
                            >
                                <span class="text-4xl transition group-hover:scale-110">
                                    {{ match(strtolower($cat->nombre)) {
                                        'educativos' => '🧩',
                                        'construccion', 'construcción' => '🏗️',
                                        'figuras de accion', 'figuras de acción' => '🦸',
                                        'muñecas' => '👸',
                                        'juegos de mesa' => '🎲',
                                        'peluches' => '🧸',
                                        'vehiculos', 'vehículos' => '🏎️',
                                        'arte y manualidades' => '🎨',
                                        default => '🎁'
                                    } }}
                                </span>
                                <span class="mt-3 font-bold text-xs text-slate-800 group-hover:text-brand-orange transition">
                                    {{ $cat->nombre }}
                                </span>
                                <span class="text-[10px] text-slate-400 mt-0.5">
                                    {{ $cat->productos_count }} juguetes
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        <!-- SECCIÓN: NOVEDADES Y JUGUETES MÁS RECIENTES -->
        <section id="novedades" class="py-16">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-brand-orange">
                            <span>✨</span> Recién llegados al almacén
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-black text-slate-900">Novedades y Favoritos Recientes</h2>
                        <p class="mt-1 text-sm text-slate-500">Los juguetes más nuevos listos para entrega inmediata o recojo en sucursal.</p>
                    </div>

                    <a
                        href="{{ route('tienda.catalogo') }}"
                        class="inline-flex items-center gap-1.5 rounded-2xl bg-brand-blue px-5 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-700 transition shrink-0"
                        wire:navigate
                    >
                        <span>Ver Catálogo Completo</span>
                        <span>→</span>
                    </a>
                </div>

                <!-- GRILLA DE PRODUCTOS RECIENTES -->
                <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse($productosRecientes as $prod)
                        @php
                            $imagen = $prod->imagenes->sortBy('orden')->first()?->ruta_archivo;
                            $tiene3D = $prod->modelos3D->whereNotNull('modelo_glb_path')->isNotEmpty();
                        @endphp
                        <article class="group relative flex flex-col rounded-3xl border border-slate-200/80 bg-white p-4 shadow-sm hover:shadow-xl hover:border-brand-orange/40 transition duration-300">
                            <!-- ENLACE TOTAL DE LA CARTA AL DETALLE -->
                            <a
                                href="{{ route('tienda.producto.detalle', ['codigo' => $prod->codigo_producto]) }}"
                                class="absolute inset-0 z-10"
                                wire:navigate
                                aria-label="Ver detalles de {{ $prod->nombre }}"
                            ></a>

                            <!-- IMAGEN DEL PRODUCTO (SOLO LA PRIMERA) -->
                            <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-slate-100">
                                @if($imagen)
                                    <img
                                        src="{{ asset('storage/' . ltrim($imagen, '/')) }}"
                                        alt="{{ $prod->nombre }}"
                                        class="size-full object-cover group-hover:scale-105 transition duration-500"
                                        loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';"
                                    >
                                    <div style="display:none;" class="size-full place-items-center text-6xl">🧸</div>
                                @else
                                    <div class="grid size-full place-items-center text-6xl">🧸</div>
                                @endif

                                <!-- BADGES SOBRE IMAGEN -->
                                @if($tiene3D)
                                    <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 rounded-full bg-slate-900/80 px-2.5 py-0.5 text-[10px] font-bold text-white backdrop-blur-sm pointer-events-none">
                                        <span>🧊</span> 3D
                                    </span>
                                @endif

                                @if($prod->edad_recomendada)
                                    <span class="absolute bottom-2.5 right-2.5 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-bold text-slate-700 shadow-sm backdrop-blur-sm pointer-events-none">
                                        +{{ $prod->edad_recomendada }} años
                                    </span>
                                @endif
                            </div>

                            <!-- INFORMACIÓN DEL JUGUETE -->
                            <div class="mt-4 flex flex-1 flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between text-[11px] font-medium text-slate-400">
                                        <span>{{ $prod->categoria?->nombre ?? 'General' }}</span>
                                        <span>{{ $prod->marca?->nombre ?? '' }}</span>
                                    </div>
                                    <h3 class="mt-1 text-sm font-bold text-slate-900 line-clamp-2 group-hover:text-brand-orange transition">
                                        {{ $prod->nombre }}
                                    </h3>
                                </div>

                                <!-- PRECIO Y DETALLE -->
                                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                                    <div>
                                        <span class="text-[10px] text-slate-400 block">Precio</span>
                                        <span class="text-base font-black text-brand-orange">
                                            Bs. {{ number_format((float) ($prod->precio_venta ?? 0), 2) }}
                                        </span>
                                    </div>

                                    <div class="relative z-20">
                                        <span class="inline-flex items-center rounded-xl bg-orange-50 px-3 py-1.5 text-xs font-bold text-brand-orange group-hover:bg-brand-orange group-hover:text-white transition shadow-sm">
                                            Ver Info →
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-3xl border border-slate-200 bg-white p-12 text-center text-slate-400">
                            <span class="text-4xl">🧸</span>
                            <p class="mt-2 text-sm">Pronto tendremos juguetes disponibles en nuestra vitrina.</p>
                            <p class="mt-2 text-sm font-semibold text-slate-500">Aún no hay juguetes publicados</p>
                            <p class="text-xs text-slate-400 mt-1">Pronto tendremos novedades en nuestra vitrina.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- SECCIÓN: SOBRE NOSOTROS Y VENTAJAS -->
        <section id="sobre-nosotros" class="py-16 bg-gradient-to-b from-white via-orange-50/30 to-white border-t border-orange-100">
            <div class="mx-auto max-w-7xl px-5 sm:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-orange">Nuestra Misión</span>
                        <h2 class="mt-2 text-3xl sm:text-4xl font-black text-slate-900">
                            Transformamos el juego en aprendizaje, imaginación y alegría
                        </h2>
                        <p class="mt-4 text-sm sm:text-base text-slate-600 leading-relaxed">
                            En <strong>MundiToys</strong> creemos que cada juguete es la puerta de entrada a un universo de descubrimientos. Nacimos en Bolivia con el propósito de conectar a las familias con juguetes seguros, educativos e interactivos de la más alta calidad internacional.
                        </p>
                        <p class="mt-3 text-sm sm:text-base text-slate-600 leading-relaxed">
                            Combinamos la calidez de nuestras <strong>sucursales físicas</strong> con la vanguardia de la tecnología digital: modelos 3D interactivos, compras en línea seguras y retiro express en mostrador con escaneo de código QR.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-4">
                            <div class="rounded-2xl border border-blue-100 bg-white p-4 shadow-sm">
                                <div class="text-2xl">🏢</div>
                                <div class="mt-2 font-bold text-sm text-slate-900">Sucursales Físicas</div>
                                <p class="text-xs text-slate-500">Presencia en las principales ciudades de Bolivia.</p>
                            </div>

                            <div class="rounded-2xl border border-orange-100 bg-white p-4 shadow-sm">
                                <div class="text-2xl">🧊</div>
                                <div class="mt-2 font-bold text-sm text-slate-900">Gemelos 3D</div>
                                <p class="text-xs text-slate-500">Visualización interactiva tridimensional de productos.</p>
                            </div>
                        </div>
                    </div>

                    <!-- 4 BENEFICIOS DESTACADOS -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                            <div class="grid size-12 place-items-center rounded-2xl bg-brand-orange/10 text-2xl text-brand-orange">
                                🛍️
                            </div>
                            <h3 class="mt-4 font-bold text-base text-slate-900">Recojo en Sucursal</h3>
                            <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                                Compra por la web y retira en tienda física sin costo adicional con tu código QR verificado al instante.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                            <div class="grid size-12 place-items-center rounded-2xl bg-blue-50 text-2xl text-brand-blue">
                                🛡️
                            </div>
                            <h3 class="mt-4 font-bold text-base text-slate-900">Calidad Certificada</h3>
                            <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                                Lotes de inventario trazables mediante FIFO que garantizan juguetes nuevos, originales y seguros.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                            <div class="grid size-12 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-600">
                                💳
                            </div>
                            <h3 class="mt-4 font-bold text-base text-slate-900">Pagos Seguros</h3>
                            <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                                Transferencia QR bancaria Simple, tarjetas de crédito/débito con Stripe o abono al retirar en tienda.
                            </p>
                        </div>

                        <div class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                            <div class="grid size-12 place-items-center rounded-2xl bg-purple-50 text-2xl text-purple-600">
                                🤖
                            </div>
                            <h3 class="mt-4 font-bold text-base text-slate-900">Asistencia Inteligente</h3>
                            <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                                Recomendaciones precisas por edad, gustos e intereses para acertar en cada regalo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- BANNER FINAL LLAMADO A LA ACCIÓN -->
        <section class="py-14 bg-gradient-to-r from-brand-blue via-blue-700 to-brand-blue text-white text-center">
            <div class="mx-auto max-w-4xl px-5">
                <span class="rounded-full bg-white/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-orange-200">
                    ★ ¡Comienza tu aventura ahora!
                </span>
                <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black">
                    ¿Listo para encontrar el juguete perfecto?
                </h2>
                <p class="mt-3 text-sm sm:text-base text-blue-100 max-w-xl mx-auto">
                    Entra a nuestro catálogo interactivo, utiliza los filtros por edad y precio, y explora los modelos en 3D.
                </p>
                <div class="mt-8 flex justify-center">
                    <a
                        href="{{ route('tienda.catalogo') }}"
                        class="rounded-full bg-brand-orange px-8 py-3.5 text-base font-black text-white shadow-xl shadow-orange-950/40 hover:bg-orange-600 transition hover:scale-105"
                        wire:navigate
                    >
                        Explorar Catálogo con Filtros →
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="border-t border-slate-200 bg-slate-900 text-slate-300 py-12">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <div class="flex items-center gap-2 text-xl font-bold text-white">
                        <span class="grid size-8 place-items-center rounded-xl bg-brand-orange text-lg text-white">★</span>
                        MundiToys
                    </div>
                    <p class="mt-3 text-xs text-slate-400 leading-relaxed">
                        Juguetería interactiva líder en Bolivia. Juguetes pensados para imaginar, aprender y crear historias inolvidables.
                    </p>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Navegación</div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-400">
                        <li><a href="{{ route('welcome') }}" class="hover:text-white transition">Inicio</a></li>
                        <li><a href="{{ route('tienda.catalogo') }}" class="hover:text-white transition" wire:navigate>Catálogo de Juguetes</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white transition" wire:navigate>Ingresar a mi cuenta</a></li>
                        <li><a href="{{ route('register') }}" class="hover:text-white transition" wire:navigate>Registrarme</a></li>
                    </ul>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Sucursales y Retiro</div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-400">
                        <li>📍 La Paz: Sucursal Central y Calacoto</li>
                        <li>📍 Cochabamba: Sucursal América</li>
                        <li>📍 Santa Cruz: Sucursal Equipetrol</li>
                        <li class="text-brand-orange font-bold">Horario: Lun - Sáb 09:00 a 19:30</li>
                    </ul>
                </div>

                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-white">Contacto y Ayuda</div>
                    <ul class="mt-3 space-y-2 text-xs text-slate-400">
                        <li>📞 (+591) 4-4123456</li>
                        <li>📱 WhatsApp: (+591) 70000000</li>
                        <li>✉️ soporte@munditoys.com</li>
                        <li>🛡️ Comprobantes y Facturación Oficial</li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 border-t border-slate-800 pt-6 text-center text-xs text-slate-500">
                &copy; {{ date('Y') }} MundiToys Bolivia S.R.L. Todos los derechos reservados.
            </div>
        </div>
    </footer>
</body>
</html>
