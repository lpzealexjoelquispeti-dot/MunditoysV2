<x-admin-shell>
    <p class="text-xs font-semibold uppercase tracking-[.18em] text-brand-orange">Tu espacio de trabajo</p>
    <h1 class="mt-2 text-3xl font-bold text-brand-ink">Bienvenido, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-sm text-slate-600">Estos son los módulos disponibles para tu cuenta.</p>
    <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['productos','🧸','Productos','ver productos','Consulta el catálogo de juguetes y sus imágenes.'],
            ['inventario','📦','Inventario','gestionar inventario','Gestiona ingresos por lotes y existencias.'],
            ['usuarios','👥','Usuarios y accesos','administrar accesos','Invita empleados, controla roles y revisa la auditoría.'],
            ['categorias','🧩','Categorías','administrar accesos','Organiza las categorías del catálogo.'],
            ['marcas','🏷️','Marcas','administrar accesos','Administra las marcas de tus juguetes.'],
            ['sucursales','📍','Sucursales','administrar accesos','Gestiona las ubicaciones de MundiToys.'],
            ['almacenes','🏬','Almacenes','administrar accesos','Organiza los almacenes por sucursal.'],
            ['proveedores','🚚','Proveedores','administrar accesos','Mantén los contactos de tus proveedores.'],
        ] as [$clave,$icono,$titulo,$permiso,$descripcion])
            @can($permiso)
                <a wire:key="inicio-{{ $clave }}" href="{{ route('admin.'.$clave) }}" wire:navigate class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="text-2xl">{{ $icono }}</span><h2 class="mt-4 text-xl font-semibold">{{ $titulo }}</h2><p class="mt-2 text-sm text-slate-600">{{ $descripcion }}</p><span class="mt-4 inline-block text-sm font-semibold text-brand-blue">Abrir módulo →</span>
                </a>
            @endcan
        @endforeach
    </div>
</x-admin-shell>
