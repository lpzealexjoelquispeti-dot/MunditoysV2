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

class ActualizarProductoAction
{
    public function __construct(
        private ProductoRepository $productoRepository,
        private ProductoMediaRepository $productoMediaRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<int, UploadedFile>  $imagenes
     * @param  array<int, int>  $imagenesEliminadas
     */
    public function ejecutar(Producto $producto, array $atributos, array $imagenes, array $imagenesEliminadas, ?UploadedFile $modeloGlb): Producto
    {
        $rutasImagenes = [];
        $rutaModelo = null;

        try {
            foreach ($imagenes as $imagen) {
                $rutasImagenes[] = $imagen->store('productos', 'public');
            }

            $rutaModelo = $modeloGlb?->storeAs('modelos-3d', Str::uuid().'.glb', 'public');

            return DB::transaction(function () use ($producto, $atributos, $rutasImagenes, $imagenesEliminadas, $rutaModelo): Producto {
                $producto = $this->productoRepository->actualizar($producto, $atributos);
                $this->productoMediaRepository->eliminarImagenes($producto, $imagenesEliminadas);
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
