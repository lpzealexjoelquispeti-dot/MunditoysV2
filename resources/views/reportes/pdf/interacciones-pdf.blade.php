<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Interacciones, Vistas y Topics IA - MundiToys</title>
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
            border-bottom: 2px solid #0284c7;
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
            border-top: 3px solid #0284c7;
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
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 4px;
        }
        .kpi-badge {
            font-size: 8px;
            font-weight: 600;
            color: #0284c7;
            margin-top: 2px;
        }
        .section-heading {
            font-size: 11px;
            font-weight: 700;
            color: #0284c7;
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
            background-color: #0284c7;
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
                <div class="report-subtitle">Inteligencia de Catálogo, Experiencia Visual 3D y Conversaciones IA</div>
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                <h1 class="report-title">Interacciones y Topics IA</h1>
                <div class="report-subtitle">Emisión: {{ now()->format('d/m/Y H:i:s') }} | Auditoría: {{ auth()->user()?->name ?? 'Administrador' }}</div>
            </td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td class="meta-label">Periodo:</td>
            <td class="meta-value">{{ $filtros_aplicados['fecha_desde'] }} al {{ $filtros_aplicados['fecha_hasta'] }}</td>
            <td class="meta-label">Filtro Interacción:</td>
            <td class="meta-value">{{ ucfirst($filtros_aplicados['tipo_interaccion']) }}</td>
        </tr>
        <tr>
            <td class="meta-label">Categoría:</td>
            <td class="meta-value">{{ $filtros_aplicados['categoria'] }}</td>
            <td class="meta-label">Total Eventos:</td>
            <td class="meta-value">{{ number_format($kpis['total_interacciones']) }} registros analizados</td>
        </tr>
    </table>

    {{-- KPIS EJECUTIVOS --}}
    <table class="kpi-container" style="width: 100%; border-spacing: 6px; border-collapse: separate;">
        <tr>
            <td class="kpi-card" style="border-top-color: #0284c7; width: 16%;">
                <div class="kpi-title">Vistas de Catálogo</div>
                <div class="kpi-number">{{ number_format($kpis['total_vistas_producto']) }}</div>
                <div class="kpi-badge">Impresiones Detalle</div>
            </td>
            <td class="kpi-card" style="border-top-color: #8b5cf6; width: 16%;">
                <div class="kpi-title">Visor 3D Interactivo</div>
                <div class="kpi-number" style="color: #8b5cf6;">{{ number_format($kpis['total_interacciones_3d']) }}</div>
                <div class="kpi-badge" style="color: #8b5cf6;">Modelos Tripo Explorados</div>
            </td>
            <td class="kpi-card" style="border-top-color: #16a34a; width: 17%;">
                <div class="kpi-title">Agregados al Carrito</div>
                <div class="kpi-number" style="color: #16a34a;">{{ number_format($kpis['total_agregados_carrito']) }}</div>
                <div class="kpi-badge">Intención de Compra</div>
            </td>
            <td class="kpi-card" style="border-top-color: #ea580c; width: 17%;">
                <div class="kpi-title">Conversión Visual</div>
                <div class="kpi-number" style="color: #ea580c;">{{ $kpis['tasa_conversion_visual'] }}%</div>
                <div class="kpi-badge">Vistas a Carrito</div>
            </td>
            <td class="kpi-card" style="border-top-color: #06b6d4; width: 17%;">
                <div class="kpi-title">Sesiones Asistente IA</div>
                <div class="kpi-number">{{ number_format($kpis['total_conversaciones_ia']) }}</div>
                <div class="kpi-badge">Chats Iniciados</div>
            </td>
            <td class="kpi-card" style="border-top-color: #6366f1; width: 17%;">
                <div class="kpi-title">Mensajes IA Totales</div>
                <div class="kpi-number">{{ number_format($kpis['total_mensajes_ia']) }}</div>
                <div class="kpi-badge">Respuestas Generadas</div>
            </td>
        </tr>
    </table>

    {{-- TOP JUGUETES Y TOPICS IA --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="width: 55%; vertical-align: top; padding-right: 10px;">
                <div class="section-heading">Top 10 Juguetes con Mayor Popularidad e Interés</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Juguete</th>
                            <th>Categoría</th>
                            <th class="text-center">Vistas</th>
                            <th class="text-center">3D Clics</th>
                            <th class="text-center">Carritos</th>
                            <th class="text-right">Total Eventos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($top_juguetes as $tj)
                            <tr>
                                <td><strong>{{ $tj['nombre'] }}</strong></td>
                                <td>{{ $tj['categoria'] }}</td>
                                <td class="text-center">{{ $tj['vistas'] }}</td>
                                <td class="text-center font-bold" style="color: #8b5cf6;">{{ $tj['interacciones_3d'] }}</td>
                                <td class="text-center font-bold" style="color: #16a34a;">{{ $tj['carritos'] }}</td>
                                <td class="text-right font-bold">{{ $tj['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center" style="color: #94a3b8;">No se registraron interacciones de juguetes en el periodo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>

            <td style="width: 45%; vertical-align: top;">
                <div class="section-heading">Topics Recurrentes Consultados a la IA</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tema / Pregunta Frecuente</th>
                            <th class="text-right">Frecuencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topicos_ia as $tema => $frec)
                            <tr>
                                <td>{{ $tema }}</td>
                                <td class="text-right font-bold">{{ $frec }} consultas</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center" style="color: #94a3b8;">Sin conversaciones con la IA registradas</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="section-heading">Términos de Búsqueda Más Frecuentes</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Término Buscado</th>
                            <th class="text-right">Apariciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($top_busquedas as $query => $frec)
                            <tr>
                                <td><em>"{{ $query }}"</em></td>
                                <td class="text-right font-bold">{{ $frec }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-center" style="color: #94a3b8;">Sin términos de búsqueda en el rango</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        MundiToys S.R.L. &bull; Módulo de Business Intelligence y Analítica Predictiva &bull; Generado con DomPDF
    </div>

</body>
</html>

