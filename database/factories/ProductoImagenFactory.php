<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductoImagen>
 */
class ProductoImagenFactory extends Factory
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
            'ruta_archivo' => 'productos/'.fake()->uuid().'.webp',
            'tipo_imagen' => 'producto',
            'orden' => 1,
            'principal' => true,
        ];
    }
}
