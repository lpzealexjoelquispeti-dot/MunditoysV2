<?php

namespace App\Actions\Reportes;

use Illuminate\Http\Response;
use InvalidArgumentException;

class GenerarReporteExcelAction
{
    /**
     * Renderiza la plantilla HTML enriquecida con estilos nativos para hojas de cálculo.
     */
    public function render(string $tipoReporte, array $datos): string
    {
        $vista = match ($tipoReporte) {
            'ventas' => 'reportes.excel.ventas-excel',
            'interacciones' => 'reportes.excel.interacciones-excel',
            'inventario' => 'reportes.excel.inventario-excel',
            'comercial' => 'reportes.excel.comercial-excel',
            'logistica' => 'reportes.excel.logistica-excel',
            default => throw new InvalidArgumentException("Tipo de reporte inválido: {$tipoReporte}"),
        };

        return view($vista, $datos)->render();
    }

    /**
     * Genera una respuesta de descarga con formato Excel (.xls).
     */
    public function execute(string $tipoReporte, array $datos): Response
    {
        $html = $this->render($tipoReporte, $datos);
        $fecha = now()->format('Ymd_His');
        $nombreArchivo = "munditoys_reporte_{$tipoReporte}_{$fecha}.xls";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
