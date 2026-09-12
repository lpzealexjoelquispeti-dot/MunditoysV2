<?php

namespace App\Actions\Entregas;

use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\ResenaCompraService;
use DomainException;
use Illuminate\Support\Facades\DB;

class ConfirmarEntregaSucursalAction
{
    /**
     * Confirma la entrega física de un pedido de recojo en sucursal.
     *
     * @throws DomainException
     */
    public function execute(Venta $venta, User $empleado, bool $qrValidado = false): Venta
    {
        if ($venta->tipo_entrega !== 'recojo_tienda') {
            throw new DomainException('Solo es posible confirmar entregas de pedidos con recojo en tienda.');
        }

        if ($venta->estado === 'cancelada') {
            throw new DomainException('No se puede entregar un pedido que ha sido cancelado.');
        }

        $entrega = $venta->entrega;
        if (! $entrega) {
            throw new DomainException('El pedido no cuenta con un registro de entrega asociado.');
        }

        if ($entrega->estado === 'entregado') {
            throw new DomainException('Este pedido ya ha sido entregado anteriormente.');
        }

        if (! $qrValidado) {
            throw new DomainException('No es posible marcar como entregado sin antes escanear y validar el código QR presentado por el cliente (desde su correo Gmail, factura PDF o comprobante en pantalla).');
        }

        // Si el empleado es vendedor y tiene sucursal asignada, verificar coincidencia
        if ($empleado->hasRole('Vendedor') && $empleado->sucursal_id && $entrega->sucursal_origen_id) {
            if ($empleado->sucursal_id !== $entrega->sucursal_origen_id) {
                $nombreSucursal = $entrega->sucursal?->nombre ?? 'otra sucursal';
                throw new DomainException("No puedes entregar un pedido asignado a la sucursal [{$nombreSucursal}].");
            }
        }

        return DB::transaction(function () use ($venta, $entrega, $empleado) {
            $entrega->update([
                'estado' => 'entregado',
                'entregado_at' => now(),
                'empleado_id' => $empleado->id,
                'hora_fin' => now()->format('H:i:s'),
                'fecha_programada' => $entrega->fecha_programada ?? now()->toDateString(),
            ]);

            $venta->update([
                'estado' => 'completada',
            ]);

            app(ResenaCompraService::class)->notificar($venta);

            if ($venta->cliente_id) {
                Notificacion::create([
                    'usuario_id' => $venta->cliente_id,
                    'titulo' => "¡Pedido #{$venta->numero_venta} entregado!",
                    'mensaje' => 'Tu pedido con recojo en sucursal ha sido entregado exitosamente. ¡Gracias por confiar en MundiToys!',
                    'leido' => false,
                ]);
            }

            return $venta->fresh(['entrega.empleado', 'entrega.sucursal', 'cliente', 'pago', 'detalles.producto']);
        });
    }
}
