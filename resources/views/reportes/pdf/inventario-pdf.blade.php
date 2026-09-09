<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario, Valoración y Costos - MundiToys</title>
    <style>
        @page {
            margin: 20px 25px 30px 25px;
            size: a4 landscape;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #ea580c;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .logo-box {
            font-size: 22px;
            font-weight: 800;
            color: #005ca8;
            letter-spacing: -0.5px;
        }
        .logo-box span {
            color: #ea580c;
        }
        .report-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            margin: 0;
        }
        .report-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 14px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 9px;
            border-bottom: 1px solid #e2e8f0;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 15%;
        }
        .meta-value {
            color: #0f172a;
            width: 35%;
        }
        .kpi-container {
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-top: 3px solid #ea580c;
            padding: 8px 10px;
            text-align: center;
        }
        .kpi-title {
            font-size: 8.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
        }
        .kpi-number {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .kpi-badge {
            font-size: 8px;
            font-weight: 600;
            color: #ea580c;
            margin-top: 2px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: #ea580c;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #334155;
            color: #ffffff;
            font-weight: 700;
            text-align: left;
            padding: 6px 8px;
            font-size: 8.5px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5px;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .font-bold {
            font-weight: bold;
        }
        .status-pill {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-optimo {
            background-color: #dcfce7;
            color: #15803d;
        }
        .status-bajo {
            background-color: #fef9c3;
            color: #a16207;
        }
        .status-agotado {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <table class="header">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="logo-box">★ Mundi<span>Toys</span></div>
                <div class="report-subtitle">Control de Stock, Valuación de Activos y Auditoría de Lotes</div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <h1 class="report-title">Inventario y Valuación FIFO</h1>
                <div class="report-subtitle">Emisión: {{ now()->format('d/m/Y H:i:s') }} | Encargado: {{ auth()->user()?->name ?? 'Administrador' }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Sucursal:</td>
            <td class="meta-value">{{ $filtros_aplicados['sucursal'] }}</td>
            <td class="meta-label">Categoría:</td>
            <td class="meta-value">{{ $filtros_aplicados['categoria'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Estado Stock:</td>
            <td class="meta-value">{{ $filtros_aplicados['estado_stock'] }}</td>
            <td class="meta-label">Mermas/Bajas:</td>
            <td class="meta-value">{{ $kpis['unidades_merma'] }} unidades registradas</td>
        </tr>
    </table>

    {{-- KPIS DE INVENTARIO --}}
    <table class="kpi-container" style="width: 100%; border-spacing: 6px; border-collapse: separate;">
        <tr>
            <td class="kpi-card" style="border-top-color: #ea580c; width: 16%;">
                <div class="kpi-title">Stock Físico Total</div>
                <div class="kpi-number">{{ number_format($kpis['unidades_stock_total']) }}</div>
                <div class="kpi-badge">Unidades en Almacén</div>
            </td>
            <td class="kpi-card" style="border-top-color: #64748b; width: 16%;">
                <div class="kpi-title">Valuación a Costo</div>
                <div class="kpi-number">Bs. {{ number_format($kpis['valor_costo_total'], 2) }}</div>
                <div class="kpi-badge" style="color: #64748b;">Capital Invertido</div>
            </td>
            <td class="kpi-card" style="border-top-color: #005ca8; width: 17%;">
                <div class="kpi-title">Valuación a Venta</div>
                <div class="kpi-number" style="color: #005ca8;">Bs. {{ number_format($kpis['valor_venta_total'], 2) }}</div>
                <div class="kpi-badge" style="color: #005ca8;">Valor Catálogo</div>
            </td>
            <td class="kpi-card" style="border-top-color: #16a34a; width: 17%;">
                <div class="kpi-title">Margen Proyectado</div>
                <div class="kpi-number" style="color: #16a34a;">Bs. {{ number_format($kpis['margen_potencial'], 2) }}</div>
                <div class="kpi-badge" style="color: #16a34a;">Retorno: {{ $kpis['margen_porcentaje'] }}%</div>
            </td>
            <td class="kpi-card" style="border-top-color: #dc2626; width: 17%;">
                <div class="kpi-title">Alertas Críticas</div>
                <div class="kpi-number" style="color: #dc2626;">{{ $kpis['productos_agotados'] }} / {{ $kpis['productos_bajo_minimo'] }}</div>
                <div class="kpi-badge" style="color: #dc2626;">Agotados / Bajo Mínimo</div>
            </td>
            <td class="kpi-card" style="border-top-color: #d97706; width: 17%;">
                <div class="kpi-title">Pérdidas / Mermas</div>
                <div class="kpi-number" style="color: #d97706;">{{ $kpis['unidades_merma'] }} u.</div>
                <div class="kpi-badge" style="color: #d97706;">Dañados o Exhibición</div>
            </td>
        </tr>
    </table>

    {{-- DESGLOSE POR CATEGORÍA Y ALERTAS --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 10px;">
                <div class="section-heading">Distribución de Capital por Categoría</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Categoría</th>
                            <th class="text-center">Stock</th>
                            <th class="text-right">Valuación Costo</th>
                            <th class="text-right">Valuación Venta</th>
                            <th class="text-right">Margen Bruto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stock_por_categoria as $sc)
                            <tr>
                                <td><strong>{{ $sc['categoria'] }}</strong></td>
                                <td class="text-center font-bold">{{ number_format($sc['unidades']) }}</td>
                                <td class="text-right">Bs. {{ number_format($sc['valor_costo'], 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($sc['valor_venta'], 2) }}</td>
                                <td class="text-right font-bold" style="color: #16a34a;">
                                    Bs. {{ number_format($sc['valor_venta'] - $sc['valor_costo'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center" style="color: #94a3b8;">Sin datos en el inventario</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td style="width: 45%; vertical-align: top;">
                <div class="section-heading">Lotes con Alerta de Vencimiento / Caducidad</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Lote</th>
                            <th>Juguete</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-center">Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lotes_alerta as $la)
                            <tr>
                                <td><code>{{ $la['lote'] }}</code></td>
                                <td>{{ $la['producto'] }}</td>
                                <td class="text-center font-bold">{{ $la['unidades'] }}</td>
                                <td class="text-center">{{ $la['fecha'] }}</td>
                                <td>
                                    <span class="status-pill {{ $la['estado'] === 'Vencido' ? 'status-agotado' : 'status-bajo' }}">
                                        {{ $la['estado'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center" style="color: #16a34a; font-weight: bold;">
                                    ✓ No existen lotes vencidos ni próximos a vencer en los próximos 60 días.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- DETALLE DEL STOCK POR PRODUCTO --}}
    <div class="section-heading">Auditoría Detallada de Inventario (Primeros 50 productos)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto / Juguete</th>
                <th>Categoría</th>
                <th>Sucursal - Almacén</th>
                <th class="text-center">Disponible</th>
                <th class="text-center">Mínimo</th>
                <th>Condición</th>
                <th class="text-right">Val. Costo</th>
                <th class="text-right">Val. Venta</th>
                <th class="text-right">Margen Potencial</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inventario_registros as $inv)
                <tr>
                    <td><code>{{ $inv['codigo'] }}</code></td>
                    <td><strong>{{ $inv['producto'] }}</strong></td>
                    <td>{{ $inv['categoria'] }}</td>
                    <td>{{ $inv['sucursal'] }} - {{ $inv['almacen'] }}</td>
                    <td class="text-center font-bold">{{ $inv['stock_actual'] }}</td>
                    <td class="text-center">{{ $inv['stock_minimo'] }}</td>
                    <td>
                        @php
                            $clase = match($inv['condicion']) {
                                'Agotado' => 'status-agotado',
                                'Bajo Mínimo' => 'status-bajo',
                                default => 'status-optimo',
                            };
                        @endphp
                        <span class="status-pill {{ $clase }}">{{ $inv['condicion'] }}</span>
                    </td>
                    <td class="text-right">Bs. {{ number_format($inv['valor_costo'], 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($inv['valor_venta'], 2) }}</td>
                    <td class="text-right font-bold" style="color: #16a34a;">Bs. {{ number_format($inv['margen_potencial'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center" style="color: #94a3b8;">No se encontraron registros de inventario con los filtros indicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        MundiToys S.R.L. &bull; Reporte de Auditoría Patrimonial y Control de Almacén
    </div>

</body>
</html>

