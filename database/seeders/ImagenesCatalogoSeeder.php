<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ImagenesCatalogoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $directorio = public_path('images/catalogo-demo');
        $fuentes = collect(json_decode(file_get_contents($directorio.'/fuentes.json'), true, flags: JSON_THROW_ON_ERROR))->keyBy('codigo');
        $productos = Producto::query()->whereIn('codigo_producto', $fuentes->keys())
            ->where('estado', 'activo')->with('imagenes')->get();
        $disco = Storage::disk('public');

        foreach ($productos as $producto) {
            if ($producto->imagenes->isNotEmpty()) {
                continue;
            }
            $fuente = $fuentes[$producto->codigo_producto];
            $archivo = $fuente['archivo'];
            $origen = $directorio.'/'.$archivo;
            if (basename($archivo) !== $archivo || ! is_file($origen)
                || hash_file('sha256', $origen) !== $fuente['sha256']) {
                throw new RuntimeException('La imagen de referencia no es válida para '.$producto->codigo_producto.'.');
            }
            $imagen = getimagesize($origen);
            if ($imagen === false || ! in_array($imagen['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw new RuntimeException('Formato de imagen no compatible para '.$producto->codigo_producto.'.');
            }

            $ruta = 'productos/demostracion/'.$archivo;
            if (! $disco->exists($ruta) && ! $disco->put($ruta, file_get_contents($origen))) {
                throw new RuntimeException('No se pudo guardar la imagen de '.$producto->codigo_producto.'.');
            }
            $producto->imagenes()->create([
                'ruta_archivo' => $ruta, 'tipo_imagen' => 'producto', 'orden' => 1, 'principal' => true,
            ]);
        }
    }
}
