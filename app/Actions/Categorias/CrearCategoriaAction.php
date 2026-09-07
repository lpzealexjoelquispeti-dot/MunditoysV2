<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Repositories\CategoriaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CrearCategoriaAction
{
    public function __construct(private CategoriaRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos, ?UploadedFile $imagen): Categoria
    {
        $ruta = $imagen?->store('categorias', 'public');

        try {
            if ($ruta !== null) {
                $datos['imagen'] = $ruta;
            }
            $resultado = DB::transaction(fn () => $this->repository->crear($datos));
        } catch (Throwable $exception) {
            if ($ruta !== null) {
                Storage::disk('public')->delete($ruta);
            }
            throw $exception;
        }

        return $resultado;
    }
}
