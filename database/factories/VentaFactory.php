<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_venta' => fake()->unique()->bothify('VTA-########'),
            'cliente_id' => User::factory(),
            'usuario_vendedor_id' => User::factory(),
            'tipo_venta' => fake()->randomElement(['online', 'presencial']),
            'tipo_entrega' => fake()->randomElement(['delivery', 'recojo_tienda']),
            'estado' => fake()->randomElement(['pendiente', 'pagada', 'completada']),
            'subtotal' => $subtotal = fake()->randomFloat(2, 20, 1_000),
            'descuento' => $descuento = fake()->randomFloat(2, 0, min(50, $subtotal)),
            'total' => $subtotal - $descuento,
            'fecha_venta' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
