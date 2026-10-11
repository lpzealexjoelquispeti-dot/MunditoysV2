<?php

namespace App\Livewire\Cliente;

use App\Actions\Ventas\GenerarFacturaPdfAction;
use App\Models\Notificacion;
use App\Models\Venta;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Seguimiento y Registro de Compras - MundiToys')]
class SeguimientoRegistro extends Component
{
    use WithPagination;

    #[Url(as: 'vista', except: 'pedidos')]
    public string $pestanaActiva = 'pedidos';

    public string $busqueda = '';

    public ?int $pedidoQrSeleccionadoId = null;

    public bool $qrVisible = true;

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function cambiarPestana(string $pestana): void
    {
        if (in_array($pestana, ['pedidos', 'notificaciones'])) {
            $this->pestanaActiva = $pestana;
            $this->resetPage();
        }
    }

    public function verQr(int $ventaId): void
    {
        $this->pedidoQrSeleccionadoId = $ventaId;
        $this->qrVisible = true;
    }

    public function cerrarQr(): void
    {
        $this->pedidoQrSeleccionadoId = null;
    }

    public function toggleQrVisibilidad(): void
    {
        $this->qrVisible = ! $this->qrVisible;
    }

    public function marcarNotificacionLeida(int $notificacionId): void
    {
        if (Auth::check()) {
            Notificacion::query()
                ->where('id', $notificacionId)
                ->where('usuario_id', Auth::id())
                ->update(['leido' => true]);
            $this->dispatch('notificaciones-actualizadas');
        }
    }

    public function marcarTodasLeidas(): void
    {
        if (Auth::check()) {
            Notificacion::query()
                ->where('usuario_id', Auth::id())
                ->update(['leido' => true]);
            $this->dispatch('notificaciones-actualizadas');
        }
    }

    public function descargarFactura(int $ventaId, GenerarFacturaPdfAction $action): StreamedResponse
    {
        $venta = Venta::query()->findOrFail($ventaId);
        $pdf = $action->execute($venta);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "Factura_{$venta->numero_venta}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function render(QrCodeService $qrCodeService): View
    {
        $usuario = Auth::user();
        $termino = trim($this->busqueda);

        // Consulta de compras / pedidos
        $pedidosQuery = Venta::query()
            ->with([
                'cliente',
                'pago',
                'entrega.sucursal',
                'detalles.producto',
            ]);

        if ($usuario) {
            // Usuario autenticado: ver sus compras
            $pedidosQuery->where(function (Builder $q) use ($usuario, $termino) {
                $q->where('cliente_id', $usuario->id);
                if ($termino !== '') {
                    $q->orWhere('numero_venta', 'like', "%{$termino}%");
                }
            });
        } else {
            // Invitado: filtrar obligatoriamente por búsqueda
            if ($termino !== '') {
                $pedidosQuery->where(function (Builder $q) use ($termino) {
                    $q->where('numero_venta', 'like', "%{$termino}%")
                        ->orWhereHas('cliente', fn ($cq) => $cq->where('ci_nit', 'like', "%{$termino}%"));
                });
            } else {
                $pedidosQuery->whereRaw('1 = 0'); // Vacío hasta que busque
            }
        }

        $pedidos = $pedidosQuery->latest('fecha_venta')->paginate(6);

        // Pedido seleccionado para modal QR
        $pedidoSeleccionado = $this->pedidoQrSeleccionadoId
            ? Venta::query()->with('entrega.sucursal', 'cliente', 'pago')->find($this->pedidoQrSeleccionadoId)
            : null;

        $qrDataUriModal = null;
        if ($pedidoSeleccionado) {
            $codigoQrContenido = "MUNDITOYS-RECOJO:{$pedidoSeleccionado->numero_venta}";
            $qrDataUriModal = $qrCodeService->generarDataUri($codigoQrContenido, 280);
        }

        // Notificaciones
        $notificaciones = collect();
        $totalNoLeidas = 0;
        if ($usuario) {
            $notificaciones = Notificacion::query()
                ->with('producto')
                ->where('usuario_id', $usuario->id)
                ->latest()
                ->paginate(10, pageName: 'notificacionesPagina');

            $totalNoLeidas = Notificacion::query()
                ->where('usuario_id', $usuario->id)
                ->where('leido', false)
                ->count();
        }

        return view('livewire.cliente.seguimiento-registro', [
            'pedidos' => $pedidos,
            'pedidoSeleccionado' => $pedidoSeleccionado,
            'qrDataUriModal' => $qrDataUriModal,
            'notificaciones' => $notificaciones,
            'totalNoLeidas' => $totalNoLeidas,
            'esInvitado' => ! $usuario,
        ]);
    }
}
