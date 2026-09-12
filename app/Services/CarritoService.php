<?php

namespace App\Services;

use App\Models\Carrito;
use App\Models\CarritoDetalle;
use App\Models\Producto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CarritoService
{
    /**
     * Agrega un producto al carrito de compras con la cantidad solicitada.
     */
    public function agregar(Producto $producto, int $cantidad = 1): void
    {
        $cantidad = max(1, $cantidad);
        $precio = (float) ($producto->precio_venta ?? 0);

        if (Auth::check()) {
            $carrito = Carrito::query()->firstOrCreate(
                ['user_id' => Auth::id(), 'estado' => 'activo'],
                ['tipo_compra' => 'online']
            );

            /** @var CarritoDetalle|null $detalle */
            $detalle = $carrito->detalles()->where('producto_id', $producto->id)->first();

            if ($detalle) {
                $detalle->update([
                    'cantidad' => $detalle->cantidad + $cantidad,
                    'precio_unitario' => $precio,
                ]);
            } else {
                $carrito->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                ]);
            }
        } else {
            $items = session()->get('carrito_items', []);
            $id = $producto->id;

            if (isset($items[$id])) {
                $items[$id]['cantidad'] += $cantidad;
                $items[$id]['precio_unitario'] = $precio;
            } else {
                $items[$id] = [
                    'producto_id' => $id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                ];
            }

            session()->put('carrito_items', $items);
        }
    }

    /**
     * Retorna el número total de unidades en el carrito.
     */
    public function totalArticulos(): int
    {
        if (Auth::check()) {
            $carrito = Carrito::query()->where('user_id', Auth::id())->where('estado', 'activo')->first();
            if (! $carrito) {
                return 0;
            }

            return (int) $carrito->detalles()->sum('cantidad');
        }

        $items = session()->get('carrito_items', []);

        return (int) array_sum(array_column($items, 'cantidad'));
    }

    /**
     * Retorna la lista detallada de items del carrito con sus modelos de producto.
     *
     * @return Collection<int, array{id: int|string, producto_id: int, producto: Producto, cantidad: int, precio_unitario: float, subtotal: float}>
     */
    public function obtenerItems(): Collection
    {
        if (Auth::check()) {
            $carrito = Carrito::query()
                ->with(['detalles.producto.imagenes', 'detalles.producto.categoria', 'detalles.producto.marca'])
                ->where('user_id', Auth::id())
                ->where('estado', 'activo')
                ->first();

            if (! $carrito) {
                return collect();
            }

            return $carrito->detalles->map(function (CarritoDetalle $d): array {
                $pu = (float) $d->precio_unitario;
                $sub = $pu * $d->cantidad;

                return [
                    'id' => $d->id,
                    'producto_id' => $d->producto_id,
                    'producto' => $d->producto,
                    'cantidad' => $d->cantidad,
                    'precio_unitario' => $pu,
                    'subtotal' => $sub,
                ];
            });
        }

        $items = session()->get('carrito_items', []);
        if (empty($items)) {
            return collect();
        }

        $productos = Producto::query()
            ->with(['imagenes', 'categoria', 'marca'])
            ->whereIn('id', array_keys($items))
            ->get()
            ->keyBy('id');

        $resultado = collect();

        foreach ($items as $pId => $item) {
            $prod = $productos->get($pId);
            if (! $prod) {
                continue;
            }

            $pu = (float) $item['precio_unitario'];
            $cant = (int) $item['cantidad'];

            $resultado->push([
                'id' => $pId,
                'producto_id' => $pId,
                'producto' => $prod,
                'cantidad' => $cant,
                'precio_unitario' => $pu,
                'subtotal' => $pu * $cant,
            ]);
        }

        return $resultado;
    }

    /**
     * Retorna el monto total en Bs del carrito.
     */
    public function montoTotal(): float
    {
        return (float) $this->obtenerItems()->sum('subtotal');
    }

    /**
     * Elimina un producto del carrito.
     */
    public function eliminar(int $productoId): void
    {
        if (Auth::check()) {
            $carrito = Carrito::query()->where('user_id', Auth::id())->where('estado', 'activo')->first();
            if ($carrito) {
                $carrito->detalles()->where('producto_id', $productoId)->delete();
            }
        } else {
            $items = session()->get('carrito_items', []);
            unset($items[$productoId]);
            session()->put('carrito_items', $items);
        }
    }

    /**
     * Actualiza la cantidad de un producto en el carrito.
     */
    public function actualizarCantidad(int $productoId, int $nuevaCantidad): void
    {
        if ($nuevaCantidad <= 0) {
            $this->eliminar($productoId);

            return;
        }

        if (Auth::check()) {
            $carrito = Carrito::query()->where('user_id', Auth::id())->where('estado', 'activo')->first();
            if ($carrito) {
                $carrito->detalles()->where('producto_id', $productoId)->update(['cantidad' => $nuevaCantidad]);
            }
        } else {
            $items = session()->get('carrito_items', []);
            if (isset($items[$productoId])) {
                $items[$productoId]['cantidad'] = $nuevaCantidad;
                session()->put('carrito_items', $items);
            }
        }
    }

    /**
     * Vacía completamente los productos del carrito de compras.
     */
    public function vaciar(): void
    {
        if (Auth::check()) {
            $carrito = Carrito::query()->where('user_id', Auth::id())->where('estado', 'activo')->first();
            if ($carrito) {
                $carrito->detalles()->delete();
            }
        } else {
            session()->forget('carrito_items');
        }
    }
}
