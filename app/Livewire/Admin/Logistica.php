<?php

namespace App\Livewire\Admin;

use App\Actions\Reportes\GenerarReporteExcelAction;
use App\Actions\Reportes\GenerarReportePdfAction;
use App\Models\Entrega;
use App\Models\User;
use App\Services\LogisticaEntregaService;
use App\Services\LogisticaHorarioService;
use App\Services\ReporteService;
use DomainException;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Logística y Envíos - MundiToys')]
class Logistica extends Component
{
    use WithPagination;

    public string $fechaFiltro = '';

    public string $filtroEstado = 'todos';

    public string $busqueda = '';

    #[Url(as: 'vista', except: 'tablero')]
    public string $pestanaActiva = 'tablero';

    // Parámetros y Filtros de Reportes de Logística
    public string $reportePeriodoPreset = 'este_mes';

    public string $reporteFechaDesde = '';

    public string $reporteFechaHasta = '';

    public string $reporteChoferId = 'todos';

    public string $reporteEstadoEntrega = 'todos';

    public string $reporteTurnoHorario = 'todos';

    // Modal de Detalle
    public bool $modalDetalleAbierto = false;

    public ?int $entregaDetalleId = null;

    // Modal de Asignación de Horario Matemático
    public bool $modalAsignarAbierto = false;

    public ?int $entregaAsignarId = null;

    public string $asignarFecha = '';

    public string $asignarHoraInicio = '';

    public ?int $asignarEmpleadoId = null;

    public string $asignarNotas = '';

    /** @var array<int, array<string, mixed>> */
    public array $slotsDisponibles = [];

    // Modal de Cancelación con Justificación
    public bool $modalCancelarAbierto = false;

    public ?int $entregaCancelarId = null;

    public string $motivoCancelacion = '';

    // Modal de Confirmación de Entrega
    public bool $modalConfirmarAbierto = false;

    public ?int $entregaConfirmarId = null;

    public string $observacionesEntrega = '';

    // Mensajes de Alerta
    public ?string $mensajeAlerta = null;

    public string $tipoAlerta = 'success';

