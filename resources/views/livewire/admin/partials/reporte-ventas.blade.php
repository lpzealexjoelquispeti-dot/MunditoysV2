@php
    $kpis = $datos['kpis'];
    $comparacion = $datos['comparacion'];
    $canales = collect($datos['ventas_por_canal'])->map(fn ($valor, $clave) => ['nombre' => $clave === 'online' ? 'Tienda online' : 'Tienda física', 'valor' => $valor, 'color' => $clave === 'online' ? '#005ca8' : '#f58220'])->values();
    $pagos = collect($datos['ventas_por_metodo'])->map(fn ($valor, $clave) => ['nombre' => $opciones['metodos_pago'][$clave] ?? $clave, 'valor' => $valor])->sortByDesc('valor')->values();
    $lider = $datos['top_productos'][0] ?? null;
@endphp
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <x-report-stat label="Ingresos Totales" :value="number_format($kpis['total_ingresos'], 2)" unit="Bs" hint="Importe después de descuentos" :change="$comparacion['total_ingresos'] ?? null" />
    <x-report-stat label="Ganancia bruta" :value="number_format($kpis['ganancia_bruta'], 2)" unit="Bs" :hint="'Margen bruto: '.number_format($kpis['margen_promedio'], 1).'% · excluye gastos operativos'" :change="$comparacion['ganancia_bruta'] ?? null" />
    <x-report-stat label="Pedidos" :value="number_format($kpis['total_pedidos'])" :hint="number_format($kpis['unidades_vendidas']).' unidades vendidas'" :change="$comparacion['total_pedidos'] ?? null" />
    <x-report-stat label="Ticket promedio" :value="number_format($kpis['ticket_promedio'], 2)" unit="Bs" hint="Ingresos seleccionados / pedidos" :change="$comparacion['ticket_promedio'] ?? null" />
</div>
<x-report-panel title="Ingresos y ganancia bruta en el tiempo" :subtitle="'Totales por '.mb_strtolower($datos['agrupacion']).' · Bs · todo el período seleccionado'" :note="'Costo de venta: Bs '.number_format($kpis['total_costo'], 2).' · Descuentos atribuidos: Bs '.number_format($kpis['descuentos_aplicados'], 2).'. Los descuentos se distribuyen proporcionalmente entre los productos.'">
    <x-report-line-chart :points="$datos['serie_cronologica']" :series="[['key' => 'ingresos', 'label' => 'Ingresos', 'color' => '#005ca8'], ['key' => 'ganancia', 'label' => 'Ganancia bruta', 'color' => '#059669']]" label="Ingresos y ganancia bruta en el tiempo" />
    @if($kpis['unidades_costo_estimado'] > 0)
        <p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">{{ $kpis['unidades_costo_estimado'] }} unidades no tienen lote asociado: su costo utiliza el precio de compra de referencia o cero si no existe. La ganancia de esas unidades es estimada.</p>
    @endif
</x-report-panel>
<div class="grid items-start gap-6 xl:grid-cols-2">
    <x-report-panel title="Participación por canal" subtitle="Ingresos en Bs y porcentaje del total seleccionado">
        <x-report-bars :rows="$canales" unit="Bs" :total="$kpis['total_ingresos']" />
    </x-report-panel>
    <x-report-panel title="Ingresos por método de pago" subtitle="Ordenados de mayor a menor · Bs y participación">
        <x-report-bars :rows="$pagos" unit="Bs" :total="$kpis['total_ingresos']" color="#059669" />
    </x-report-panel>
</div>
<x-report-panel title="Productos con mayor ganancia bruta" subtitle="Top 10 · ordenado por ganancia en Bs, después de descuentos" :note="$lider ? $lider['nombre'].' aporta Bs '.number_format($lider['ganancia'], 2).' de ganancia bruta, con un margen de '.number_format($lider['margen_pct'], 1).'%.' : null">
    <x-report-bars :rows="$datos['top_productos']" value-key="ganancia" unit="Bs" color="#059669" />
    <details class="mt-5 border-t border-slate-100 pt-4"><summary class="cursor-pointer text-sm font-semibold text-brand-blue">Ver unidades, ingresos, costos y margen</summary>
        <div class="mt-3"><x-report-table :rows="$datos['top_productos']" :columns="['nombre' => 'Producto', 'categoria' => 'Categoría', 'unidades' => 'Unidades', 'ingresos' => 'Ingresos', 'costos' => 'Costo', 'ganancia' => 'Ganancia bruta', 'margen_pct' => 'Margen bruto']" :money="['ingresos', 'costos', 'ganancia']" :percent="['margen_pct']" /></div>
    </details>
</x-report-panel>
<x-report-panel title="Detalle de ventas" subtitle="Hasta 50 ventas recientes · importes atribuidos a los filtros seleccionados">
    <x-report-table :rows="$datos['ventas_registros']" :columns="['numero_venta' => 'Venta', 'cliente.name' => 'Cliente', 'vendedor.name' => 'Vendedor', 'tipo_venta' => 'Canal', 'estado' => 'Estado', 'total' => 'Importe seleccionado']" :money="['total']" />
</x-report-panel>
