<?php

namespace App\Actions\Productos;

use App\Models\Producto;
use App\Repositories\ProductoMediaRepository;
use App\Repositories\ProductoRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CrearProductoAction
{
    public function __construct(
        private ProductoRepository $productoRepository,
        private ProductoMediaRepository $productoMediaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<int, UploadedFile>  $imagenes
     */
    public function ejecutar(array $atributos, array $imagenes, ?UploadedFile $modeloGlb): Producto
    {
        $rutasImagenes = [];
        $rutaModelo = null;

        try {
            foreach ($imagenes as $imagen) {
                $rutasImagenes[] = $imagen->store('productos', 'public');
            }

            $rutaModelo = $modeloGlb?->storeAs('modelos-3d', Str::uuid().'.glb', 'public');

            return DB::transaction(function () use ($atributos, $rutasImagenes, $rutaModelo): Producto {
                $producto = $this->productoRepository->crear($atributos);
                $this->productoMediaRepository->agregarImagenes($producto, $rutasImagenes);

                if ($rutaModelo !== null) {
                    $this->productoMediaRepository->guardarModeloGlb($producto, $rutaModelo);
                }

                return $producto;
            });
        } catch (Throwable $throwable) {
            Storage::disk('public')->delete($rutasImagenes);

            if ($rutaModelo !== null) {
                Storage::disk('public')->delete($rutaModelo);
            }

            throw $throwable;
        }
    }
}
