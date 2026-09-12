<?php

namespace App\Services;

use App\Models\Entrega;
use Carbon\Carbon;

class LogisticaHorarioService
{
    /**
     * Duración máxima estimada de llegada y entrega en el domicilio del cliente (minutos).
     */
    public const DURACION_ENTREGA_MINUTOS = 40;

    /**
     * Margen de seguridad y retorno/traslado a la sucursal o siguiente punto (minutos).
     */
    public const MARGEN_RETORNO_MINUTOS = 50;

    /**
     * Intervalo total matemático fijo entre inicios de entregas (90 minutos = 1h 30m).
     */
    public const INTERVALO_TOTAL_MINUTOS = 90;

    /**
     * Lista de turnos diarios fijos calculados matemáticamente desde las 12:00.
     *
     * @return array<int, array{inicio: string, fin: string, ventana: string, etiqueta: string}>
     */
    public function obtenerTurnosFijos(): array
    {
        //
        return [
            [
                'inicio' => '12:00',
                'fin' => '12:40',
                'ventana' => '12:00 - 13:30',
                'etiqueta' => '12:00 PM (Llegada máx: 12:40 PM)',
            ],
            [
                'inicio' => '13:30',
                'fin' => '14:10',
                'ventana' => '13:30 - 15:00',
                'etiqueta' => '01:30 PM (Llegada máx: 02:10 PM)',
            ],
            [
                'inicio' => '15:00',
                'fin' => '15:40',
                'ventana' => '15:00 - 16:30',
                'etiqueta' => '03:00 PM (Llegada máx: 03:40 PM)',
            ],
            [
                'inicio' => '16:30',
                'fin' => '17:10',
                'ventana' => '16:30 - 18:00',
                'etiqueta' => '04:30 PM (Llegada máx: 05:10 PM)',
            ],
            [
                'inicio' => '18:00',
                'fin' => '18:40',
                'ventana' => '18:00 - 19:30',
                'etiqueta' => '06:00 PM (Llegada máx: 06:40 PM)',
            ],
        ];
    }

    /**
     * Retorna la disponibilidad detallada de cada slot para una fecha determinada.
     *
     * @return array<int, array{inicio: string, fin: string, ventana: string, etiqueta: string, disponible: bool, entrega_id: ?int, numero_venta: ?string, cliente: ?string, estado: ?string, repartidor: ?string}>
     */
    public function obtenerDisponibilidad(string $fecha, ?int $empleadoId = null, ?int $ignorarEntregaId = null): array
    {
        $turnos = $this->obtenerTurnosFijos();

        $query = Entrega::query()
            ->with(['venta.cliente', 'empleado'])
            ->whereDate('fecha_programada', $fecha)
            ->where('estado', '!=', 'cancelado');

        if ($ignorarEntregaId) {
            $query->where('id', '!=', $ignorarEntregaId);
        }

        if ($empleadoId) {
            $query->where('empleado_id', $empleadoId);
        }

        $entregasDelDia = $query->get();

        return array_map(function (array $turno) use ($entregasDelDia): array {
            $horaInicioFormateada = substr($turno['inicio'], 0, 5);

            // Buscar si hay una entrega activa en este turno
            $ocupada = $entregasDelDia->first(function (Entrega $entrega) use ($horaInicioFormateada): bool {
                $entregaHora = substr((string) $entrega->hora_inicio, 0, 5);

                return $entregaHora === $horaInicioFormateada;
            });

            return [
                'inicio' => $turno['inicio'],
                'fin' => $turno['fin'],
                'ventana' => $turno['ventana'],
                'etiqueta' => $turno['etiqueta'],
                'disponible' => is_null($ocupada),
                'entrega_id' => $ocupada?->id,
                'numero_venta' => $ocupada?->venta?->numero_venta,
                'cliente' => $ocupada?->venta?->cliente?->name,
                'estado' => $ocupada?->estado,
                'repartidor' => $ocupada?->empleado?->name,
            ];
        }, $turnos);
    }

    /**
     * Valida si un slot específico está libre y cumple las reglas de horario.
     *
     * @return array{valido: bool, mensaje: string, hora_fin: string}
     */
    public function validarSlot(
        string $fecha,
        string $horaInicio,
        ?int $empleadoId = null,
        ?int $ignorarEntregaId = null
    ): array {
        $horaLimpia = substr($horaInicio, 0, 5);
        $turnos = $this->obtenerTurnosFijos();

        $turnoEncontrado = null;
        foreach ($turnos as $t) {
            if ($t['inicio'] === $horaLimpia) {
                $turnoEncontrado = $t;
                break;
            }
        }

        if (! $turnoEncontrado) {
            return [
                'valido' => false,
                'mensaje' => 'El horario seleccionado no corresponde a un turno oficial de logística (turnos permitidos: 12:00, 13:30, 15:00, 16:30 o 18:00).',
                'hora_fin' => '00:00',
            ];
        }

        $query = Entrega::query()
            ->with(['venta'])
            ->whereDate('fecha_programada', $fecha)
            ->where('estado', '!=', 'cancelado')
            ->where('hora_inicio', 'like', "{$horaLimpia}%");

        if ($ignorarEntregaId) {
            $query->where('id', '!=', $ignorarEntregaId);
        }

        if ($empleadoId) {
            $query->where('empleado_id', $empleadoId);
        }

        $ocupada = $query->first();

        if ($ocupada) {
            $num = $ocupada->venta?->numero_venta ?? 'un pedido previo';

            return [
                'valido' => false,
                'mensaje' => "El turno de las {$horaLimpia} ya está reservado para {$num}. Por seguridad matemática de 1h 30m entre entregas, selecciona otro turno libre.",
                'hora_fin' => $turnoEncontrado['fin'],
            ];
        }

        return [
            'valido' => true,
            'mensaje' => "Turno disponible de {$turnoEncontrado['inicio']} a {$turnoEncontrado['fin']} (ventana con holgura hasta las {$turnoEncontrado['ventana']}).",
            'hora_fin' => $turnoEncontrado['fin'],
        ];
    }

    /**
     * Calcula automáticamente la hora fin a partir de la hora de inicio seleccionada.
     */
    public function calcularHoraFin(string $horaInicio): string
    {
        $horaLimpia = substr($horaInicio, 0, 5);
        foreach ($this->obtenerTurnosFijos() as $turno) {
            if ($turno['inicio'] === $horaLimpia) {
                return $turno['fin'];
            }
        }

        return Carbon::parse($horaInicio)->addMinutes(self::DURACION_ENTREGA_MINUTOS)->format('H:i');
    }
}
