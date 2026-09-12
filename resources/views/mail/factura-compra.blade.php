<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Compra - MundiToys</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #005ca8 0%, #003e73 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .brand {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }
        .brand span {
            color: #f97316;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .card {
            background-color: #f1f5f9;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e2e8f0;
        }
        .card-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .card-row:last-child {
            margin-bottom: 0;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            font-size: 16px;
            font-weight: bold;
            color: #005ca8;
        }
        .qr-box {
            text-align: center;
            background-color: #eff6ff;
            border: 2px dashed #005ca8;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
        }
        .qr-img {
            width: 160px;
            height: 160px;
            display: inline-block;
            border-radius: 8px;
            background: white;
            padding: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .instructions {
            font-size: 13px;
            color: #475569;
            line-height: 1.6;
            margin: 16px 0;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            background-color: #ea580c;
            color: #ffffff !important;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 50px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <div class="brand">Mundi<span>Toys</span> ★</div>
            <div style="font-size: 14px; opacity: 0.9;">¡Tu pedido online está confirmado!</div>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <div class="greeting">¡Hola, {{ $facturaNombre ?: ($venta->cliente?->name ?? 'Apreciado Cliente') }}! 🎉</div>
            <p style="color: #475569; font-size: 14px; line-height: 1.5;">
                Hemos procesado tu pago de manera exitosa mediante la pasarela segura Stripe. Tu compra se encuentra registrada y lista para el recojo en tienda física.
            </p>

            <!-- RESUMEN -->
            <div class="card">
                <div class="card-row">
                    <span style="color: #64748b;">N° de Venta:</span>
                    <strong style="color: #005ca8;">{{ $venta->numero_venta }}</strong>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">Sucursal de Recojo:</span>
                    <strong>{{ $sucursalNombre ?? ($venta->entrega?->sucursal?->nombre ?? 'Sucursal MundiToys') }}</strong>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">Dirección:</span>
                    <span>{{ $sucursalDireccion ?? ($venta->entrega?->sucursal?->direccion ?? 'Av. Principal') }}</span>
                </div>
                <div class="card-row">
                    <span style="color: #64748b;">Método de Pago:</span>
                    <span>Stripe (Tarjeta / Online)</span>
                </div>
                <div class="card-row">
                    <span>Total Pagado:</span>
                    <span>Bs. {{ number_format($venta->total, 2) }}</span>
                </div>
            </div>

            <!-- QR DE RECOJO -->
            <div class="qr-box">
                <div style="font-size: 15px; font-weight: bold; color: #005ca8; margin-bottom: 6px;">
                    Código QR para Recojo en Sucursal
                </div>
                <p style="font-size: 12px; color: #64748b; margin-top: 0; margin-bottom: 12px;">
                    Muestra este código QR o tu documento de identidad (CI) al encargado de sucursal:
                </p>
                @if($qrDataUri)
                    <img src="{{ $qrDataUri }}" alt="QR de Recojo" class="qr-img">
                @endif
                <div style="font-size: 11px; font-family: monospace; color: #475569; margin-top: 8px;">
                    {{ $venta->numero_venta }}
                </div>
            </div>

            <!-- FACTURA ADJUNTA -->
            <div class="instructions">
                <strong>📎 Factura Electrónica Oficial Adjunta:</strong><br>
                Hemos emitido tu factura fiscal con todos los datos legales y la hemos adjuntado a este correo en formato PDF (<strong>Factura_{{ $venta->numero_venta }}.pdf</strong>). Puedes imprimirla o guardarla para tus registros contables.
            </div>

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ route('tienda.seguimiento') }}" class="btn">
                    Ver Estado de mi Pedido
                </a>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            MundiToys Bolivia S.R.L. · La juguetería más mágica de Bolivia<br>
            Si tienes dudas o necesitas asistencia, contáctanos a soporte@munditoys.bo
        </div>
    </div>
</body>
</html>

