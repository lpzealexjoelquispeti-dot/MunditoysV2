@php
    $kpis = $datos['kpis'];
    $estados = collect($datos['estados_stock'])->map(fn ($valor, $nombre) => ['nombre' => $nombre, 'valor' => $valor, 'color' => ['Agotado' => '#e30613', 'Bajo Mínimo' => '#f58220', 'Óptimo' => '#059669'][$nombre]])->values();
    $reposicion = collect($datos['reposicion'])->map(fn ($fila) => array_merge($fila, ['nombre' => $fila['producto'].' · '.$fila['almacen'], 'detalle' => $fila['sucursal'].' · '.$fila['condicion']]));
    $lider = $datos['stock_por_categoria'][0] ?? null;
@endphp
<div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-brand-blue">
    Existencias actuales al {{ $datos['filtros_aplicados']['existencias_al'] }}. Las fechas seleccionadas se aplican a las mermas; no reconstruyen un inventario histórico.
</div>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-report-stat label="Valuación Costo FIFO" :value="number_format($kpis['valor_costo_total'], 2)" unit="Bs" hint="Capital al costo en lotes activos" />
    <x-report-stat label="Stock Total" :value="number_format($kpis['unidades_stock_total'])" unit="unidades" hint="Existencias de los filtros seleccionados" />
    <x-report-stat label="Existencias que requieren atención" :value="$kpis['productos_agotados'] + $kpis['productos_bajo_minimo']" :hint="$kpis['productos_agotados'].' agotadas · '.$kpis['productos_bajo_minimo'].' bajo mínimo · producto por almacén'" />
    <x-report-stat label="Mermas del período" :value="number_format($kpis['unidades_merma'])" unit="unidades" hint="Mismos filtros de sucursal, categoría y estado actual" />
</div>
<x-report-panel title="Capital al costo por categoría" subtitle="Bs y participación en el total · ordenado de mayor a menor" :note="$lider && $kpis['valor_costo_total'] > 0 ? $lider['categoria'].' concentra el '.number_format($lider['valor_costo'] / $kpis['valor_costo_total'] * 100, 1).'% del capital seleccionado. La valoración no mide rotación ni antigüedad del stock.' : 'La valoración refleja el stock actual; no mide rotación ni antigüedad.'">
    <x-report-bars :rows="$datos['stock_por_categoria']" label-key="categoria" value-key="valor_costo" unit="Bs" :total="$kpis['valor_costo_total']" />
</x-report-panel>
<div class="grid items-start gap-6 xl:grid-cols-2">
    <x-report-panel title="Stock disponible frente al mínimo" subtitle="Hasta 10 existencias prioritarias · azul: disponible · ámbar: mínimo configurado">
        <x-report-bars :rows="$reposicion" value-key="stock_actual" secondary-key="stock_minimo" secondary-label="Stock mínimo" empty="No hay existencias agotadas o bajo mínimo con estos filtros." />
    </x-report-panel>
    <div class="space-y-6">
        <x-report-panel title="Estado de las existencias" subtitle="Cantidad de registros producto–almacén y participación">
            <x-report-bars :rows="$estados" :total="array_sum($datos['estados_stock'])" />
        </x-report-panel>
        <x-report-panel title="Potencial de venta" subtitle="Estimación a precios actuales, antes de gastos y descuentos futuros">
            <p class="text-3xl font-semibold text-brand-ink tabular-nums">Bs {{ number_format($kpis['valor_venta_total'], 2) }}</p>
            <p class="mt-3 text-sm text-slate-600">Ganancia bruta potencial: <strong>Bs {{ number_format($kpis['margen_potencial'], 2) }}</strong> · {{ number_format($kpis['margen_porcentaje'], 1) }}% de margen.</p>
        </x-report-panel>
    </div>
</div>
<x-report-panel title="Lotes vencidos y próximos a vencer" subtitle="Hasta 10 alertas por fecha de vencimiento · horizonte: 60 días">
    <x-report-table :rows="$datos['lotes_alerta']" :columns="['producto' => 'Producto', 'lote' => 'Lote', 'sucursal' => 'Sucursal', 'unidades' => 'Unidades', 'fecha' => 'Vencimiento', 'estado' => 'Estado']" />
</x-report-panel>
<x-report-panel title="Auditoría de existencias" subtitle="Hasta 50 registros · mismo alcance que los indicadores">
    <x-report-table :rows="$datos['inventario_registros']" :columns="['producto' => 'Producto', 'sucursal' => 'Sucursal', 'almacen' => 'Almacén', 'stock_actual' => 'Disponible', 'stock_minimo' => 'Mínimo', 'condicion' => 'Estado', 'valor_costo' => 'Valor al costo', 'valor_venta' => 'Valor de venta']" :money="['valor_costo', 'valor_venta']" />
</x-report-panel>
<x-report-panel title="Mermas recientes del período" subtitle="Hasta 20 movimientos · cantidades registradas">
    <x-report-table :rows="$datos['mermas_recientes']" :columns="['lote.inventario.producto.nombre' => 'Producto', 'tipo_movimiento' => 'Movimiento', 'cantidad' => 'Cantidad', 'motivo' => 'Motivo', 'created_at' => 'Fecha']" />
</x-report-panel>
