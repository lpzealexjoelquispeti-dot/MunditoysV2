<?php

namespace App\Actions\Ventas;

use App\Actions\Inventario\DescontarStockFifoAction;
use App\Models\Almacen;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcesarVentaPosAction
{
    public function __construct(
        private DescontarStockFifoAction $descontarStockFifoAction
    ) {}

    /**
     * Procesa una venta física de mostrador garantizando el descuento FIFO por lotes y registro atómico.
     *
     * @param  array<int, array{producto_id: int, cantidad: int, precio_unitario: float}>  $items
     * @return array{venta: Venta, recibo: array<string, mixed>}
     */
    public function ejecutar(
        int $usuarioVendedorId,
        int $sucursalId,
        ?int $almacenId,
        ?int $clienteId,
        array $items,
        string $metodoPago,
        float $montoRecibido = 0.0,
        ?string $referenciaPago = null,
        ?string $stripePaymentId = null,
        ?string $nombreClienteMostrador = null,
        ?string $ciNitClienteMostrador = null
    ): array {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'carrito' => 'El carrito de ventas está vacío. Agregue al menos un producto.',
            ]);
        }

        return DB::transaction(function () use (
            $usuarioVendedorId,
            $sucursalId,
            $almacenId,
            $clienteId,
            $items,
            $metodoPago,
            $montoRecibido,
            $referenciaPago,
            $stripePaymentId,
            $nombreClienteMostrador,
            $ciNitClienteMostrador
        ): array {
            $sucursal = Sucursal::query()->findOrFail($sucursalId);
            $vendedor = User::query()->findOrFail($usuarioVendedorId);
            $cliente = $clienteId ? User::query()->find($clienteId) : null;

            // Calcular montos
            $total = 0.0;
            foreach ($items as $it) {
                $cant = (int) ($it['cantidad'] ?? 0);
                $precio = (float) ($it['precio_unitario'] ?? 0);
                if ($cant <= 0 || $precio < 0) {
                    throw ValidationException::withMessages([
                        'carrito' => 'Los productos en el carrito deben tener cantidad mayor a cero.',
                    ]);
                }
                $total += round($cant * $precio, 2);
            }
            $total = round($total, 2);

            // Validar efectivo
            if ($metodoPago === 'efectivo') {
                if ($montoRecibido < $total) {
                    $faltante = number_format($total - $montoRecibido, 2);
                    throw ValidationException::withMessages([
                        'montoRecibido' => "El monto entregado en efectivo (Bs. {$montoRecibido}) no cubre el total de Bs. {$total} (Faltan Bs. {$faltante}).",
                    ]);
                }
            }

            $cambio = $metodoPago === 'efectivo' ? max(0, round($montoRecibido - $total, 2)) : 0.0;

            // Generar número correlativo de venta
            $fechaPrefijo = now()->format('Ymd');
            $conteoHoy = Venta::query()->whereDate('fecha_venta', now()->toDateString())->count() + 1;
            $numeroVenta = sprintf('VTA-POS-%s-%04d', $fechaPrefijo, $conteoHoy);

            // Crear la Venta cabecera
            $venta = Venta::query()->create([
                'numero_venta' => $numeroVenta,
                'cliente_id' => $cliente?->id,
                'usuario_vendedor_id' => $usuarioVendedorId,
                'tipo_venta' => 'presencial',
                'tipo_entrega' => 'inmediata',
                'estado' => 'completada',
                'subtotal' => $total,
                'descuento' => 0.00,
                'total' => $total,
                'fecha_venta' => now(),
            ]);

            $detalleReciboItems = [];

            // Descontar inventario FIFO y registrar detalles
            foreach ($items as $item) {
                $producto = Producto::query()->findOrFail($item['producto_id']);
                $cantidadPedida = (int) $item['cantidad'];
                $precioUnitario = (float) $item['precio_unitario'];

                // Localizar el registro de Inventario en la sucursal actual
                $inventarioQuery = Inventario::query()
                    ->where('producto_id', $producto->id)
                    ->whereHas('almacen', function ($q) use ($sucursalId, $almacenId): void {
                        $q->where('sucursal_id', $sucursalId);
                        if ($almacenId) {
                            $q->where('id', $almacenId);
                        }
                    });

                $inventario = $inventarioQuery->first();

                if (! $inventario) {
                    // Si no existe aún en el almacén de mostrador, intentar crearlo en el almacén principal de la sucursal
                    $almacenDestino = $almacenId
                        ? Almacen::query()->findOrFail($almacenId)
                        : Almacen::query()->where('sucursal_id', $sucursalId)->where('estado', true)->first();

                    if (! $almacenDestino) {
                        throw ValidationException::withMessages([
                            'sucursalId' => "La sucursal {$sucursal->nombre} no tiene un almacén habilitado para despacho.",
                        ]);
                    }

                    $inventario = Inventario::query()->firstOrCreate(
                        ['producto_id' => $producto->id, 'almacen_id' => $almacenDestino->id],
                        ['stock_minimo' => 5]
                    );
                }

                // Ejecutar descuento FIFO
                try {
                    $afectados = $this->descontarStockFifoAction->ejecutar(
                        $inventario,
                        $cantidadPedida,
                        "Venta de Mostrador POS {$numeroVenta}",
                        $usuarioVendedorId
                    );
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'carrito' => "Stock insuficiente de '{$producto->nombre}' en la sucursal {$sucursal->nombre}.",
                    ]);
                }

                // Registrar en detalle_ventas por cada lote descontado
                foreach ($afectados as $loteAfectado) {
                    DetalleVenta::query()->create([
                        'venta_id' => $venta->id,
                        'producto_id' => $producto->id,
                        'lote_id' => $loteAfectado['lote_id'],
                        'cantidad' => $loteAfectado['descontado'],
                        'precio_unitario' => $precioUnitario,
                        'subtotal' => round($loteAfectado['descontado'] * $precioUnitario, 2),
                    ]);
                }

                $detalleReciboItems[] = [
                    'producto' => $producto->nombre,
                    'codigo' => $producto->codigo_producto,
                    'cantidad' => $cantidadPedida,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => round($cantidadPedida * $precioUnitario, 2),
                ];
            }

            // Registrar Pago
            $referenciaFinal = match ($metodoPago) {
                'efectivo' => 'Efectivo Recibido: Bs. '.number_format($montoRecibido, 2).' | Vuelto: Bs. '.number_format($cambio, 2),
                'efectivo' => 'Efectivo Recibido: Bs. '.number_format($montoRecibido, 2).' | Vuelto: Bs. '.number_format($cambio, 2),
                'qr' => $referenciaPago ?: 'QR-BCB-'.strtoupper(Str::random(8)),
                'tarjeta' => $referenciaPago ?: 'POS-VOUCHER-'.strtoupper(Str::random(8)),
                'stripe' => $referenciaPago ?: ($stripePaymentId ?: 'STRIPE-'.strtoupper(Str::random(8))),
                default => $referenciaPago ?? 'PAGO-POS',
            };

            $pago = Pago::query()->create([
                'venta_id' => $venta->id,
                'metodo_pago' => $metodoPago,
                'referencia_pago' => $referenciaFinal,
                'stripe_payment_id' => $stripePaymentId,
                'estado' => 'completado',
                'monto' => $total,
                'fecha_pago' => now(),
            ]);

            $nombreCliente = $cliente
                ? trim("{$cliente->name} {$cliente->apellido_paterno} {$cliente->apellido_materno}")
                : ($nombreClienteMostrador ?: 'Consumidor Final / Mostrador');

            $ciNitCliente = $cliente
                ? ($cliente->ci_nit ?: '0')
                : ($ciNitClienteMostrador ?: '0');

            $recibo = [
                'numero_venta' => $numeroVenta,
                'fecha_hora' => now()->format('d/m/Y H:i:s'),
                'sucursal_nombre' => $sucursal->nombre,
                'sucursal_direccion' => $sucursal->direccion ?? 'MundiToys S.R.L.',
                'sucursal_ciudad' => $sucursal->ciudad ?? 'Bolivia',
                'sucursal_telefono' => $sucursal->telefono ?? '2-2440000',
                'vendedor_nombre' => $vendedor->name,
                'cliente_nombre' => $nombreCliente,
                'cliente_ci_nit' => $ciNitCliente,
                'items' => $detalleReciboItems,
                'subtotal' => $total,
                'descuento' => 0.00,
                'total' => $total,
                'metodo_pago' => $metodoPago,
                'monto_recibido' => $montoRecibido,
                'cambio' => $cambio,
                'referencia_pago' => $referenciaFinal,
            ];

            return [
                'venta' => $venta,
                'recibo' => $recibo,
            ];
        });
    }
}
