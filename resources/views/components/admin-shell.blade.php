@props(['active' => null])

<div class="min-h-[calc(100vh-73px)] bg-white lg:grid lg:grid-cols-[260px_minmax(0,1fr)]" x-data="{ menuAbierto: false }">
    <div class="flex items-center justify-between bg-brand-blue px-5 py-4 text-white lg:hidden">
        <span class="font-semibold">Panel administrativo</span>
        <button type="button" class="rounded-lg bg-white/15 px-3 py-2" x-on:click="menuAbierto = ! menuAbierto" aria-label="Abrir menú"><x-lucide-menu /></button>
    </div>

    <aside class="bg-brand-blue p-5 text-white lg:block lg:p-6" x-show="menuAbierto" x-transition x-cloak x-on:resize.window="menuAbierto = window.innerWidth >= 1024" x-init="menuAbierto = window.innerWidth >= 1024">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 text-xl font-semibold">
            <img src="{{ asset('images/marca/logo-munditoys.png') }}" alt="" class="size-11">
            MundiToys
        </a>

        @php
            $esVendedorPuro = auth()->user()?->hasRole('Vendedor') && ! auth()->user()?->hasRole('Administrador');
        @endphp

        <div class="mt-8"></div>

        <nav class="mt-4 flex flex-col gap-1.5">
            @can('acceder logistica')
                <a href="{{ route('admin.logistica') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'logistica', 'text-blue-50 hover:bg-white/10' => $active !== 'logistica']) wire:navigate wire:key="nav-logistica" @if($active === 'logistica') aria-current="page" @endif><span><x-lucide-truck /></span>Logística</a>
                <a href="{{ route('admin.logistica', ['vista' => 'reportes']) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'reportes-logistica', 'text-blue-50 hover:bg-white/10' => $active !== 'reportes-logistica']) wire:navigate wire:key="nav-reportes-logistica" @if($active === 'reportes-logistica') aria-current="page" @endif><span><x-lucide-chart-column /></span>Reportes</a>
                <a href="{{ route('admin.logistica', ['vista' => 'completados']) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'historial-logistica', 'text-blue-50 hover:bg-white/10' => $active !== 'historial-logistica']) wire:navigate wire:key="nav-historial-logistica" @if($active === 'historial-logistica') aria-current="page" @endif><span><x-lucide-circle-check /></span>Completados</a>
            @elseif ($esVendedorPuro)
                @can('crear ventas')
                    <a href="{{ route('admin.ventas') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'ventas', 'text-blue-50 hover:bg-white/10' => $active !== 'ventas']) wire:navigate wire:key="nav-ventas" @if($active === 'ventas') aria-current="page" @endif><span><x-lucide-receipt /></span>Registrar Venta</a>
                    <a href="{{ route('admin.recojo-sucursal') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'recojo-sucursal', 'text-blue-50 hover:bg-white/10' => $active !== 'recojo-sucursal']) wire:navigate wire:key="nav-recojo" @if($active === 'recojo-sucursal') aria-current="page" @endif><span><x-lucide-shopping-bag /></span>Recoger en sucursal</a>
                    <a href="{{ route('admin.mis-reportes') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'mis-reportes', 'text-blue-50 hover:bg-white/10' => $active !== 'mis-reportes']) wire:navigate wire:key="nav-mis-reportes" @if($active === 'mis-reportes') aria-current="page" @endif><span><x-lucide-chart-column /></span>Mis reportes</a>
                @endcan
                @can('ver productos')
                    <a href="{{ route('admin.productos') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === 'productos', 'text-blue-50 hover:bg-white/10' => $active !== 'productos']) wire:navigate wire:key="nav-productos" @if($active === 'productos') aria-current="page" @endif><span><x-lucide-toy-brick /></span>Catálogo</a>
                @endcan
            @else
                @foreach ([['ventas', 'receipt', 'Registrar Venta', 'crear ventas'], ['productos', 'toy-brick', 'Productos', 'ver productos'], ['modelos-3d', 'box', 'Fotogrametría', 'ver productos'], ['categorias', 'puzzle', 'Categorías', 'administrar accesos'], ['marcas', 'tag', 'Marcas', 'administrar accesos'], ['sucursales', 'map-pin', 'Sucursales', 'administrar accesos'], ['almacenes', 'warehouse', 'Almacenes', 'administrar accesos'], ['proveedores', 'truck', 'Proveedores', 'administrar accesos'], ['inventario', 'package', 'Inventario', 'gestionar inventario'], ['reportes', 'chart-column', 'Reportes', 'administrar accesos'], ['usuarios', 'users', 'Usuarios y accesos', 'administrar accesos']] as [$clave, $icono, $modulo, $permiso])
                    @can($permiso)
                    <a href="{{ route('admin.'.$clave) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === $clave, 'text-blue-50 hover:bg-white/10' => $active !== $clave]) wire:navigate wire:key="nav-{{ $clave }}" @if($active === $clave) aria-current="page" @endif><span><x-dynamic-component :component="'lucide-'.$icono" /></span>{{ $modulo }}</a>
                    @endcan
                @endforeach
            @endcan
            <a href="{{ route('perfil') }}" wire:navigate class="mt-3 rounded-xl border-t border-white/20 px-4 py-3 text-sm text-blue-50">Mi perfil</a>
        </nav>
    </aside>

    <section class="min-w-0 bg-brand-surface p-5 sm:p-7 lg:p-9">
        {{ $slot }}
    </section>
</div>
