<?php

namespace Database\Factories;

use App\Models\Modelado3D;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modelado3D>
 */
class Modelado3DFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'task_id' => 'local-'.fake()->uuid(),
            'tipo_generacion' => 'fotogrametria_local',
            'status' => 'borrador',
            'revision' => 0,
            'progreso' => 0,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (): array => [
            'tipo_generacion' => 'carga_manual', 'status' => 'completed',
            'modelo_glb_path' => 'modelos-3d/'.fake()->uuid().'.glb', 'progreso' => 100,
        ]);
    }
}
