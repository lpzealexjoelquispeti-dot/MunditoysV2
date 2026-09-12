<?php

namespace App\Actions\Entregas;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class GenerarFichaEntregaPdfAction
{
    /**
     * Genera un comprobante / ficha de entrega en formato PDF con estilo factura.
     */
    public function execute(Venta $venta): DomPdfWrapper
    {
        $venta->loadMissing([
            'cliente',
            'pago',
            'entrega.sucursal',
            'entrega.empleado',
            'detalles.producto.categoria',
        ]);

        return Pdf::loadView('entregas.pdf.ficha-entrega', [
            'venta' => $venta,
            'entrega' => $venta->entrega,
            'cliente' => $venta->cliente,
            'pago' => $venta->pago,
            'detalles' => $venta->detalles,
            'sucursal' => $venta->entrega?->sucursal,
            'empleado' => $venta->entrega?->empleado,
            'fechaEmision' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}
