<?php

namespace App\Services;

use App\Models\Entrega;
use App\Models\Notificacion;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LogisticaEntregaService
{
    public function __construct(
        protected LogisticaHorarioService $horarioService
    ) {}

    /**
     * Asigna fecha, turno matemático y chofer a una entrega.
     *
     * @throws DomainException
     */
    public function asignarHorarioYEmpleado(
        Entrega $entrega,
        string $fecha,
        string $horaInicio,
        ?int $empleadoId = null,
        ?string $notas = null
    ): Entrega {
        if ($entrega->estado === 'entregado') {
            throw new DomainException('No se puede reprogramar una entrega que ya fue marcada como entregada.');
        }

        $validacion = $this->horarioService->validarSlot(
            fecha: $fecha,
            horaInicio: $horaInicio,
            empleadoId: $empleadoId,
            ignorarEntregaId: $entrega->id
        );

        if (! $validacion['valido']) {
            throw new DomainException($validacion['mensaje']);
        }

        $horaFin = $this->horarioService->calcularHoraFin($horaInicio);

        $entrega->update([
            'fecha_programada' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'empleado_id' => $empleadoId,
            'notas_despacho' => $notas ?? $entrega->notas_despacho,
            'estado' => $entrega->estado === 'cancelado' ? 'pendiente' : $entrega->estado,
            'motivo_cancelacion' => $entrega->estado === 'cancelado' ? null : $entrega->motivo_cancelacion,
            'cancelado_at' => $entrega->estado === 'cancelado' ? null : $entrega->cancelado_at,
        ]);

        return $entrega->fresh(['venta.cliente', 'empleado', 'direccion', 'sucursal']);
    }

    /**
     * Pasa el despacho a estado "en_camino".
     *
     * @throws DomainException
     */
    public function marcarEnCamino(Entrega $entrega): Entrega
    {
        //
        if ($entrega->estado === 'entregado') {
            throw new DomainException('Esta entrega ya fue completada exitosamente.');
        }

        if ($entrega->estado === 'cancelado') {
            throw new DomainException('No se puede despachar un pedido cancelado. Asígnale un nuevo turno primero.');
        }

        $entrega->update([
            'estado' => 'en_camino',
        ]);

        return $entrega->fresh();
    }

    /**
     * Confirma la recepción exitosa del pedido.
     *
     * @throws DomainException
     */
    public function marcarEntregado(Entrega $entrega, ?string $observaciones = null): Entrega
    {
        if ($entrega->estado === 'entregado') {
            throw new DomainException('La entrega ya se encuentra registrada como entregada.');
        }

        if ($entrega->estado === 'cancelado') {
            throw new DomainException('No se puede marcar como entregado un pedido que figura cancelado.');
        }

        return DB::transaction(function () use ($entrega, $observaciones): Entrega {
            $entrega = Entrega::query()->lockForUpdate()->findOrFail($entrega->id);
            if (in_array($entrega->estado, ['entregado', 'cancelado'], true) || $entrega->venta?->estado === 'cancelada') {
                throw new DomainException('El pedido ya fue entregado o cancelado. Actualiza el listado.');
            }
            $entrega->update([
                'estado' => 'entregado',
                'entregado_at' => now(),
                'notas_despacho' => $observaciones ? trim(($entrega->notas_despacho ? $entrega->notas_despacho.' | ' : '').$observaciones) : $entrega->notas_despacho,
            ]);
            $entrega->venta->update(['estado' => 'completada']);
            app(ResenaCompraService::class)->notificar($entrega->venta);

            return $entrega->fresh();
        });
    }

    /**
     * Cancela la entrega registrando obligatoriamente el motivo y notificando a los administradores.
     *
     * @throws InvalidArgumentException
     * @throws DomainException
     */
    public function cancelarEntrega(Entrega $entrega, string $motivo, User $usuarioCancelador): Entrega
    {
        $motivoLimpio = trim($motivo);

        if (mb_strlen($motivoLimpio) < 10) {
            throw new InvalidArgumentException('El motivo de cancelación debe tener al menos 10 caracteres para justificar la incidencia.');
        }

        if ($entrega->estado === 'entregado') {
            throw new DomainException('No se puede cancelar una entrega que ya fue completada exitosamente.');
        }

        return DB::transaction(function () use ($entrega, $motivoLimpio, $usuarioCancelador): Entrega {
            $entrega->update([
                'estado' => 'cancelado',
                'motivo_cancelacion' => $motivoLimpio,
                'cancelado_at' => now(),
            ]);

            $entrega->loadMissing(['venta.cliente']);
            $numeroVenta = $entrega->venta?->numero_venta ?? 'N/A';
            $fechaProg = $entrega->fecha_programada ? $entrega->fecha_programada->format('d/m/Y') : 'Sin fecha';
            $horaProg = $entrega->hora_inicio ? substr((string) $entrega->hora_inicio, 0, 5) : 'Sin hora';

            // Notificar a todos los administradores del sistema
            $administradores = User::role('Administrador')->where('estado', true)->get();

            foreach ($administradores as $admin) {
                Notificacion::query()->create([
                    'usuario_id' => $admin->id,
                    'titulo' => "⚠️ Envío Cancelado: {$numeroVenta}",
                    'mensaje' => "El despacho del pedido #{$numeroVenta} programado para el {$fechaProg} a las {$horaProg} fue cancelado por {$usuarioCancelador->name}. Motivo reportado: \"{$motivoLimpio}\".",
                    'leido' => false,
                ]);
            }

            return $entrega->fresh();
        });
    }
}
