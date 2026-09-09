<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Rendimiento Comercial y Sucursales - MundiToys</title>
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
            border-bottom: 2px solid #8b5cf6;
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
            border-top: 3px solid #8b5cf6;
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
            color: #8b5cf6;
            margin-top: 2px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: #8b5cf6;
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
            background-color: #475569;
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
                <div class="report-subtitle">Desempeño de Sucursales, Productividad de Asesores y Logística</div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <h1 class="report-title">Rendimiento Comercial</h1>
                <div class="report-subtitle">Emisión: {{ now()->format('d/m/Y H:i:s') }} | Evaluador: {{ auth()->user()?->name ?? 'Administrador' }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Periodo:</td>
            <td class="meta-value">{{ $filtros_aplicados['fecha_desde'] }} hasta {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <td class="meta-label">Sucursal:</td>
            <td class="meta-value">{{ $filtros_aplicados['sucursal'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Vendedor / Asesor:</td>
            <td class="meta-value">{{ $filtros_aplicados['vendedor'] }}</td>
            <td class="meta-label">Enfoque:</td>
            <td class="meta-value">Fulfillment, CSAT y Eficiencia Comercial</td>
        </tr>
    </table>

    {{-- KPIS EJECUTIVOS --}}
    <table class="kpi-container" style="width: 100%; border-spacing: 6px; border-collapse: separate;">
        <tr>
            <td class="kpi-card" style="border-top-color: #8b5cf6; width: 16%;">
                <div class="kpi-title">Sucursal Líder</div>
                <div class="kpi-number" style="font-size: 13px;">{{ $kpis['sucursal_top'] }}</div>
                <div class="kpi-badge">Mayor Facturación</div>
            </td>
            <td class="kpi-card" style="border-top-color: #0284c7; width: 16%;">
                <div class="kpi-title">Vendedor Destacado</div>
                <div class="kpi-number" style="font-size: 13px;">{{ $kpis['vendedor_top'] }}</div>
                <div class="kpi-badge">Mayor Volumen</div>
            </td>
            <td class="kpi-card" style="border-top-color: #16a34a; width: 17%;">
                <div class="kpi-title">Fulfillment Logístico</div>
                <div class="kpi-number" style="color: #16a34a;">{{ $kpis['tasa_exito_entrega'] }}%</div>
                <div class="kpi-badge">{{ $kpis['total_entregas'] }} Entregas Totales</div>
            </td>
            <td class="kpi-card" style="border-top-color: #f59e0b; width: 17%;">
                <div class="kpi-title">Satisfacción Cliente</div>
                <div class="kpi-number" style="color: #f59e0b;">{{ $kpis['calificacion_promedio'] }} / 5.0 ★</div>
                <div class="kpi-badge">{{ $kpis['total_resenas'] }} Reseñas Recibidas</div>
            </td>
            <td class="kpi-card" style="border-top-color: #10b981; width: 17%;">
                <div class="kpi-title">Entregas Exitosas</div>
                <div class="kpi-number" style="color: #10b981;">{{ $resumen_entregas['completadas'] }}</div>
                <div class="kpi-badge">Completadas sin Incidencias</div>
            </td>
            <td class="kpi-card" style="border-top-color: #ef4444; width: 17%;">
                <div class="kpi-title">Entregas Fallidas</div>
                <div class="kpi-number" style="color: #ef4444;">{{ $resumen_entregas['fallidas'] }}</div>
                <div class="kpi-badge">Canceladas o No Recibidas</div>
            </td>
        </tr>
    </table>

    {{-- RENDIMIENTO DE SUCURSALES Y RANKING DE VENDEDORES --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <div class="section-heading">Ventas y Facturación por Sucursal</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sucursal</th>
                            <th>Ciudad</th>
                            <th class="text-center">Órdenes</th>
                            <th class="text-right">Total Vendido</th>
                            <th class="text-right">Ticket Prom.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rendimiento_sucursales as $rs)
                            <tr>
                                <td><strong>{{ $rs['nombre'] }}</strong></td>
                                <td>{{ $rs['ciudad'] }}</td>
                                <td class="text-center font-bold">{{ $rs['pedidos'] }}</td>
                                <td class="text-right font-bold" style="color: #005ca8;">Bs. {{ number_format($rs['facturacion'], 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($rs['ticket_promedio'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center" style="color: #94a3b8;">Sin actividad registrada</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td style="width: 50%; vertical-align: top;">
                <div class="section-heading">Ranking de Vendedores y Asesores</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asesor Comercial</th>
                            <th class="text-center">Ventas</th>
                            <th class="text-right">Total Facturado</th>
                            <th class="text-right">Ticket Prom.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ranking_vendedores as $rv)
                            <tr>
                                <td>
                                    <strong>{{ $rv['nombre'] }}</strong>
                                    <br><small style="color: #64748b;">{{ $rv['email'] }}</small>
                                </td>
                                <td class="text-center font-bold">{{ $rv['ventas_conteo'] }}</td>
                                <td class="text-right font-bold" style="color: #16a34a;">Bs. {{ number_format($rv['total_vendido'], 2) }}</td>
                                <td class="text-right">Bs. {{ number_format($rv['ticket_promedio'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center" style="color: #94a3b8;">No se registraron ventas de vendedores en el periodo</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- DESGLOSE DE RESEÑAS Y LOGÍSTICA --}}
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                <div class="section-heading">Desglose de Calificaciones de Clientes (CSAT)</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Puntuación</th>
                            <th class="text-right">Cantidad Reseñas</th>
                            <th class="text-right">% Satisfacción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $totalR = max($kpis['total_resenas'], 1); @endphp
                        @foreach ([5 => '★★★★★ (Excelente)', 4 => '★★★★☆ (Muy Bueno)', 3 => '★★★☆☆ (Regular)', 2 => '★★☆☆☆ (Malo)', 1 => '★☆☆☆☆ (Muy Malo)'] as $estrellas => $lbl)
                            <tr>
                                <td>{{ $lbl }}</td>
                                <td class="text-right font-bold">{{ $distribucion_estrellas[$estrellas] ?? 0 }}</td>
                                <td class="text-right">{{ round((($distribucion_estrellas[$estrellas] ?? 0) / $totalR) * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </td>

            <td style="width: 50%; vertical-align: top;">
                <div class="section-heading">Estado de Órdenes de Entrega y Delivery</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estado Operativo</th>
                            <th class="text-right">Envíos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong style="color: #16a34a;">Entregados con Éxito</strong></td>
                            <td class="text-right font-bold">{{ $resumen_entregas['completadas'] }}</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #0284c7;">En Ruta / En Camino</strong></td>
                            <td class="text-right font-bold">{{ $resumen_entregas['en_camino'] }}</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #f59e0b;">Pendientes de Despacho</strong></td>
                            <td class="text-right font-bold">{{ $resumen_entregas['pendientes'] }}</td>
                        </tr>
                        <tr>
                            <td><strong style="color: #dc2626;">Fallidos / Devueltos</strong></td>
                            <td class="text-right font-bold">{{ $resumen_entregas['fallidas'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        MundiToys S.R.L. &bull; Evaluación de Metas Comerciales y Servicio al Cliente &bull; Generado con DomPDF
    </div>

</body>
</html>

