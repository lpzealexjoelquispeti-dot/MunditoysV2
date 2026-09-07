<?php

namespace Database\Factories;

use App\Models\Almacen;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Almacen>
 */
class AlmacenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sucursal_id' => Sucursal::factory(),
            'nombre' => 'Almacén '.fake()->unique()->word(),
            'tipo' => 'principal',
            'estado' => true,
        ];
    }
}
