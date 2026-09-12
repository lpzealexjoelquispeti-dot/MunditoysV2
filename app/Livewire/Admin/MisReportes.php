<?php

namespace App\Livewire\Admin;

use App\Actions\Reportes\GenerarReporteVendedorExcelAction;
use App\Actions\Reportes\GenerarReporteVendedorPdfAction;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\ReporteVendedorService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Mis Reportes - Personal de Ventas - MundiToys')]
class MisReportes extends Component
{
    public string $seccion = 'ventas_fisicas';

    public string $periodoPreset = 'este_mes';

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    public ?int $sucursalId = null;

    public ?int $vendedorId = null;

    /**
     * Reglas de validación lógica de parámetros de fechas en español.
     *
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'fechaDesde' => [
                'required',
                'date',
                'before_or_equal:fechaHasta',
                'before_or_equal:today',
            ],
            'fechaHasta' => [
                'required',
                'date',
                'after_or_equal:fechaDesde',
                'before_or_equal:today',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados y amigables en español.
     *
     * @return array<string, string>
     */
    protected function messages(): array
    {
        $hoy = now()->format('d/m/Y');

        return [
            'fechaDesde.required' => 'Debes ingresar una fecha de inicio para el reporte.',
            'fechaDesde.date' => 'La fecha de inicio no corresponde a un formato de fecha válido.',
            'fechaDesde.before_or_equal' => "La fecha de inicio no puede ser posterior a la fecha final ni puede superar el día de hoy ({$hoy}).",
            'fechaHasta.required' => 'Debes ingresar una fecha de término para el reporte.',
            'fechaHasta.date' => 'La fecha de término no corresponde a un formato de fecha válido.',
            'fechaHasta.after_or_equal' => 'La fecha final no puede ser anterior a la fecha inicial.',
            'fechaHasta.before_or_equal' => "La fecha final no puede ser posterior al día de hoy ({$hoy}). No es posible consultar fechas futuras.",
        ];
    }

    public function mount(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario && ($usuario->hasRole(['Vendedor', 'Administrador']) || $usuario->can('crear ventas')),
            403,
            'No tienes autorización para consultar los reportes de ventas.'
        );

        $this->vendedorId = (int) $usuario->id;

        if ($usuario->hasRole('Vendedor') && $usuario->sucursal_id) {
            $this->sucursalId = (int) $usuario->sucursal_id;
        } else {
            $this->sucursalId = (int) ($usuario->sucursal_id ?? Sucursal::query()->where('activo', true)->value('id') ?? 1);
        }

        $this->aplicarPreset('este_mes');
    }

    public function updatedFechaDesde(): void
    {
        $this->periodoPreset = 'personalizado';
        $this->validate();
    }

    public function updatedFechaHasta(): void
    {
        $this->periodoPreset = 'personalizado';
        $this->validate();
    }

    public function updatedPeriodoPreset(string $valor): void
    {
        $this->aplicarPreset($valor);
    }

    public function cambiarSeccion(string $seccion): void
    {
        if (in_array($seccion, ['ventas_fisicas', 'recojos_sucursal'])) {
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
            case 'este_mes':
                $this->fechaDesde = now()->startOfMonth()->toDateString();
                $this->fechaHasta = now()->toDateString();
                break;
            case 'mes_anterior':
                $this->fechaDesde = now()->subMonth()->startOfMonth()->toDateString();
                $this->fechaHasta = now()->subMonth()->endOfMonth()->toDateString();
                break;
            case 'personalizado':
                break;
        }

        $this->resetValidation();
    }

    public function exportarPdf(
        ReporteVendedorService $service,
        GenerarReporteVendedorPdfAction $pdfAction
    ): StreamedResponse {
        $this->validate();

        $datos = $this->obtenerDatosReporte($service);
        $pdf = $pdfAction->execute($this->seccion, $datos);

        $nombreArchivo = "munditoys_mis_reportes_{$this->seccion}_".now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $nombreArchivo,
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportarExcel(
        ReporteVendedorService $service,
        GenerarReporteVendedorExcelAction $excelAction
    ): StreamedResponse {
        $this->validate();

        $datos = $this->obtenerDatosReporte($service);
        $html = $excelAction->render($this->seccion, $datos);

        $nombreArchivo = "munditoys_mis_reportes_{$this->seccion}_".now()->format('Ymd_His').'.xls';

        return response()->streamDownload(
            fn () => print ($html),
            $nombreArchivo,
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function obtenerDatosReporte(ReporteVendedorService $service): array
    {
        $sucursalId = (int) ($this->sucursalId ?? 1);
        $vendedorId = (int) ($this->vendedorId ?? auth()->id());

        if ($this->seccion === 'ventas_fisicas') {
            return $service->obtenerReporteVentasFisicas(
                $vendedorId,
                $sucursalId,
                $this->fechaDesde,
                $this->fechaHasta
            );
        }

        return $service->obtenerReporteRecojosSucursal(
            $sucursalId,
            $vendedorId,
            $this->fechaDesde,
            $this->fechaHasta
        );
    }

    public function render(ReporteVendedorService $service): View
    {
        $sucursalActual = Sucursal::query()->find($this->sucursalId);
        $vendedorActual = User::query()->find($this->vendedorId);

        // Si hay errores de validación de fechas, no cargamos datos pesados
        $datos = [];
        $esValido = ! $this->getErrorBag()->has('fechaDesde') && ! $this->getErrorBag()->has('fechaHasta');

        if ($esValido) {
            $datos = $this->obtenerDatosReporte($service);
        }

        return view('livewire.admin.mis-reportes', [
            'datos' => $datos,
            'esValido' => $esValido,
            'sucursalActual' => $sucursalActual,
            'vendedorActual' => $vendedorActual,
        ]);
    }
}
