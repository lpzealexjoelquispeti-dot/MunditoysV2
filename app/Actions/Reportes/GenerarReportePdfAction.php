<?php

namespace App\Actions\Reportes;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use InvalidArgumentException;

class GenerarReportePdfAction
{
    /**
     * Genera un objeto PDF configurado en orientación horizontal con DomPDF.
     *
     * @param  string  $tipoReporte  ('ventas', 'interacciones', 'inventario', 'comercial')
     * @param  array  $datos
     */
    public function execute(string $tipoReporte, array $datos): DomPdfWrapper
    {
        $vista = match ($tipoReporte) {
            'ventas' => 'reportes.pdf.ventas-pdf',
            'interacciones' => 'reportes.pdf.interacciones-pdf',
            'inventario' => 'reportes.pdf.inventario-pdf',
            'comercial' => 'reportes.pdf.comercial-pdf',
            default => throw new InvalidArgumentException("Tipo de reporte inválido: {$tipoReporte}"),
        };

        return Pdf::loadView($vista, $datos)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);
    }
}

