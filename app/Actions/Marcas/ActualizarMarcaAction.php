<?php

namespace App\Actions\Marcas;

use App\Models\Marca;
use App\Repositories\MarcaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ActualizarMarcaAction
{
    public function __construct(private MarcaRepository $repository) {}

    /** @param array<string, mixed> $datos */
    public function ejecutar(Marca $registro, array $datos, ?UploadedFile $imagen): Marca
    {
        $ruta = $imagen?->store('marcas', 'public');
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
