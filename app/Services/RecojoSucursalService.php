<?php

namespace App\Services;

use App\Models\Sucursal;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class RecojoSucursalService
{
    /**
     * Obtiene los pedidos online con recojo en tienda asignados a una sucursal específica.
     *
     * @return LengthAwarePaginator<Venta>
     */
    public function obtenerPedidosSucursal(
        int $sucursalId,
        string $filtroEstado = 'pendientes',
        ?string $busqueda = null,
        int $porPagina = 10
    ): LengthAwarePaginator {
        $query = Venta::query()
            ->with([
                'cliente',
                'pago',
                'entrega.sucursal',
                'entrega.empleado',
                'detalles.producto.imagenes',
                'detalles.producto.categoria',
            ])
            ->where('tipo_entrega', 'recojo_tienda')
            ->whereHas('entrega', fn (Builder $q) => $q->where('sucursal_origen_id', $sucursalId));

        if ($filtroEstado === 'pendientes') {
            $query->whereHas('entrega', fn (Builder $q) => $q->where('estado', '!=', 'entregado'))
                ->where('estado', '!=', 'cancelada');
        } elseif ($filtroEstado === 'entregados') {
            $query->whereHas('entrega', fn (Builder $q) => $q->where('estado', 'entregado'));
        }

        if (! empty($busqueda)) {
            $termino = trim($busqueda);
            $query->where(function (Builder $q) use ($termino) {
                $q->where('numero_venta', 'like', "%{$termino}%")
                    ->orWhereHas('cliente', function (Builder $cq) use ($termino) {
                        $cq->where('name', 'like', "%{$termino}%")
                            ->orWhere('email', 'like', "%{$termino}%")
                            ->orWhere('telefono', 'like', "%{$termino}%")
                            ->orWhere('ci_nit', 'like', "%{$termino}%");
                    });
            });
        }

        return $query->orderBy('fecha_venta', 'desc')->paginate($porPagina);
    }

    /**
     * Busca un pedido de recojo a partir del texto leído por el código QR o ingresado manualmente.
     *
     * @return array{
     *     encontrado: bool,
     *     pedido: ?Venta,
     *     sucursal_coincide: bool,
     *     mensaje: string
     * }
     */
    public function buscarPorCodigoQR(string $codigoBruto, ?int $sucursalUsuarioId = null): array
    {
        $codigoLimpio = trim($codigoBruto);

        if (empty($codigoLimpio)) {
            return [
                'encontrado' => false,
                'pedido' => null,
                'sucursal_coincide' => false,
                'mensaje' => 'No se proporcionó ningún código QR para escanear.',
            ];
        }

        // Extraer número de venta si viene con prefijos comunes
        // ej: "MUNDITOYS-RECOJO:VTA-20260909-0001" o JSON o URL
        $numeroVenta = $codigoLimpio;
        if (str_contains($codigoLimpio, 'MUNDITOYS-RECOJO:')) {
            $numeroVenta = trim(str_replace('MUNDITOYS-RECOJO:', '', $codigoLimpio));
        } elseif (preg_match('/(VTA-[A-Za-z0-9\-]+)/i', $codigoLimpio, $coincidencias)) {
            $numeroVenta = strtoupper($coincidencias[1]);
        }

        $pedido = Venta::query()
            ->with([
                'cliente',
                'pago',
                'entrega.sucursal',
                'entrega.empleado',
                'detalles.producto.imagenes',
                'detalles.producto.categoria',
            ])
            ->where(function (Builder $q) use ($numeroVenta, $codigoLimpio) {
                $q->where('numero_venta', $numeroVenta)
                    ->orWhere('numero_venta', $codigoLimpio);
            })
            ->first();

        if (! $pedido) {
            return [
                'encontrado' => false,
                'pedido' => null,
                'sucursal_coincide' => false,
                'mensaje' => "No se encontró ningún pedido con el código [{$numeroVenta}]. Verifica el comprobante del cliente.",
            ];
        }

        if ($pedido->tipo_entrega !== 'recojo_tienda') {
            return [
                'encontrado' => true,
                'pedido' => $pedido,
                'sucursal_coincide' => false,
                'mensaje' => "El pedido [{$pedido->numero_venta}] es de tipo '{$pedido->tipo_entrega}', no es para recojo en tienda física.",
            ];
        }

        $sucursalOrigenId = $pedido->entrega?->sucursal_origen_id;
        $sucursalCoincide = true;

        if ($sucursalUsuarioId !== null && $sucursalOrigenId !== null && $sucursalOrigenId !== $sucursalUsuarioId) {
            $sucursalCoincide = false;
            $nombreSucursalPedido = $pedido->entrega?->sucursal?->nombre ?? 'Otra sucursal';

            return [
                'encontrado' => true,
                'pedido' => $pedido,
                'sucursal_coincide' => false,
                'mensaje' => "¡Atención! Este pedido corresponde a la sucursal [{$nombreSucursalPedido}]. No corresponde a tu sucursal actual.",
            ];
        }

        return [
            'encontrado' => true,
            'pedido' => $pedido,
            'sucursal_coincide' => $sucursalCoincide,
            'mensaje' => 'Pedido localizado satisfactoriamente.',
        ];
    }

    /**
     * Valida si el código escaneado o ingresado coincide con el QR oficial asignado a la venta.
     */
    public function validarQrParaVenta(Venta $venta, string $codigoBruto): bool
    {
        $codigoLimpio = trim($codigoBruto);

        if (empty($codigoLimpio)) {
            return false;
        }

        // Extraer número de venta si viene con el prefijo "MUNDITOYS-RECOJO:"
        $numeroExtraido = $codigoLimpio;
        if (str_contains($codigoLimpio, 'MUNDITOYS-RECOJO:')) {
            $numeroExtraido = trim(str_replace('MUNDITOYS-RECOJO:', '', $codigoLimpio));
        } elseif (preg_match('/(VTA-[A-Za-z0-9\-]+)/i', $codigoLimpio, $coincidencias)) {
            $numeroExtraido = strtoupper($coincidencias[1]);
        }

        $numeroVentaEsperado = strtoupper($venta->numero_venta);
        $qrCompletoEsperado = "MUNDITOYS-RECOJO:{$numeroVentaEsperado}";

        return strtoupper($numeroExtraido) === $numeroVentaEsperado
            || strtoupper($codigoLimpio) === $qrCompletoEsperado;
    }
}