    public function boot(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario?->can('acceder logistica'),
            403,
            'No tienes autorización para acceder al módulo de logística.'
        );
    }

    public function mount(): void
    {
        $this->fechaFiltro = now()->toDateString();
        $this->asignarFecha = now()->toDateString();
        $this->cambiarPestana($this->pestanaActiva);
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function cambiarFecha(string $fecha): void
    {
        $this->fechaFiltro = $fecha;
        $this->resetPage();
    }

    public function irAHoy(): void
    {
        $this->fechaFiltro = now()->toDateString();
        $this->resetPage();
    }

    public function irAManana(): void
    {
        $this->fechaFiltro = now()->addDay()->toDateString();
        $this->resetPage();
    }

    public function cambiarPestana(string $pestana): void
    {
        $this->pestanaActiva = in_array($pestana, ['tablero', 'mapa', 'completados', 'reportes'], true)
            ? $pestana
            : 'tablero';

        if ($this->pestanaActiva === 'reportes' && empty($this->reporteFechaDesde)) {
            $this->aplicarPresetReporte('este_mes');
        }

        $this->resetPage();
    }

    public function updatedReportePeriodoPreset(string $valor): void
    {
        $this->aplicarPresetReporte($valor);
    }

    public function aplicarPresetReporte(string $preset): void
    {
        $this->reportePeriodoPreset = $preset;

        switch ($preset) {
            case 'hoy':
                $this->reporteFechaDesde = now()->toDateString();
                $this->reporteFechaHasta = now()->toDateString();
                break;
            case 'ultimos_7_dias':
                $this->reporteFechaDesde = now()->subDays(6)->toDateString();
                $this->reporteFechaHasta = now()->toDateString();
                break;
            case 'ultimos_30_dias':
                $this->reporteFechaDesde = now()->subDays(29)->toDateString();
                $this->reporteFechaHasta = now()->toDateString();
                break;
            case 'este_mes':
                $this->reporteFechaDesde = now()->startOfMonth()->toDateString();
                $this->reporteFechaHasta = now()->toDateString();
                break;
            case 'este_ano':
                $this->reporteFechaDesde = now()->startOfYear()->toDateString();
                $this->reporteFechaHasta = now()->toDateString();
                break;
            case 'personalizado':
                break;
        }
    }

    public function reiniciarFiltrosReporte(): void
    {
        $this->aplicarPresetReporte('este_mes');
        $this->reporteChoferId = 'todos';
        $this->reporteEstadoEntrega = 'todos';
        $this->reporteTurnoHorario = 'todos';
    }

    public function exportarReportePdf(GenerarReportePdfAction $pdfAction, ReporteService $service): mixed
    {
        $filtros = [
            'fecha_desde' => $this->reporteFechaDesde ?: now()->startOfMonth()->toDateString(),
            'fecha_hasta' => $this->reporteFechaHasta ?: now()->toDateString(),
            'chofer_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
            'empleado_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
            'estado_entrega' => $this->reporteEstadoEntrega !== 'todos' ? $this->reporteEstadoEntrega : null,
            'turno_horario' => $this->reporteTurnoHorario !== 'todos' ? $this->reporteTurnoHorario : null,
        ];

        $datos = $service->obtenerReporteLogistica($filtros);
        $pdf = $pdfAction->execute('logistica', $datos);
        $fecha = now()->format('Ymd_His');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "munditoys_reporte_logistica_{$fecha}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function exportarReporteExcel(GenerarReporteExcelAction $excelAction, ReporteService $service): mixed
    {
        $filtros = [
            'fecha_desde' => $this->reporteFechaDesde ?: now()->startOfMonth()->toDateString(),
            'fecha_hasta' => $this->reporteFechaHasta ?: now()->toDateString(),
            'chofer_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
            'empleado_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
            'estado_entrega' => $this->reporteEstadoEntrega !== 'todos' ? $this->reporteEstadoEntrega : null,
            'turno_horario' => $this->reporteTurnoHorario !== 'todos' ? $this->reporteTurnoHorario : null,
        ];

        $datos = $service->obtenerReporteLogistica($filtros);
        $fecha = now()->format('Ymd_His');

        return response()->streamDownload(
            fn () => print ($excelAction->render('logistica', $datos)),
            "munditoys_reporte_logistica_{$fecha}.xls",
            [
                'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"munditoys_reporte_logistica_{$fecha}.xls\"",
            ]
        );
    }

    public function abrirDetalle(int $id): void
    {
        $this->entregaDetalleId = $id;
        $this->modalDetalleAbierto = true;
    }

    public function cerrarModalDetalle(): void
    {
        $this->modalDetalleAbierto = false;
        $this->entregaDetalleId = null;
    }

    public function abrirAsignar(int $id, LogisticaHorarioService $horarioService): void
    {
        $entrega = Entrega::query()->with(['direccion'])->findOrFail($id);

        $this->entregaAsignarId = $id;
        $this->asignarFecha = $entrega->fecha_programada
            ? $entrega->fecha_programada->toDateString()
            : ($this->fechaFiltro ?: now()->toDateString());

        $this->asignarHoraInicio = $entrega->hora_inicio
            ? substr((string) $entrega->hora_inicio, 0, 5)
            : '12:00';

        $this->asignarEmpleadoId = $entrega->empleado_id;
        $this->asignarNotas = (string) ($entrega->notas_despacho ?? '');

        $this->actualizarSlots($horarioService);
        $this->modalAsignarAbierto = true;
    }

    public function updatedAsignarFecha(string $valor, LogisticaHorarioService $horarioService): void
    {
        $this->actualizarSlots($horarioService);
    }

    public function updatedAsignarEmpleadoId(?int $valor, LogisticaHorarioService $horarioService): void
    {
        $this->actualizarSlots($horarioService);
    }

    public function seleccionarSlot(string $horaInicio): void
    {
        $this->asignarHoraInicio = $horaInicio;
    }

    protected function actualizarSlots(LogisticaHorarioService $horarioService): void
    {
        if (empty($this->asignarFecha)) {
            $this->asignarFecha = now()->toDateString();
        }

        $this->slotsDisponibles = $horarioService->obtenerDisponibilidad(
            fecha: $this->asignarFecha,
            empleadoId: $this->asignarEmpleadoId,
            ignorarEntregaId: $this->entregaAsignarId
        );
    }

    public function guardarAsignacion(LogisticaEntregaService $entregaService): void
    {
        $this->validate([
            'asignarFecha' => ['required', 'date'],
            'asignarHoraInicio' => ['required', 'string', 'regex:/^(12:00|13:30|15:00|16:30|18:00)$/'],
            'asignarEmpleadoId' => ['nullable', 'exists:users,id'],
            'asignarNotas' => ['nullable', 'string', 'max:500'],
        ], [
            'asignarFecha.required' => 'Debes indicar una fecha para la entrega.',
            'asignarFecha.date' => 'El formato de fecha no es válido.',
            'asignarHoraInicio.required' => 'Debes seleccionar un turno de entrega.',
            'asignarHoraInicio.regex' => 'El turno debe ser un horario oficial de logística (12:00, 13:30, 15:00, 16:30 o 18:00).',
            'asignarEmpleadoId.exists' => 'El repartidor seleccionado no existe en el sistema.',
            'asignarNotas.max' => 'Las notas de despacho no pueden superar los 500 caracteres.',
        ]);

        $entrega = Entrega::query()->findOrFail($this->entregaAsignarId);

        try {
            $entregaService->asignarHorarioYEmpleado(
                entrega: $entrega,
                fecha: $this->asignarFecha,
                horaInicio: $this->asignarHoraInicio,
                empleadoId: $this->asignarEmpleadoId,
                notas: $this->asignarNotas
            );

            $this->mostrarAlerta('success', "Turno y repartidor asignados exitosamente para el pedido {$entrega->venta?->numero_venta}.");
            $this->cerrarModalAsignar();
        } catch (DomainException $e) {
            $this->addError('asignarHoraInicio', $e->getMessage());
        }
    }

    public function cerrarModalAsignar(): void
    {
        $this->modalAsignarAbierto = false;
        $this->entregaAsignarId = null;
        $this->asignarFecha = '';
        $this->asignarHoraInicio = '';
        $this->asignarEmpleadoId = null;
        $this->asignarNotas = '';
        $this->slotsDisponibles = [];
        $this->resetErrorBag();
    }

    public function iniciarRuta(int $id, LogisticaEntregaService $entregaService): void
    {
        $entrega = Entrega::query()->findOrFail($id);

        try {
            $entregaService->marcarEnCamino($entrega);
            $this->mostrarAlerta('info', "El pedido #{$entrega->venta?->numero_venta} ha salido a ruta de reparto.");
        } catch (DomainException $e) {
            $this->mostrarAlerta('danger', $e->getMessage());
        }
    }

    public function abrirConfirmarEntrega(int $id): void
    {
        $this->entregaConfirmarId = $id;
        $this->observacionesEntrega = '';
        $this->modalConfirmarAbierto = true;
    }

    public function guardarConfirmacionEntrega(LogisticaEntregaService $entregaService): void
    {
        $this->validate([
            'observacionesEntrega' => ['nullable', 'string', 'max:300'],
        ], [
            'observacionesEntrega.max' => 'Las observaciones no pueden superar los 300 caracteres.',
        ]);

        $entrega = Entrega::query()->findOrFail($this->entregaConfirmarId);

        try {
            $entregaService->marcarEntregado($entrega, $this->observacionesEntrega);
            $this->mostrarAlerta('success', "¡Entrega confirmada con éxito para el pedido #{$entrega->venta?->numero_venta}!");
            $this->cerrarModalConfirmar();
        } catch (DomainException $e) {
            $this->mostrarAlerta('danger', $e->getMessage());
        }
    }

    public function cerrarModalConfirmar(): void
    {
        $this->modalConfirmarAbierto = false;
        $this->entregaConfirmarId = null;
        $this->observacionesEntrega = '';
        $this->resetErrorBag();
    }

    public function abrirCancelar(int $id): void
    {
        $this->entregaCancelarId = $id;
        $this->motivoCancelacion = '';
        $this->modalCancelarAbierto = true;
    }

    public function guardarCancelacion(LogisticaEntregaService $entregaService): void
    {
        $this->validate([
            'motivoCancelacion' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'motivoCancelacion.required' => 'Debes ingresar obligatoriamente el motivo de cancelación.',
            'motivoCancelacion.min' => 'El motivo debe tener al menos 10 caracteres para justificar la cancelación ante el Administrador.',
            'motivoCancelacion.max' => 'El motivo no puede superar los 500 caracteres.',
        ]);

        $entrega = Entrega::query()->findOrFail($this->entregaCancelarId);

        try {
            $entregaService->cancelarEntrega(
                entrega: $entrega,
                motivo: $this->motivoCancelacion,
                usuarioCancelador: auth()->user()
            );

            $this->mostrarAlerta('warning', "Se canceló la entrega del pedido #{$entrega->venta?->numero_venta}. Se envió la notificación de alerta al Administrador.");
            $this->cerrarModalCancelar();
        } catch (InvalidArgumentException|DomainException $e) {
            $this->addError('motivoCancelacion', $e->getMessage());
        }
    }

    public function cerrarModalCancelar(): void
    {
        $this->modalCancelarAbierto = false;
        $this->entregaCancelarId = null;
        $this->motivoCancelacion = '';
        $this->resetErrorBag();
    }

    protected function mostrarAlerta(string $tipo, string $mensaje): void
    {
        $this->tipoAlerta = $tipo;
        $this->mensajeAlerta = $mensaje;
    }

    public function descartarAlerta(): void
    {
        $this->mensajeAlerta = null;
    }

    public function render(): View
    {
        // 1. Métricas del día seleccionado
        $metricasQuery = Entrega::query()->whereDate('fecha_programada', $this->fechaFiltro);

        $conteoTotal = (clone $metricasQuery)->count();
        $conteoPendientes = (clone $metricasQuery)->where('estado', 'pendiente')->count();
        $conteoEnCamino = (clone $metricasQuery)->where('estado', 'en_camino')->count();
        $conteoEntregados = (clone $metricasQuery)->where('estado', 'entregado')->count();
        $conteoCancelados = (clone $metricasQuery)->where('estado', 'cancelado')->count();

        // 2. Consulta de Entregas según la pestaña activa
        $query = Entrega::query()
            ->with([
                'venta.cliente',
                'venta.detalles.producto.imagenes',
                'direccion',
                'empleado',
                'sucursal',
            ]);

        if ($this->pestanaActiva === 'completados') {
            $query->where('estado', 'entregado')->latest('entregado_at');
        } else {
            $query->whereDate('fecha_programada', $this->fechaFiltro);

            if ($this->filtroEstado !== 'todos') {
                $query->where('estado', $this->filtroEstado);
            }

            $query->orderBy('hora_inicio')->latest('id');
        }

        // Búsqueda
        if (! empty($this->busqueda)) {
            $termino = trim($this->busqueda);
            $query->where(function ($q) use ($termino): void {
                $q->whereHas('venta', fn ($v) => $v->where('numero_venta', 'like', "%{$termino}%"))
                    ->orWhereHas('venta.cliente', fn ($c) => $c->where('name', 'like', "%{$termino}%")->orWhere('telefono', 'like', "%{$termino}%"))
                    ->orWhereHas('direccion', fn ($d) => $d->where('direccion_completa', 'like', "%{$termino}%")->orWhere('zona', 'like', "%{$termino}%"));
            });
        }

        $entregas = $query->paginate(10);

        // 3. Puntos de entrega geolocalizados para el mapa en La Paz
        $puntosMapa = Entrega::query()
            ->with(['venta.cliente', 'direccion', 'empleado'])
            ->whereDate('fecha_programada', $this->fechaFiltro)
            ->whereNotNull('direccion_id')
            ->whereHas('direccion', fn ($d) => $d->whereNotNull('latitud')->whereNotNull('longitud'))
            ->get()
            ->map(fn (Entrega $e) => [
                'id' => $e->id,
                'numero_venta' => $e->venta?->numero_venta ?? 'S/N',
                'cliente' => $e->venta?->cliente?->name ?? 'Cliente',
                'telefono' => $e->venta?->cliente?->telefono ?? 'S/T',
                'direccion' => $e->direccion?->direccion_completa ?? '',
                'zona' => $e->direccion?->zona ?? '',
                'lat' => (float) $e->direccion?->latitud,
                'lng' => (float) $e->direccion?->longitud,
                'hora_inicio' => $e->hora_inicio ? substr((string) $e->hora_inicio, 0, 5) : '--:--',
                'hora_fin' => $e->hora_fin ? substr((string) $e->hora_fin, 0, 5) : '--:--',
                'estado' => $e->estado,
                'repartidor' => $e->empleado?->name ?? 'Sin asignar',
            ])
            ->values()
            ->toArray();

        // 4. Empleados para selector de chofer / logística
        $empleados = User::query()
            ->where('estado', true)
            ->whereHas('roles', fn ($r) => $r->whereIn('name', ['Logistica', 'Administrador', 'Vendedor']))
            ->orderBy('name')
            ->get(['id', 'name', 'apellido_paterno', 'email']);

        // 5. Datos para el modal de detalle si está abierto
        $entregaDetalle = null;
        if ($this->modalDetalleAbierto && $this->entregaDetalleId) {
            $entregaDetalle = Entrega::query()
                ->with([
                    'venta.cliente',
                    'venta.detalles.producto.imagenes',
                    'direccion',
                    'empleado',
                    'sucursal',
                ])
                ->find($this->entregaDetalleId);
        }

        // 6. Datos del Reporte de Logística si la pestaña activa es 'reportes'
        $datosReporte = null;
        $opcionesReporte = null;
        if ($this->pestanaActiva === 'reportes') {
            $reporteService = app(ReporteService::class);
            if (empty($this->reporteFechaDesde)) {
                $this->aplicarPresetReporte('este_mes');
            }
            $filtrosReporte = [
                'fecha_desde' => $this->reporteFechaDesde,
                'fecha_hasta' => $this->reporteFechaHasta,
                'chofer_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
                'empleado_id' => $this->reporteChoferId !== 'todos' ? (int) $this->reporteChoferId : null,
                'estado_entrega' => $this->reporteEstadoEntrega !== 'todos' ? $this->reporteEstadoEntrega : null,
                'turno_horario' => $this->reporteTurnoHorario !== 'todos' ? $this->reporteTurnoHorario : null,
            ];
            $datosReporte = $reporteService->obtenerReporteLogistica($filtrosReporte);
            $opcionesReporte = $reporteService->obtenerOpcionesFiltros();
        }

        return view('livewire.admin.logistica', compact(
            'entregas',
            'conteoTotal',
            'conteoPendientes',
            'conteoEnCamino',
            'conteoEntregados',
            'conteoCancelados',
            'puntosMapa',
            'empleados',
            'entregaDetalle',
            'datosReporte',
            'opcionesReporte'
        ));
    }
}
