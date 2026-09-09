<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Ejecutivo de Ventas y Rentabilidad - MundiToys</title>
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
            border-bottom: 2px solid #005ca8;
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
            border-top: 3px solid #005ca8;
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
            color: #16a34a;
            margin-top: 2px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: #005ca8;
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
            background-color: #005ca8;
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
        .tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .tag-online {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .tag-fisica {
            background-color: #fef3c7;
            color: #b45309;
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

    {{-- ENCABEZADO CORPORATIVO --}}
    <table class="header">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                <div class="logo-box">★ Mundi<span>Toys</span></div>
                <div class="report-subtitle">Sistema Integral de Gestión de Juguetería y Modelado 3D</div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <h1 class="report-title">Ventas y Rentabilidad Real</h1>
                <div class="report-subtitle">Emisión: {{ now()->format('d/m/Y H:i:s') }} | Usuario: {{ auth()->user()?->name ?? 'Administrador' }}</div>
            </td>
        </tr>
    </table>

    {{-- PARÁMETROS Y FILTROS APLICADOS --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Rango Fechas:</td>
            <td class="meta-value">{{ $filtros_aplicados['fecha_desde'] }} hasta {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <td class="meta-label">Sucursal:</td>
            <td class="meta-value">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Canal Venta:</td>
            <td class="meta-value">{{ ucfirst($filtros_aplicados['tipo_venta']) }}</td>
            <td class="meta-label">Categoría:</td>
            <td class="meta-value">{{ $filtros_aplicados['categoria'] }}</td>
        </tr>
    </table>

    {{-- BLOQUE DE KPIS EJECUTIVOS --}}
    <table class="kpi-container" style="width: 100%; border-spacing: 6px; border-collapse: separate;">
        <tr>
            <td class="kpi-card" style="border-top-color: #005ca8; width: 16%;">
                <div class="kpi-title">Ingresos Totales</div>
                <div class="kpi-number">Bs. {{ number_format($kpis['total_ingresos'], 2) }}</div>
                <div class="kpi-badge">Ventas Brutas</div>
            </td>
            <td class="kpi-card" style="border-top-color: #64748b; width: 16%;">
                <div class="kpi-title">Costo FIFO Lotes</div>
                <div class="kpi-number">Bs. {{ number_format($kpis['total_costo'], 2) }}</div>
                <div class="kpi-badge" style="color: #64748b;">Inversión Mercadería</div>
            </td>
            <td class="kpi-card" style="border-top-color: #16a34a; width: 17%;">
                <div class="kpi-title">Ganancia Neta Real</div>
                <div class="kpi-number" style="color: #16a34a;">Bs. {{ number_format($kpis['ganancia_bruta'], 2) }}</div>
                <div class="kpi-badge">Margen: {{ $kpis['margen_promedio'] }}%</div>
            </td>
            <td class="kpi-card" style="border-top-color: #ea580c; width: 17%;">
                <div class="kpi-title">Pedidos Totales</div>
                <div class="kpi-number">{{ number_format($kpis['total_pedidos']) }}</div>
                <div class="kpi-badge" style="color: #ea580c;">Órdenes Atendidas</div>
            </td>
            <td class="kpi-card" style="border-top-color: #8b5cf6; width: 17%;">
                <div class="kpi-title">Ticket Promedio</div>
                <div class="kpi-number">Bs. {{ number_format($kpis['ticket_promedio'], 2) }}</div>
                <div class="kpi-badge" style="color: #8b5cf6;">Por Transacción</div>
            </td>
            <td class="kpi-card" style="border-top-color: #0284c7; width: 17%;">
                <div class="kpi-title">Unidades Vendidas</div>
                <div class="kpi-number">{{ number_format($kpis['unidades_vendidas']) }}</div>
                <div class="kpi-badge" style="color: #0284c7;">Descuentos: Bs. {{ number_format($kpis['descuentos_aplicados'], 2) }}</div>
            </td>
        </tr>
    </table>

    {{-- DESGLOSE DE CANAL Y TOP PRODUCTOS RENTABLES --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 48%; vertical-align: top; padding-right: 10px;">
                <div class="section-heading">Distribución por Canal de Facturación</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Canal Comercial</th>
                            <th class="text-right">Monto Total (Bs.)</th>
                            <th class="text-right">% Participación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalCanales = max(array_sum($ventas_por_canal), 1);
                        @endphp
                        @foreach ($ventas_por_canal as $canal => $monto)
                            <tr>
                                <td>
                                    <span class="tag {{ $canal === 'online' ? 'tag-online' : 'tag-fisica' }}">
                                        {{ $canal === 'online' ? 'Tienda Online' : 'Sucursal Física' }}
                                    </span>
                                </td>
                                <td class="text-right font-bold">Bs. {{ number_format($monto, 2) }}</td>
                                <td class="text-right">{{ round(($monto / $totalCanales) * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="section-heading">Ventas por Método de Pago</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th class="text-right">Total (Bs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ventas_por_metodo as $metodo => $monto)
                            <tr>
                                <td>{{ ucfirst($metodo) }}</td>
                                <td class="text-right font-bold">Bs. {{ number_format($monto, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center" style="color: #94a3b8;">Sin registros</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td style="width: 52%; vertical-align: top;">
                <div class="section-heading">Top 10 Juguetes con Mayor Rentabilidad Real</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Juguete</th>
                            <th class="text-center">Cant.</th>
                            <th class="text-right">Ingreso</th>
                            <th class="text-right">Costo</th>
                            <th class="text-right">Margen Real</th>
                            <th class="text-right">% Rent.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($top_productos as $tp)
                            <tr>
                                <td>
                                    <strong>{{ $tp['nombre'] }}</strong>
                                    <br><small style="color: #64748b;">{{ $tp['categoria'] }}</small>
                                </td>
                                <td class="text-center font-bold">{{ $tp['unidades'] }}</td>
                                <td class="text-right">Bs. {{ number_format($tp['ingresos'], 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($tp['costos'], 2) }}</td>
                                <td class="text-right font-bold" style="color: #16a34a;">Bs. {{ number_format($tp['ganancia'], 2) }}</td>
                                <td class="text-right font-bold">{{ $tp['margen_pct'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center" style="color: #94a3b8;">Sin ventas registradas en el periodo seleccionado</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- DETALLE CRONOLÓGICO DE TRANSACCIONES --}}
    <div class="section-heading">Detalle de Ventas Registradas (Últimas 50 operaciones)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>N° Venta</th>
                <th>Fecha / Hora</th>
                <th>Cliente</th>
                <th>Canal</th>
                <th>Método Pago</th>
                <th>Sucursal / Entrega</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Desc.</th>
                <th class="text-right">Total Facturado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($ventas_registros as $v)
                <tr>
                    <td><strong>#{{ $v->numero_venta }}</strong></td>
                    <td>{{ $v->fecha_venta ? $v->fecha_venta->format('d/m/Y H:i') : $v->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $v->cliente?->name ?? 'Cliente General' }}</td>
                    <td>
                        <span class="tag {{ $v->tipo_venta === 'online' ? 'tag-online' : 'tag-fisica' }}">
                            {{ $v->tipo_venta }}
                        </span>
                    </td>
                    <td>{{ $v->pago ? ucfirst($v->pago->metodo_pago) : 'N/A' }}</td>
                    <td>{{ $v->entrega?->sucursal?->nombre ?? ($v->detalles->first()?->lote?->inventario?->almacen?->sucursal?->nombre ?? 'Principal') }}</td>
                    <td class="text-right">Bs. {{ number_format($v->subtotal, 2) }}</td>
                    <td class="text-right">Bs. {{ number_format($v->descuento, 2) }}</td>
                    <td class="text-right font-bold" style="color: #005ca8;">Bs. {{ number_format($v->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center" style="color: #94a3b8;">No se encontraron ventas para los criterios seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        MundiToys S.R.L. &bull; Reporte Oficial para Auditoría Administrativa y Contable &bull; Confidencial
    </div>

</body>
</html>

