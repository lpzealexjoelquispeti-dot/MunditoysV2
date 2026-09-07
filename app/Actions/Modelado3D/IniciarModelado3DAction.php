<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;
use App\Repositories\Modelado3DRepository;
use App\Services\TripoService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IniciarModelado3DAction
{
    public function __construct(
        private TripoService $tripo,
        private Modelado3DRepository $repository,
    ) {}

    /**
     * @param  array<int, UploadedFile|string>  $fotos
     */
    public function ejecutar(array $fotos, ?int $productoId = null): Modelado3D
    {
        if (count($fotos) < 2 || count($fotos) > 4) {
            throw ValidationException::withMessages([
                'fotos' => 'Debes seleccionar entre 2 y 4 fotos del producto desde diferentes ángulos.',
            ]);
        }

        $rutasLocales = [];
        $nombrePrimeraFoto = null;

        // Directorio seguro en almacenamiento público
        Storage::disk('public')->makeDirectory('tripo-inputs');

        foreach ($fotos as $indice => $foto) {
            if ($foto instanceof UploadedFile) {
                $nombreArchivo = $foto->hashName();
                $rutaGuardada = $foto->storeAs('tripo-inputs', $nombreArchivo, 'public');
                $rutaAbsoluta = Storage::disk('public')->path($rutaGuardada);
            } else {
                $rutaAbsoluta = $foto;
                $nombreArchivo = basename($foto);
            }

            $rutasLocales[] = $rutaAbsoluta;

            if ($indice === 0) {
                $nombrePrimeraFoto = 'tripo-inputs/'.$nombreArchivo;
            }
        }

        $resultado = $this->tripo->generarMultiImageTo3d($rutasLocales);
        $taskId = $resultado['data']['task_id'] ?? null;

        if (! $taskId) {
            throw new Exception('Tripo no devolvió un identificador de tarea válido.');
        }

        return $this->repository->crearTarea([
            'producto_id' => $productoId ?: null,
            'task_id' => $taskId,
            'status' => 'pending',
            'tipo_generacion' => 'multi_image_to_3d',
            'archivo_origen' => $nombrePrimeraFoto,
            'progreso' => 0,
        ]);
    }
}
