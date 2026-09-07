<?php

namespace App\Actions\Categorias;

use App\Models\Categoria;
use App\Repositories\CategoriaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ActualizarCategoriaAction
{
    public function __construct(private CategoriaRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Categoria $registro, array $datos, ?UploadedFile $imagen): Categoria
    {
        $ruta = $imagen?->store('categorias', 'public');
        $anterior = $registro->imagen;
        try {
            if ($ruta !== null) {
                $datos['imagen'] = $ruta;
            }
            $resultado = DB::transaction(fn () => $this->repository->actualizar($registro, $datos));
        } catch (Throwable $exception) {
            if ($ruta !== null) {
                Storage::disk('public')->delete($ruta);
            }
            throw $exception;
        }
        if ($ruta !== null && $anterior) {
            Storage::disk('public')->delete($anterior);
        }

        return $resultado;
    }
}
