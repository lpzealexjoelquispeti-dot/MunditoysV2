@props(['section', 'filters', 'points' => [], 'comparison' => [], 'kpis'])
<div style="font-family: Helvetica, Arial, sans-serif; font-size: 10px; margin-top: 18px; {{ $points ? 'page-break-before: always;' : '' }}">
    <h2 style="font-size: 13px; color: #005ca8;">Alcance y lectura del reporte</h2>
    <p>
        @foreach($filters as $key => $value)
            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}{{ $loop->last ? '.' : ' | ' }}
        @endforeach
    </p>
    @if($section === 'ventas')
        <p>Ingresos después de descuentos. Ganancia bruta = ingresos menos costo de productos; excluye gastos operativos. Los importes se atribuyen proporcionalmente a los productos seleccionados. Hasta 50 ventas recientes en el detalle.</p>
        @if($kpis['unidades_costo_estimado'] > 0)<p>{{ $kpis['unidades_costo_estimado'] }} unidades sin lote: costo estimado con precio de compra de referencia o cero si no existe.</p>@endif
    @elseif($section === 'interacciones')
        <p>Conteos de eventos, no personas únicas ni conversiones de compra. Asistente IA: alcance global del período, independiente de los filtros de catálogo. Los temas se detectan por palabras clave en mensajes del usuario; una conversación puede aparecer en varios temas.</p>
    @elseif($section === 'inventario')
        <p>Existencias actuales al {{ $filters['existencias_al'] }}. El período se aplica a las mermas, con los filtros de sucursal, categoría y estado actual. Las cantidades de alertas corresponden a registros producto-almacén. La valoración no mide rotación. Detalle: hasta 50 existencias y 10 lotes en alerta.</p>
    @else
        <p>Los ingresos se atribuyen a la sucursal del lote de cada producto; sin lote se usa la sucursal de entrega. Los importes no se duplican; un pedido puede participar en varias sucursales. Reseñas: alcance global del período, independiente de sucursal y vendedor. Entregas: creadas en el período, según origen de despacho y vendedor.</p>
    @endif
    @if($comparison)
        <h2 style="font-size: 13px; color: #005ca8;">Comparación con período anterior</h2>
        <p>{{ $comparison['desde'] }} al {{ $comparison['hasta'] }}. Igual cantidad de días y mismos filtros.</p>
        <table style="width: 100%; border-collapse: collapse;">
            <thead><tr><th style="text-align: left;">Indicador</th><th>Anterior</th><th>Diferencia</th><th>Variación</th></tr></thead>
            <tbody>
                @foreach($comparison as $key => $change)
                    @if(is_array($change))
                        <tr>
                            <td style="padding: 5px; border-bottom: 1px solid #e2e8f0;">{{ ucfirst(str_replace('_', ' ', $key)) }}{{ in_array($key, ['total_ingresos', 'ganancia_bruta', 'ticket_promedio']) ? ' (Bs)' : '' }}</td>
                            <td style="text-align: center;">{{ number_format($change['anterior'], 2) }}</td>
                            <td style="text-align: center;">{{ number_format($change['diferencia'], 2) }}</td>
                            <td style="text-align: center;">{{ $change['porcentaje'] === null ? 'Sin base porcentual' : $change['porcentaje'].'%' }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
    @if($points)
        <h2 style="font-size: 13px; color: #005ca8;">Valores del gráfico temporal - período completo</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f1f5f9;"><tr>
                <th style="text-align: left; padding: 5px;">Desde</th><th style="text-align: left;">Hasta</th>
                @if($section === 'ventas')<th>Ingresos (Bs)</th><th>Ganancia bruta (Bs)</th><th>Pedidos</th>
                @else<th>Vistas</th><th>3D</th><th>Carrito</th><th>Total eventos</th>@endif
            </tr></thead>
            <tbody>
                @foreach($points as $point)
                    <tr style="page-break-inside: avoid;">
                        <td style="padding: 5px; border-bottom: 1px solid #e2e8f0;">{{ $point['desde'] }}</td><td>{{ $point['hasta'] }}</td>
                        @foreach($section === 'ventas' ? ['ingresos', 'ganancia', 'pedidos'] : ['vistas', 'modelos_3d', 'carritos', 'eventos'] as $key)
                            <td style="text-align: center; border-bottom: 1px solid #e2e8f0;">{{ number_format($point[$key], in_array($key, ['ingresos', 'ganancia']) ? 2 : 0) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
