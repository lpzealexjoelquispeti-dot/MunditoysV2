<?php

namespace App\Actions\Marcas;

use App\Models\Marca;
use App\Repositories\MarcaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CrearMarcaAction
{
    public function __construct(private MarcaRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(array $datos, ?UploadedFile $imagen): Marca
    {
        $ruta = $imagen?->store('marcas', 'public');

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
