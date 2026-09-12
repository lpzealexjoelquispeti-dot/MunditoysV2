<?php

namespace App\Services;

use App\Models\Direccion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DireccionService
{
    public const int LIMITE_MAXIMO_DIRECCIONES = 3;

    public function __construct(
        private UbicacionLaPazService $ubicacionLaPazService
    ) {}

    /**
     * @return Collection<int, Direccion>
     */
    public function listar(int $userId): Collection
    {
        return Direccion::query()
            ->where('user_id', $userId)
            ->orderByDesc('principal')
            ->latest('id')
            ->get();
    }

    public function contar(int $userId): int
    {
        return Direccion::query()
            ->where('user_id', $userId)
            ->count();
    }

    public function puedeAgregar(int $userId): bool
    {
        return $this->contar($userId) < self::LIMITE_MAXIMO_DIRECCIONES;
    }

    /**
     * @throws ValidationException
     */
    public function validarLimite(int $userId): void
    {
        if (! $this->puedeAgregar($userId)) {
            throw ValidationException::withMessages([
                'limite' => 'Has alcanzado el límite máximo de 3 direcciones. Para registrar una nueva, debes eliminar una dirección antigua.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function crear(int $userId, array $datos): Direccion
    {
        $this->validarLimite($userId);

        $lat = (string) ($datos['latitud'] ?? '');
        $lng = (string) ($datos['longitud'] ?? '');

        $this->ubicacionLaPazService->validar($lat, $lng);

        return DB::transaction(function () use ($userId, $datos, $lat, $lng) {
            $totalActual = $this->contar($userId);
            $esPrincipal = ! empty($datos['principal']) || $totalActual === 0;

            if ($esPrincipal) {
                Direccion::query()
                    ->where('user_id', $userId)
                    ->update(['principal' => false]);
            }

            return Direccion::create([
                'user_id' => $userId,
                'nombre_referencia' => trim((string) ($datos['nombre_referencia'] ?? '')),
                'direccion_completa' => trim((string) ($datos['direccion_completa'] ?? '')),
                'zona' => trim((string) ($datos['zona'] ?? '')),
                'ciudad' => 'La Paz',
                'latitud' => (float) $lat,
                'longitud' => (float) $lng,
                'ubicacion_jsonb' => $datos['ubicacion_jsonb'] ?? null,
                'principal' => $esPrincipal,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function actualizar(int $userId, int $direccionId, array $datos): Direccion
    {
        $direccion = Direccion::query()
            ->where('user_id', $userId)
            ->where('id', $direccionId)
            ->firstOrFail();

        $lat = (string) ($datos['latitud'] ?? $direccion->latitud);
        $lng = (string) ($datos['longitud'] ?? $direccion->longitud);

        $this->ubicacionLaPazService->validar($lat, $lng);

        return DB::transaction(function () use ($direccion, $userId, $datos, $lat, $lng) {
            $esPrincipal = ! empty($datos['principal']);

            if ($esPrincipal) {
                Direccion::query()
                    ->where('user_id', $userId)
                    ->where('id', '!=', $direccion->id)
                    ->update(['principal' => false]);
            }

            $direccion->update([
                'nombre_referencia' => trim((string) ($datos['nombre_referencia'] ?? $direccion->nombre_referencia)),
                'direccion_completa' => trim((string) ($datos['direccion_completa'] ?? $direccion->direccion_completa)),
                'zona' => trim((string) ($datos['zona'] ?? $direccion->zona)),
                'ciudad' => 'La Paz',
                'latitud' => (float) $lat,
                'longitud' => (float) $lng,
                'ubicacion_jsonb' => $datos['ubicacion_jsonb'] ?? $direccion->ubicacion_jsonb,
                'principal' => $esPrincipal ? true : $direccion->principal,
            ]);

            return $direccion->fresh();
        });
    }

    public function eliminar(int $userId, int $direccionId): void
    {
        $direccion = Direccion::query()
            ->where('user_id', $userId)
            ->where('id', $direccionId)
            ->firstOrFail();

        DB::transaction(function () use ($direccion, $userId) {
            $eraPrincipal = $direccion->principal;
            $direccion->delete();

            if ($eraPrincipal) {
                $primeraRestante = Direccion::query()
                    ->where('user_id', $userId)
                    ->oldest('id')
                    ->first();

                $primeraRestante?->update(['principal' => true]);
            }
        });
    }

    public function establecerPrincipal(int $userId, int $direccionId): Direccion
    {
        $direccion = Direccion::query()
            ->where('user_id', $userId)
            ->where('id', $direccionId)
            ->firstOrFail();

        return DB::transaction(function () use ($direccion, $userId) {
            Direccion::query()
                ->where('user_id', $userId)
                ->update(['principal' => false]);

            $direccion->update(['principal' => true]);

            return $direccion->fresh();
        });
    }
}
