@props(['active' => null])

<div class="min-h-[calc(100vh-73px)] bg-white lg:grid lg:grid-cols-[260px_minmax(0,1fr)]" x-data="{ menuAbierto: false }">
    <div class="flex items-center justify-between bg-brand-blue px-5 py-4 text-white lg:hidden">
        <span class="font-semibold">Panel administrativo</span>
        <button type="button" class="rounded-lg bg-white/15 px-3 py-2" x-on:click="menuAbierto = ! menuAbierto" aria-label="Abrir menú">☰</button>
    </div>

    <aside class="bg-brand-blue p-5 text-white lg:block lg:p-6" x-show="menuAbierto" x-transition x-cloak x-on:resize.window="menuAbierto = window.innerWidth >= 1024" x-init="menuAbierto = window.innerWidth >= 1024">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 text-xl font-bold">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-orange text-white shadow-sm">★</span>
            MundiToys
        </a>

        <p class="mt-9 text-xs font-semibold uppercase tracking-[.18em] text-blue-200">Administración</p>
        <nav class="mt-4 flex flex-col gap-1.5">
            @foreach ([['productos', '🧸', 'Productos', 'ver productos'], ['modelos-3d', '🧊', 'Generar 3D', 'ver productos'], ['categorias', '🧩', 'Categorías', 'administrar accesos'], ['marcas', '🏷️', 'Marcas', 'administrar accesos'], ['sucursales', '📍', 'Sucursales', 'administrar accesos'], ['almacenes', '🏬', 'Almacenes', 'administrar accesos'], ['proveedores', '🚚', 'Proveedores', 'administrar accesos'], ['inventario', '📦', 'Inventario', 'gestionar inventario'], ['usuarios', '👥', 'Usuarios y accesos', 'administrar accesos'], ['reportes', '📊', 'Reportes', 'administrar accesos']] as [$clave, $icono, $modulo, $permiso])
                @can($permiso)
                <a href="{{ route('admin.'.$clave) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 font-semibold transition', 'bg-white text-brand-blue shadow-sm' => $active === $clave, 'text-blue-50 hover:bg-white/10' => $active !== $clave]) wire:navigate wire:key="nav-{{ $clave }}" @if($active === $clave) aria-current="page" @endif><span>{{ $icono }}</span>{{ $modulo }}</a>
                @endcan
            @endforeach
            @can('administrar accesos')
            @foreach ([['🧾', 'Ventas'], ['🎟️', 'Promociones']] as [$icono, $modulo])
                <button type="button" disabled class="flex items-center gap-3 rounded-xl px-4 py-3 text-left text-blue-100/70" wire:key="modulo-{{ $modulo }}"><span>{{ $icono }}</span>{{ $modulo }}<small class="ms-auto text-[10px]">Pronto</small></button>
            @endforeach
            @endcan
            <a href="{{ route('perfil') }}" wire:navigate class="mt-3 rounded-xl border-t border-white/20 px-4 py-3 text-sm text-blue-50">Mi perfil</a>
        </nav>
    </aside>

    <section class="min-w-0 bg-brand-surface p-5 sm:p-7 lg:p-9">
        {{ $slot }}
    </section>
</div>
