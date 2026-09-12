<?php

namespace Database\Factories;

use App\Models\Direccion;
use App\Models\Entrega;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrega>
 */
class EntregaFactory extends Factory
{
    protected $model = Entrega::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'venta_id' => Venta::factory(),
            'direccion_id' => Direccion::factory(),
            'empleado_id' => null,
            'sucursal_origen_id' => Sucursal::factory(),
            'fecha_programada' => now()->toDateString(),
            'hora_inicio' => '12:00:00',
            'hora_fin' => '12:40:00',
            'estado' => 'pendiente',
            'motivo_cancelacion' => null,
            'notas_despacho' => null,
            'cancelado_at' => null,
            'entregado_at' => null,
        ];
    }

    public function enCamino(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'en_camino',
            'empleado_id' => User::factory(),
        ]);
    }

    public function entregado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'entregado',
            'empleado_id' => User::factory(),
            'entregado_at' => now(),
        ]);
    }

    public function cancelado(string $motivo = 'Cliente ausente en el domicilio'): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'cancelado',
            'motivo_cancelacion' => $motivo,
            'cancelado_at' => now(),
        ]);
    }
}
