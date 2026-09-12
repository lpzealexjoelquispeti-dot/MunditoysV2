<?php

namespace App\Livewire\Admin;

use App\Actions\Reportes\GenerarReporteExcelAction;
use App\Actions\Reportes\GenerarReportePdfAction;
use App\Services\ReporteService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Centro de Reportes y Analítica - MundiToys')]
class Reportes extends Component
{
    #[Url]
    public string $seccion = 'ventas';

    public string $periodoPreset = 'este_mes';

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public string $sucursalId = 'todos';

    public string $categoriaId = 'todos';

    public string $tipoVenta = 'todos';

    public string $metodoPago = 'todos';

    public string $vendedorId = 'todos';

    public string $tipoInteraccion = 'todos';

    public string $estadoStock = 'todos';

    public function mount(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole('Administrador') || $usuario->can('administrar accesos')),
            403,
            'No tienes autorización para consultar los reportes ejecutivos.'
        );

        if (request()->query('seccion') && in_array(request()->query('seccion'), ['ventas', 'interacciones', 'inventario', 'comercial'])) {
            $this->seccion = request()->query('seccion');
        }

        $this->aplicarPreset('este_mes');
    }

    public function updatedPeriodoPreset(string $valor): void
    {
        $this->aplicarPreset($valor);
    }

    public function cambiarSeccion(string $seccion): void
    {
        if (in_array($seccion, ['ventas', 'interacciones', 'inventario', 'comercial'])) {
            $this->seccion = $seccion;
        }
    }

    public function aplicarPreset(string $preset): void
    {
        $this->periodoPreset = $preset;

        switch ($preset) {
            case 'hoy':
                $this->fechaDesde = now()->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'ultimos_7_dias':
                $this->fechaDesde = now()->subDays(6)->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'ultimos_30_dias':
                $this->fechaDesde = now()->subDays(29)->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'este_mes':
                $this->fechaDesde = now()->startOfMonth()->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'mes_anterior':
                $this->fechaDesde = now()->subMonth()->startOfMonth()->toDateString();
                $this->fechaHasta = now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'este_ano':
                $this->fechaDesde = now()->startOfYear()->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'personalizado':
                // Conservar fechas actuales ingresadas manualmente por el usuario
                break;
        }
    }

    public function reiniciarFiltros(): void
    {
        $this->sucursalId = 'todos';
        $this->categoriaId = 'todos';
        $this->tipoVenta = 'todos';
        $this->metodoPago = 'todos';
        $this->vendedorId = 'todos';
        $this->tipoInteraccion = 'todos';
        $this->estadoStock = 'todos';
        $this->aplicarPreset('este_mes');
    }

    protected function recopilarFiltros(): array
    {
        return [
            'fecha_desde' => $this->fechaDesde,
            'fecha_hasta' => $this->fechaHasta,
            'sucursal_id' => $this->sucursalId !== 'todos' ? (int) $this->sucursalId : null,
            'categoria_id' => $this->categoriaId !== 'todos' ? (int) $this->categoriaId : null,
            'tipo_venta' => $this->tipoVenta !== 'todos' ? $this->tipoVenta : null,
            'metodo_pago' => $this->metodoPago !== 'todos' ? $this->metodoPago : null,
            'usuario_vendedor_id' => $this->vendedorId !== 'todos' ? (int) $this->vendedorId : null,
            'vendedor_id' => $this->vendedorId !== 'todos' ? (int) $this->vendedorId : null,
            'tipo_interaccion' => $this->tipoInteraccion !== 'todos' ? $this->tipoInteraccion : null,
            'estado_stock' => $this->estadoStock !== 'todos' ? $this->estadoStock : null,
        ];
    }

    public function exportarPdf(ReporteService $service, GenerarReportePdfAction $pdfAction): mixed
    {
        $filtros = $this->recopilarFiltros();
        $datos = $this->obtenerDatosSeccion($service, $filtros);

        $pdf = $pdfAction->execute($this->seccion, $datos);
        $fecha = now()->format('Ymd_His');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "munditoys_reporte_{$this->seccion}_{$fecha}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportarExcel(ReporteService $service, GenerarReporteExcelAction $excelAction): mixed
    {
        $filtros = $this->recopilarFiltros();
        $datos = $this->obtenerDatosSeccion($service, $filtros);
        $fecha = now()->format('Ymd_His');

        return response()->streamDownload(
            fn () => print ($excelAction->render($this->seccion, $datos)),
            "munditoys_reporte_{$this->seccion}_{$fecha}.xls",
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"munditoys_reporte_{$this->seccion}_{$fecha}.xls\"",
            ]
        );
    }

    protected function obtenerDatosSeccion(ReporteService $service, array $filtros): array
    {
        return match ($this->seccion) {
            'ventas' => $service->obtenerReporteVentas($filtros),
            'interacciones' => $service->obtenerReporteInteracciones($filtros),
            'inventario' => $service->obtenerReporteInventario($filtros),
            'comercial' => $service->obtenerReporteRendimientoComercial($filtros),
            default => $service->obtenerReporteVentas($filtros),
        };
    }

    public function render(ReporteService $service): View
    {
        $filtros = $this->recopilarFiltros();
        $opciones = $service->obtenerOpcionesFiltros();
        $datos = $this->obtenerDatosSeccion($service, $filtros);

        return view('livewire.admin.reportes', [
            'opciones' => $opciones,
            'datos' => $datos,
        ]);
    }
}
