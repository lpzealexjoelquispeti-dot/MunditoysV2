<?php

namespace Database\Factories;

use App\Models\Direccion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Direccion>
 */
class DireccionFactory extends Factory
{
    protected $model = Direccion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nombre_referencia' => fake()->randomElement(['Mi Casa', 'Oficina Central', 'Departamento', 'Casa de Mis Padres']),
            'direccion_completa' => fake()->streetAddress().', Edificio '.fake()->buildingNumber(),
            'zona' => fake()->randomElement(['Sopocachi', 'Miraflores', 'Calacoto', 'San Pedro', 'Obrajes', 'San Jorge']),
            'ciudad' => 'La Paz',
            'latitud' => -16.4950000,
            'longitud' => -68.1330000,
            'ubicacion_jsonb' => [
                'detalles' => 'Portón negro, tocar timbre 2B',
            ],
            'principal' => false,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn (array $attributes) => [
            'principal' => true,
        ]);
    }
}
