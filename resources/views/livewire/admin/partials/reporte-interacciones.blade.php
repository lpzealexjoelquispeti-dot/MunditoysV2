@php
    $kpis = $datos['kpis'];
    $comparacion = $datos['comparacion'];
    $eventos = collect($datos['conteo_por_tipo'])->map(fn ($valor, $clave) => ['nombre' => $opciones['tipos_interaccion'][$clave] ?? ucfirst(str_replace('_', ' ', $clave)), 'valor' => $valor, 'color' => ['ver_3d' => '#7c3aed', 'agregar_carrito' => '#059669'][$clave] ?? '#005ca8'])->sortByDesc('valor')->values();
    $temas = collect($datos['topicos_ia'])->map(fn ($valor, $nombre) => ['nombre' => $nombre, 'valor' => $valor])->values();
    $busquedas = collect($datos['top_busquedas'])->map(fn ($valor, $nombre) => ['nombre' => $nombre, 'valor' => $valor])->values();
@endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-report-stat label="Eventos del catálogo" :value="number_format($kpis['total_interacciones'])" hint="Acciones registradas, no personas únicas" :change="$comparacion['total_interacciones'] ?? null" />
    <x-report-stat label="Vistas de producto" :value="number_format($kpis['total_vistas_producto'])" hint="Incluye los eventos equivalentes de detalle" :change="$comparacion['total_vistas_producto'] ?? null" />
    <x-report-stat label="Visor 3D Interactivo" :value="number_format($kpis['total_interacciones_3d'])" hint="Exploraciones registradas del modelo" :change="$comparacion['total_interacciones_3d'] ?? null" />
    <x-report-stat label="Agregados al carrito" :value="number_format($kpis['total_agregados_carrito'])" hint="Eventos de agregado, no compras completadas" :change="$comparacion['total_agregados_carrito'] ?? null" />
</div>
<x-report-panel title="Actividad del catálogo en el tiempo" :subtitle="'Eventos por '.mb_strtolower($datos['agrupacion']).' · cada serie se mide de forma independiente'" note="Una persona puede generar varios eventos. Estos conteos no establecen una secuencia de compra ni demuestran que usar 3D aumente la conversión.">
    <x-report-line-chart :points="$datos['serie_cronologica']" :series="[['key' => 'vistas', 'label' => 'Vistas de producto', 'color' => '#005ca8'], ['key' => 'modelos_3d', 'label' => 'Exploraciones 3D', 'color' => '#7c3aed'], ['key' => 'carritos', 'label' => 'Agregados al carrito', 'color' => '#059669']]" unit="eventos" label="Actividad del catálogo en el tiempo" />
</x-report-panel>
<div class="grid items-start gap-6 xl:grid-cols-2">
    <x-report-panel title="Distribución de eventos" subtitle="Cantidad y porcentaje de todos los eventos seleccionados">
        <x-report-bars :rows="$eventos" :total="$kpis['total_interacciones']" />
    </x-report-panel>
    <x-report-panel title="Términos de búsqueda frecuentes" subtitle="Hasta 8 términos · número de búsquedas registradas">
        <x-report-bars :rows="$busquedas" color="#f58220" />
    </x-report-panel>
</div>
<x-report-panel title="Tópicos Recurrentes en el Asistente IA" :subtitle="$kpis['total_conversaciones_ia'].' conversaciones activas · '.$kpis['total_mensajes_ia'].' mensajes en el período · alcance global, independiente de los filtros de catálogo'" note="Clasificación por palabras clave en mensajes del usuario. Una conversación puede contener varios temas; los porcentajes pueden sumar más de 100 %. Se muestran hasta 6 temas.">
    <x-report-bars :rows="$temas" :total="$kpis['total_conversaciones_ia']" color="#7c3aed" />
</x-report-panel>
<x-report-panel title="Juguetes con mayor actividad" subtitle="Top 10 por total de eventos · compara interés y agregados al carrito">
    <x-report-bars :rows="$datos['top_juguetes']" value-key="total" />
    <details class="mt-5 border-t border-slate-100 pt-4"><summary class="cursor-pointer text-sm font-semibold text-brand-blue">Ver el detalle por tipo de interacción</summary>
        <div class="mt-3"><x-report-table :rows="$datos['top_juguetes']" :columns="['nombre' => 'Producto', 'categoria' => 'Categoría', 'vistas' => 'Vistas', 'interacciones_3d' => '3D', 'carritos' => 'Carrito', 'total' => 'Total de eventos']" /></div>
    </details>
</x-report-panel>
