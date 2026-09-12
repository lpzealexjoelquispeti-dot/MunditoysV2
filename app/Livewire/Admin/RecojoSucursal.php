<?php

namespace App\Livewire\Admin;

use App\Actions\Entregas\ConfirmarEntregaSucursalAction;
use App\Actions\Entregas\GenerarFichaEntregaPdfAction;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\RecojoSucursalService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Recojo en Sucursal - MundiToys')]
class RecojoSucursal extends Component
{
    use WithPagination;

    public ?int $sucursalId = null;

    public string $filtroEstado = 'pendientes';

    public string $busqueda = '';

    public string $codigoQR = '';

    public ?int $pedidoSeleccionadoId = null;

    public ?string $mensajeAlerta = null;

    public string $tipoAlerta = 'info';

    public bool $modalCamaraAbierto = false;

    public bool $qrValidado = false;

    public ?int $pedidoQrValidadoId = null;

    public string $codigoQrModal = '';

    public ?string $mensajeValidacionQr = null;

    public ?string $codigoQrTextoValidado = null;

    public function boot(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario?->can('gestionar recojos'),
            403,
            'No tienes autorización para gestionar recojos en sucursal.'
        );
    }

    public function mount(): void
    {
        $usuario = auth()->user();
        if ($usuario->hasRole('Vendedor') && $usuario->sucursal_id) {
            $this->sucursalId = (int) $usuario->sucursal_id;
        } else {
            $this->sucursalId = (int) ($usuario->sucursal_id ?? Sucursal::query()->where('activo', true)->value('id') ?? Sucursal::query()->value('id') ?? 1);
        }
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function cambiarFiltro(string $filtro): void
    {
        if (in_array($filtro, ['pendientes', 'entregados', 'todos'])) {
            $this->filtroEstado = $filtro;
            $this->resetPage();
        }
    }

    public function abrirCamara(): void
    {
        $this->modalCamaraAbierto = true;
        $this->mensajeAlerta = null;
    }

    public function cerrarCamara(): void
    {
        $this->modalCamaraAbierto = false;
    }

    public function seleccionarPedido(int $ventaId): void
    {
        $this->pedidoSeleccionadoId = $ventaId;
        $this->mensajeAlerta = null;
        $this->codigoQrModal = '';
        $this->mensajeValidacionQr = null;

        // Si no se validó previamente con el escáner para este pedido exacto, exigir validación
        if ($this->pedidoQrValidadoId !== $ventaId) {
            $this->qrValidado = false;
            $this->codigoQrTextoValidado = null;
        }
    }

    public function cerrarDetalle(): void
    {
        $this->pedidoSeleccionadoId = null;
        $this->mensajeAlerta = null;
        $this->codigoQrModal = '';
        $this->mensajeValidacionQr = null;
        $this->qrValidado = false;
        $this->pedidoQrValidadoId = null;
        $this->codigoQrTextoValidado = null;
    }

    public function procesarCodigoQR(RecojoSucursalService $service): void
    {
        $this->mensajeAlerta = null;

        if (empty(trim($this->codigoQR))) {
            $this->tipoAlerta = 'warning';
            $this->mensajeAlerta = 'Por favor ingresa o escanea un código de pedido válido.';

            return;
        }

        $resultado = $service->buscarPorCodigoQR($this->codigoQR, $this->sucursalId);

        if (! $resultado['encontrado']) {
            $this->tipoAlerta = 'error';
            $this->mensajeAlerta = $resultado['mensaje'];

            return;
        }

        $this->pedidoSeleccionadoId = $resultado['pedido']->id;
        $this->modalCamaraAbierto = false;

        if (! $resultado['sucursal_coincide']) {
            $this->tipoAlerta = 'warning';
            $this->mensajeAlerta = $resultado['mensaje'];
            $this->qrValidado = false;
            $this->pedidoQrValidadoId = null;
            $this->codigoQrTextoValidado = null;
        } else {
            $this->tipoAlerta = 'success';
            $this->mensajeAlerta = '¡Código QR verificado con éxito! Revisa los detalles del pedido y procede con la entrega.';
            $this->qrValidado = true;
            $this->pedidoQrValidadoId = $resultado['pedido']->id;
            $this->codigoQrTextoValidado = trim($this->codigoQR);
            $this->mensajeValidacionQr = '¡Código QR validado! El cliente presentó el comprobante correcto.';
        }

        $this->codigoQR = '';
    }

    public function procesarCodigoEscaneado(string $codigo, RecojoSucursalService $service): void
    {
        $this->codigoQR = trim($codigo);
        $this->procesarCodigoQR($service);
    }

    public function simularEscaneoQR(?string $numeroVenta = null, ?RecojoSucursalService $service = null): void
    {
        $service = $service ?? app(RecojoSucursalService::class);

        if ($numeroVenta) {
            $this->codigoQR = $numeroVenta;
        } else {
            // Buscar un pedido pendiente de la sucursal actual para simular
            $pedidoPendiente = Venta::query()
                ->where('tipo_entrega', 'recojo_tienda')
                ->whereHas('entrega', fn ($q) => $q->where('sucursal_origen_id', $this->sucursalId)->where('estado', '!=', 'entregado'))
                ->latest('fecha_venta')
                ->first();

            if (! $pedidoPendiente) {
                // Si no hay en esta sucursal, buscar cualquier pedido de recojo existente
                $pedidoPendiente = Venta::query()
                    ->where('tipo_entrega', 'recojo_tienda')
                    ->latest()
                    ->first();
            }

            if (! $pedidoPendiente) {
                $this->tipoAlerta = 'warning';
                $this->mensajeAlerta = 'No existen pedidos online con recojo en tienda disponibles en el sistema para simular.';

                return;
            }

            $this->codigoQR = $pedidoPendiente->numero_venta;
        }

        $this->procesarCodigoQR($service);
    }

    public function validarQrPedidoModal(RecojoSucursalService $service): void
    {
        $this->mensajeValidacionQr = null;

        if (! $this->pedidoSeleccionadoId) {
            return;
        }

        $venta = Venta::query()->with('entrega')->find($this->pedidoSeleccionadoId);
        if (! $venta) {
            $this->tipoAlerta = 'error';
            $this->mensajeAlerta = 'El pedido no se encuentra disponible.';

            return;
        }

        if (empty(trim($this->codigoQrModal))) {
            $this->tipoAlerta = 'warning';
            $this->mensajeValidacionQr = 'Por favor escanea o ingresa el código QR del cliente.';

            return;
        }

        $esValido = $service->validarQrParaVenta($venta, $this->codigoQrModal);

        if (! $esValido) {
            $this->qrValidado = false;
            $this->pedidoQrValidadoId = null;
            $this->codigoQrTextoValidado = null;
            $this->tipoAlerta = 'error';
            $this->mensajeValidacionQr = "El código ingresado [{$this->codigoQrModal}] no coincide con este pedido (#{$venta->numero_venta}). Solicita al cliente que muestre el código QR de su correo Gmail, factura o pantalla de compra.";

            return;
        }

        $this->qrValidado = true;
        $this->pedidoQrValidadoId = $venta->id;
        $this->codigoQrTextoValidado = trim($this->codigoQrModal);
        $this->codigoQrModal = '';
        $this->tipoAlerta = 'success';
        $this->mensajeValidacionQr = '¡Código QR validado exitosamente! El cliente ha acreditado su compra.';
    }

    public function simularQrParaPedidoActual(RecojoSucursalService $service): void
    {
        if (! $this->pedidoSeleccionadoId) {
            return;
        }

        $venta = Venta::query()->find($this->pedidoSeleccionadoId);
        if ($venta) {
            $this->codigoQrModal = "MUNDITOYS-RECOJO:{$venta->numero_venta}";
            $this->validarQrPedidoModal($service);
        }
    }

    public function confirmarEntrega(
        ConfirmarEntregaSucursalAction $action,
        GenerarFichaEntregaPdfAction $pdfAction
    ): void {
        if (! $this->pedidoSeleccionadoId) {
            return;
        }

        $venta = Venta::query()->with('entrega.sucursal')->find($this->pedidoSeleccionadoId);
        if (! $venta) {
            $this->tipoAlerta = 'error';
            $this->mensajeAlerta = 'El pedido seleccionado ya no se encuentra disponible.';

            return;
        }

        // VALIDACIÓN ESTRICTA: No se permite marcar como entregado sin haber validado el código QR
        if (! $this->qrValidado || $this->pedidoQrValidadoId !== $venta->id) {
            $this->tipoAlerta = 'error';
            $this->mensajeAlerta = 'Validación requerida: No puedes marcar como entregado el pedido sin antes escanear y validar el código QR presentado por el cliente (desde su correo Gmail, factura PDF o comprobante en pantalla).';

            return;
        }

        try {
            $action->execute($venta, auth()->user(), qrValidado: true);
            $this->tipoAlerta = 'success';
            $this->mensajeAlerta = "¡Entrega confirmada con éxito! El pedido #{$venta->numero_venta} ha sido entregado al cliente.";
            $this->qrValidado = false;
            $this->pedidoQrValidadoId = null;
            $this->codigoQrTextoValidado = null;
            $this->mensajeValidacionQr = null;
        } catch (DomainException $e) {
            $this->tipoAlerta = 'error';
            $this->mensajeAlerta = $e->getMessage();
        }
    }

    public function descargarFichaPdf(int $ventaId, GenerarFichaEntregaPdfAction $pdfAction): StreamedResponse
    {
        $venta = Venta::query()->findOrFail($ventaId);
        $pdf = $pdfAction->execute($venta);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "ficha_entrega_{$venta->numero_venta}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render(RecojoSucursalService $service): View
    {
        $sucursalActual = Sucursal::query()->find($this->sucursalId);
        $todasSucursales = auth()->user()?->hasRole('Administrador')
            ? Sucursal::query()->where('activo', true)->orderBy('nombre')->get()
            : collect([$sucursalActual])->filter();

        $pedidos = $this->sucursalId
            ? $service->obtenerPedidosSucursal($this->sucursalId, $this->filtroEstado, $this->busqueda, 8)
            : new LengthAwarePaginator([], 0, 8);

        $pedidoDetallado = $this->pedidoSeleccionadoId
            ? Venta::query()
                ->with([
                    'cliente',
                    'pago',
                    'entrega.sucursal',
                    'entrega.empleado',
                    'detalles.producto.imagenes',
                    'detalles.producto.categoria',
                ])
                ->find($this->pedidoSeleccionadoId)
            : null;

        // Métricas rápidas de la sucursal
        $conteoPendientes = $this->sucursalId
            ? Venta::query()
                ->where('tipo_entrega', 'recojo_tienda')
                ->where('estado', '!=', 'cancelada')
                ->whereHas('entrega', fn ($q) => $q->where('sucursal_origen_id', $this->sucursalId)->where('estado', '!=', 'entregado'))
                ->count()
            : 0;

        $conteoEntregadosHoy = $this->sucursalId
            ? Venta::query()
                ->where('tipo_entrega', 'recojo_tienda')
                ->whereHas('entrega', fn ($q) => $q->where('sucursal_origen_id', $this->sucursalId)
                    ->where('estado', 'entregado')
                    ->whereDate('updated_at', now()->toDateString()))
                ->count()
            : 0;

        return view('livewire.admin.recojo-sucursal', [
            'pedidos' => $pedidos,
            'pedidoDetallado' => $pedidoDetallado,
            'sucursalActual' => $sucursalActual,
            'todasSucursales' => $todasSucursales,
            'conteoPendientes' => $conteoPendientes,
            'conteoEntregadosHoy' => $conteoEntregadosHoy,
        ]);
    }
}
