<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sucursal>
 */
class SucursalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Sucursal '.fake()->unique()->city(),
            'direccion' => fake()->address(),
            'zona' => fake()->citySuffix(),
            'ciudad' => fake()->city(),
            'latitud' => fake()->latitude(-22.9, -9.7),
            'longitud' => fake()->longitude(-69.7, -57.4),
            'ubicacion_jsonb' => [
                'referencia' => fake()->streetName(),
            ],
            'telefono' => fake()->phoneNumber(),
            'estado' => true,
        ];
    }
}
