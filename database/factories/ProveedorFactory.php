<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa' => fake()->company(),
            'nombre_contacto' => fake()->name(),
            'telefono' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'direccion' => fake()->address(),
            'nit' => fake()->numerify('##########'),
            'estado' => true,
        ];
    }
}
