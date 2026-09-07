<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;
use App\Repositories\Modelado3DRepository;
use App\Services\TripoService;
use Exception;
use Illuminate\Support\Facades\Log;

class SincronizarEstadoModelado3DAction
{
    public function __construct(
        private TripoService $tripo,
        private Modelado3DRepository $repository,
    ) {}

    public function ejecutar(Modelado3D $modelado): Modelado3D
    {
        // Si es una carga manual o ya está completado con archivo existente, no consultamos a la API
        if ($modelado->tipo_generacion === 'carga_manual' && ! empty($modelado->modelo_glb_path)) {
            return $modelado;
        }

        try {
            $resultado = $this->tripo->consultarEstadoTarea($modelado->task_id);
            $data = $resultado['data'] ?? [];

            $apiStatus = isset($data['status']) ? strtolower((string) $data['status']) : $modelado->status;
            $progresoApi = isset($data['progress']) ? (int) $data['progress'] : $modelado->progreso;

            $urlRemota = $data['output']['pbr_model']
                ?? $data['output']['model']
                ?? $data['result']['pbr_model']['url']
                ?? null;

            $rutaGlb = $modelado->modelo_glb_path;

            if ($apiStatus === 'success' && ! empty($urlRemota)) {
                $rutaGlb = $this->tripo->descargarGlb($urlRemota, $modelado->task_id);
                $progresoApi = 100;
            }

            return $this->repository->actualizarEstado($modelado, [
                'status' => $apiStatus,
                'progreso' => $progresoApi,
                'modelo_glb_path' => $rutaGlb,
                'error_message' => $data['message'] ?? $modelado->error_message,
            ]);
        } catch (Exception $e) {
            Log::error("Error sincronizando modelado #{$modelado->id}: ".$e->getMessage());
            throw $e;
        }
    }
}
