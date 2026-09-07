<?php

namespace App\Services;

use App\Repositories\LimiteGeograficoRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UbicacionLaPazService
{
    public function __construct(private LimiteGeograficoRepository $repository) {}

    /** @return array<string, array<int, string>> */
    public function reglas(): array
    {
        return [
            'latitud' => ['bail', 'required', 'regex:/\A-?\d{1,3}(?:\.\d{1,7})?\z/', 'numeric', 'between:-90,90'],
            'longitud' => ['bail', 'required', 'regex:/\A-?\d{1,3}(?:\.\d{1,7})?\z/', 'numeric', 'between:-180,180'],
        ];
    }

    public function validar(string $latitud, string $longitud): void
    {
        Validator::make(compact('latitud', 'longitud'), $this->reglas(), [
            'latitud.required' => 'Selecciona la ubicación de la sucursal en el mapa.',
            'longitud.required' => 'Selecciona la ubicación de la sucursal en el mapa.',
            '*.regex' => 'La coordenada debe ser un número con hasta 7 decimales.',
        ])->validate();
        if (! $this->contiene((float) $latitud, (float) $longitud)) {
            throw ValidationException::withMessages(['latitud' => 'La ubicación debe estar dentro del municipio de La Paz, Bolivia. El Alto no está incluido.']);
        }
    }

    public function contiene(float $latitud, float $longitud): bool
    {
        if (! is_finite($latitud) || ! is_finite($longitud)) {
            return false;
        }
        $geometria = $this->repository->laPaz()['geometry'];
        $poligonos = $geometria['type'] === 'Polygon' ? [$geometria['coordinates']] : $geometria['coordinates'];
        foreach ($poligonos as $anillos) {
            if (! $this->enAnillo($longitud, $latitud, $anillos[0])) {
                continue;
            }
            foreach (array_slice($anillos, 1) as $hueco) {
                if ($this->enAnillo($longitud, $latitud, $hueco)) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }

    /** @param array<int, array{0: float, 1: float}> $anillo */
    private function enAnillo(float $x, float $y, array $anillo): bool
    {
        $dentro = false;
        $cantidad = count($anillo);
        for ($i = 0, $j = $cantidad - 1; $i < $cantidad; $j = $i++) {
            [$ax, $ay] = $anillo[$i];
            [$bx, $by] = $anillo[$j];
            $cruz = ($x - $ax) * ($by - $ay) - ($y - $ay) * ($bx - $ax);
            if (abs($cruz) <= 1.0e-12 && $x >= min($ax, $bx) && $x <= max($ax, $bx)
                && $y >= min($ay, $by) && $y <= max($ay, $by)) {
                return true;
            }
            if (($ay > $y) !== ($by > $y) && $x < ($bx - $ax) * ($y - $ay) / ($by - $ay) + $ax) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }
}
