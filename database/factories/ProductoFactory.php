<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'categoria_id' => Categoria::factory(),
            'marca_id' => Marca::factory(),
            'codigo_producto' => fake()->unique()->bothify('PROD-#####'),
            'codigo_qr' => 'MTQR-'.fake()->unique()->uuid(),
            'nombre' => fake()->words(3, true),
            'descripcion' => fake()->optional()->paragraph(),
            'precio_compra_referencia' => fake()->optional()->randomFloat(2, 10, 500),
            'precio_venta' => fake()->randomFloat(2, 20, 800),
            'edad_recomendada' => fake()->randomElement(['0-2', '3-5', '6-8', '9-12', '13+']),
            'peso' => fake()->optional()->randomFloat(2, 0.10, 10),
            'dimensiones' => fake()->optional()->numerify('## x ## x ## cm'),
            'estado' => 'activo',
        ];
    }

    public function sinMarca(): static
    {
        return $this->state(fn (): array => ['marca_id' => null]);
    }
}
