<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Producto;
use App\Models\Resena;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResenaCompraService
{
    public function comprasRecibidas(int $usuarioId): Builder
    {
        return Venta::query()->where('cliente_id', $usuarioId)
            ->where('estado', 'completada')
            ->whereHas('pago', fn (Builder $q) => $q->where('estado', 'completado'))
            ->whereHas('entrega', fn (Builder $q) => $q->where('estado', 'entregado'));
    }

    public function puedeResenar(?User $usuario, Producto $producto): bool
    {
        return $usuario !== null && $usuario->estado !== false
            && ! Resena::withTrashed()->where('user_id', $usuario->id)->where('producto_id', $producto->id)->exists()
            && $this->comprasRecibidas($usuario->id)
                ->whereHas('detalles', fn (Builder $q) => $q->where('producto_id', $producto->id)->where('cantidad', '>', 0))->exists();
    }

    public function guardar(User $usuario, Producto $producto, mixed $calificacion, string $comentario): Resena
    {
        $comentario = trim($comentario);
        $datos = Validator::make(compact('calificacion', 'comentario'), [
            'calificacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['required', 'string', 'min:10', 'max:2000', 'not_regex:/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/u'],
        ], [
            'calificacion.required' => 'Selecciona una calificación.',
            'calificacion.integer' => 'La calificación debe ser un número entero.',
            'calificacion.between' => 'La calificación debe estar entre 1 y 5.',
            'comentario.required' => 'Escribe tu opinión sobre el juguete.',
            'comentario.min' => 'La reseña debe tener al menos 10 caracteres.',
            'comentario.max' => 'La reseña no puede superar los 2000 caracteres.',
            'comentario.not_regex' => 'Escribe la reseña como texto, sin etiquetas HTML ni caracteres de control.',
        ])->validate();

        return DB::transaction(function () use ($usuario, $producto, $datos): Resena {
            User::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
            if (! $this->puedeResenar($usuario->fresh(), $producto)) {
                throw ValidationException::withMessages(['resena' => 'Solo puedes reseñar una vez cada juguete comprado, después de confirmar su entrega y completar la compra.']);
            }
            $venta = $this->comprasRecibidas($usuario->id)
                ->whereHas('detalles', fn (Builder $q) => $q->where('producto_id', $producto->id)->where('cantidad', '>', 0))
                ->lockForUpdate()->firstOrFail();

            return Resena::query()->create($datos + [
                'user_id' => $usuario->id, 'producto_id' => $producto->id,
                'venta_id' => $venta->id, 'estado' => 'activo',
            ]);
        });
    }

    public function notificar(Venta $venta): void
    {
        if (! $venta->cliente_id || ! $this->comprasRecibidas($venta->cliente_id)->whereKey($venta->id)->exists()) {
            return;
        }
        $venta->loadMissing('detalles.producto');
        foreach ($venta->detalles->unique('producto_id') as $detalle) {
            if (! $detalle->producto || Resena::withTrashed()->where('user_id', $venta->cliente_id)->where('producto_id', $detalle->producto_id)->exists()) {
                continue;
            }
            Notificacion::query()->firstOrCreate([
                'clave_evento' => "resena:{$venta->id}:{$detalle->producto_id}",
            ], [
                'usuario_id' => $venta->cliente_id,
                'producto_id' => $detalle->producto_id,
                'titulo' => 'Ya puedes reseñar '.$detalle->producto->nombre,
                'mensaje' => "Recibiste tu pedido {$venta->numero_venta}. Abre los detalles del juguete y comparte tu opinión.",
                'leido' => false,
            ]);
        }
    }
}
