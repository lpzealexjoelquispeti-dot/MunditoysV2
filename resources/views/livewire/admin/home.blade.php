@php
    $esVendedorPuro = auth()->user()?->hasRole('Vendedor') && ! auth()->user()?->hasRole('Administrador');

    if (auth()->user()?->can('acceder logistica')) {
        $modulos = [
            ['logistica', 'package', 'Tablero de envíos', 'acceder logistica', ['vista' => 'tablero']],
            ['logistica', 'truck', 'Historial de entregas', 'acceder logistica', ['vista' => 'completados']],
            ['logistica', 'chart-column', 'Reportes de logística', 'acceder logistica', ['vista' => 'reportes']],
        ];
    } elseif ($esVendedorPuro) {
        $modulos = [
            ['ventas', 'receipt', 'Registrar venta', 'crear ventas', []],
            ['productos', 'toy-brick', 'Catálogo', 'ver productos', []],
            ['recojo-sucursal', 'shopping-bag', 'Recoger en sucursal', 'crear ventas', []],
            ['mis-reportes', 'chart-column', 'Mis reportes', 'crear ventas', []],
        ];
    } else {
        $modulos = [
            ['ventas', 'receipt', 'Registrar venta', 'crear ventas', []],
            ['productos', 'toy-brick', 'Productos', 'ver productos', []],
            ['modelos-3d', 'box', 'Fotogrametría 3D', 'ver productos', []],
            ['inventario', 'package', 'Inventario', 'gestionar inventario', []],
            ['usuarios', 'users', 'Usuarios y accesos', 'administrar accesos', []],
            ['reportes', 'chart-column', 'Reportes', 'administrar accesos', []],
            ['categorias', 'puzzle', 'Categorías', 'administrar accesos', []],
            ['marcas', 'tag', 'Marcas', 'administrar accesos', []],
            ['sucursales', 'map-pin', 'Sucursales', 'administrar accesos', []],
            ['almacenes', 'warehouse', 'Almacenes', 'administrar accesos', []],
            ['proveedores', 'truck', 'Proveedores', 'administrar accesos', []],
        ];
    }
@endphp

<x-admin-shell>
    <h1 class="text-3xl font-bold text-brand-ink">Hola, {{ auth()->user()->name }}</h1>

    <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
        @foreach ($modulos as [$clave, $icono, $titulo, $permiso, $parametros])
            @can($permiso)
                <a wire:key="inicio-{{ $clave }}-{{ $loop->index }}" href="{{ route('admin.'.$clave, $parametros) }}" wire:navigate class="group flex flex-col gap-4 rounded-3xl border border-blue-100 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-brand-blue/40 hover:shadow-md">
                    <span class="grid size-11 place-items-center rounded-2xl bg-blue-50 text-xl text-brand-blue transition group-hover:bg-brand-blue group-hover:text-white">
                        <x-dynamic-component :component="'lucide-'.$icono" />
                    </span>
                    <span class="font-semibold text-slate-900">{{ $titulo }}</span>
                </a>
            @endcan
        @endforeach

        @if (! $esVendedorPuro && auth()->user()?->can('administrar accesos') && ! auth()->user()?->can('acceder logistica'))
            <div class="flex cursor-not-allowed flex-col gap-4 rounded-3xl border border-dashed border-slate-200 p-5 text-slate-400">
                <span class="grid size-11 place-items-center rounded-2xl bg-slate-100 text-xl"><x-lucide-ticket /></span>
                <span class="flex items-center justify-between font-semibold">Promociones <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs">Pronto</span></span>
            </div>
        @endif
    </div>
</x-admin-shell>
