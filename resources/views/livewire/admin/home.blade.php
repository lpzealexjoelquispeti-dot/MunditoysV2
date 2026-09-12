@php
    $esVendedorPuro = auth()->user()?->hasRole('Vendedor') && ! auth()->user()?->hasRole('Administrador');
@endphp

<x-admin-shell>
    <p class="text-xs font-semibold uppercase tracking-[.18em] text-brand-orange">Tu espacio de trabajo</p>
    <h1 class="mt-2 text-3xl font-bold text-brand-ink">Bienvenido, {{ auth()->user()->name }}</h1>
    <p class="mt-3 text-sm text-slate-600">
        {{ $esVendedorPuro ? 'Punto de venta de mostrador y herramientas para el personal de ventas.' : 'Estos son los módulos disponibles para tu cuenta.' }}
    </p>

    <div class="mt-7 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @can('acceder logistica')
            @foreach ([['tablero', '📦', 'Tablero de Envíos'], ['completados', '🚚', 'Historial de Entregas'], ['reportes', '📊', 'Mis Reportes de Logística']] as [$vista, $icono, $titulo])
                <a wire:key="inicio-logistica-{{ $vista }}" href="{{ route('admin.logistica', ['vista' => $vista]) }}" wire:navigate class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="text-2xl">{{ $icono }}</span><h2 class="mt-4 text-xl font-semibold">{{ $titulo }}</h2><span class="mt-4 inline-block text-sm font-semibold text-brand-blue">Abrir módulo →</span>
                </a>
            @endforeach
        @elseif ($esVendedorPuro)
            @can('crear ventas')
                <a wire:key="inicio-ventas" href="{{ route('admin.ventas') }}" wire:navigate class="rounded-3xl border-2 border-brand-orange/30 bg-gradient-to-br from-orange-50/50 to-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md hover:border-brand-orange">
                    <div class="flex items-center justify-between">
                        <span class="text-3xl">🧾</span>
                        <span class="rounded-full bg-brand-orange/10 px-2.5 py-0.5 text-xs font-bold text-brand-orange">Activo</span>
                    </div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-900">Registrar Venta (POS)</h2>
                    <p class="mt-2 text-sm text-slate-600">Punto de venta de mostrador, búsqueda rápida de existencias en todas las sucursales, cobro multimetodo y emisión de ticket.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-orange">Abrir mostrador POS →</span>
                </a>
            @endcan

            @can('ver productos')
                <a wire:key="inicio-productos" href="{{ route('admin.productos') }}" wire:navigate class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <span class="text-3xl">🧸</span>
                    <h2 class="mt-4 text-xl font-semibold text-slate-900">Catálogo de Productos</h2>
                    <p class="mt-2 text-sm text-slate-600">Consulta juguetes, especificaciones, detalles y gemelos digitales 3D interactivos.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-blue">Ver catálogo →</span>
                </a>
            @endcan

            @can('crear ventas')
                <a wire:key="inicio-recojo" href="{{ route('admin.recojo-sucursal') }}" wire:navigate class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-3xl">🛍️</span>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-brand-blue">Activo</span>
                    </div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-900">Recoger en sucursal</h2>
                    <p class="mt-2 text-sm text-slate-600">Gestión de entregas de compras online con escáner QR y emisión de ficha de entrega.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-blue">Gestionar entregas →</span>
                </a>

                <a wire:key="inicio-mis-reportes" href="{{ route('admin.mis-reportes') }}" wire:navigate class="rounded-3xl border border-blue-100 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="text-3xl">📊</span>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-bold text-brand-blue">Activo</span>
                    </div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-900">Mis Reportes</h2>
                    <p class="mt-2 text-sm text-slate-600">Rendimiento en mostrador, métodos de cobro, recojos en tienda y descargas PDF/Excel.</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-brand-blue">Consultar métricas →</span>
                </a>
            @endcan
        @else
            @foreach ([
                ['ventas','🧾','Registrar Venta (POS)','crear ventas','Punto de venta de mostrador, cobro multimetodo y emisión de recibos.'],
                ['productos','🧸','Productos','ver productos','Consulta el catálogo de juguetes y sus imágenes.'],
                ['modelos-3d','🧊','Generar 3D','ver productos','Modelado 3D por fotogrametría y escaneo asistido con IA.'],
                ['inventario','📦','Inventario','gestionar inventario','Gestiona ingresos por lotes FIFO y existencias.'],
                ['usuarios','👥','Usuarios y accesos','administrar accesos','Invita empleados, controla roles y revisa la auditoría.'],
                ['reportes','📊','Reportes Ejecutivos','administrar accesos','Estadísticas FIFO, inventarios, interacciones 3D y descargas PDF/Excel.'],
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

            @can('administrar accesos')
                <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-slate-50/70 p-6 opacity-80 cursor-not-allowed">
                    <div class="flex items-center justify-between">
                        <span class="text-2xl grayscale opacity-60">🎟️</span>
                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Pronto</span>
                    </div>
                    <h2 class="mt-4 text-xl font-semibold text-slate-700">Promociones</h2>
                    <p class="mt-2 text-sm text-slate-500">Campañas promocionales, cupones y descuentos automáticos.</p>
                    <span class="mt-4 inline-block text-xs font-medium text-slate-400">Próximamente disponible</span>
                </div>
            @endcan
        @endcan
    </div>
</x-admin-shell>
