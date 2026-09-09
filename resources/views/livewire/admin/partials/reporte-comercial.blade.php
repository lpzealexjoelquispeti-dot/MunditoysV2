@php
    $kpis = $datos['kpis'];
    $sucursales = collect($datos['rendimiento_sucursales'])->map(fn ($fila) => array_merge($fila, ['detalle' => $fila['pedidos'].' pedidos · ticket '.($fila['ticket_promedio'] === null ? 'sin datos' : 'Bs '.number_format($fila['ticket_promedio'], 2))]));
    $vendedores = collect($datos['ranking_vendedores'])->take(10)->map(fn ($fila) => array_merge($fila, ['detalle' => $fila['ventas_conteo'].' ventas · ticket Bs '.number_format($fila['ticket_promedio'], 2)]));
    $entregas = collect($datos['resumen_entregas'])->map(fn ($valor, $clave) => ['nombre' => ['completadas' => 'Entregadas', 'en_camino' => 'En camino', 'pendientes' => 'Pendientes', 'fallidas' => 'Fallidas o canceladas', 'otros' => 'Otros estados'][$clave], 'valor' => $valor, 'color' => ['completadas' => '#059669', 'en_camino' => '#005ca8', 'pendientes' => '#f58220', 'fallidas' => '#e30613', 'otros' => '#64748b'][$clave]])->values();
    $resenas = collect($datos['distribucion_estrellas'])->map(fn ($valor, $estrellas) => ['nombre' => $estrellas.' estrellas', 'valor' => $valor])->values();
@endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-report-stat label="Facturación" :value="number_format($kpis['total_facturado'], 2)" unit="Bs" :hint="$kpis['total_pedidos'].' pedidos únicos en el alcance seleccionado'" />
    <x-report-stat label="Sucursal Líder" :value="$kpis['sucursal_top']" hint="Mayor facturación atribuida a sus productos" />
    <x-report-stat label="Vendedor destacado" :value="$kpis['vendedor_top']" hint="Mayor importe vendido con los filtros actuales" />
    <x-report-stat label="Éxito en Entregas" :value="$kpis['tasa_exito_entrega']" unit="%" :hint="$datos['resumen_entregas']['completadas'].' entregadas / '.$kpis['total_entregas'].' creadas en el período'" />
</div>
<x-report-panel title="Facturación por sucursal" subtitle="Bs y participación · pedidos y ticket promedio como contexto" note="Cada producto se atribuye a la sucursal de su lote; si no tiene lote se usa la sucursal de entrega. El importe no se duplica. Un pedido con productos de varias sucursales puede aparecer en el conteo de cada una.">
    <x-report-bars :rows="$sucursales" value-key="facturacion" unit="Bs" :total="$kpis['total_facturado']" />
</x-report-panel>
<div class="grid items-start gap-6 xl:grid-cols-2">
    <x-report-panel title="Ranking de vendedores" subtitle="Top 10 por facturación · importe, pedidos y ticket">
        <x-report-bars :rows="$vendedores" value-key="total_vendido" unit="Bs" color="#059669" />
    </x-report-panel>
    <x-report-panel title="Estado de las entregas" :subtitle="$kpis['total_entregas'].' entregas creadas en el período · cantidad y porcentaje'" note="El porcentaje entregado incluye pedidos todavía pendientes o en ruta en su denominador. El filtro de sucursal corresponde al origen del despacho.">
        <x-report-bars :rows="$entregas" :total="$kpis['total_entregas']" />
    </x-report-panel>
</div>
<x-report-panel title="Distribución de reseñas del catálogo" :subtitle="$kpis['total_resenas'].' reseñas · alcance global del período, independiente de sucursal y vendedor'">
    <div class="grid gap-8 lg:grid-cols-[1fr_2fr]">
        <div>
            <p class="text-4xl font-bold text-brand-ink tabular-nums">{{ $kpis['calificacion_promedio'] === null ? 'Sin datos' : number_format($kpis['calificacion_promedio'], 1).' / 5' }}</p>
            <p class="mt-3 text-sm leading-relaxed text-slate-500">Calificación promedio de las reseñas registradas. Revisa también su distribución y cantidad.</p>
        </div>
        <x-report-bars :rows="$resenas" :total="$kpis['total_resenas']" color="#f59e0b" />
    </div>
</x-report-panel>
<x-report-panel title="Detalle del rendimiento comercial" subtitle="Todos los vendedores con ventas en el alcance seleccionado">
    <x-report-table :rows="$datos['ranking_vendedores']" :columns="['nombre' => 'Vendedor', 'ventas_conteo' => 'Ventas', 'total_vendido' => 'Facturación', 'ticket_promedio' => 'Ticket promedio']" :money="['total_vendido', 'ticket_promedio']" />
</x-report-panel>
