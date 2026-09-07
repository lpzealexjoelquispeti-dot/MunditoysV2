<?php

use App\Services\UbicacionLaPazService;
use Illuminate\Validation\ValidationException;

test('el límite cartográfico acepta La Paz y rechaza El Alto y otras ciudades', function (float $latitud, float $longitud, bool $permitido) {
    expect(app(UbicacionLaPazService::class)->contiene($latitud, $longitud))->toBe($permitido);
})->with([
    'centro paceño' => [-16.495, -68.133, true],
    'zona sur' => [-16.539, -68.086, true],
    'El Alto próximo al borde' => [-16.504, -68.163, false],
    'El Alto' => [-16.5, -68.2, false],
    'Cochabamba' => [-17.39, -66.16, false],
    'coordenadas invertidas' => [-68.133, -16.495, false],
    'origen' => [0.0, 0.0, false],
]);

test('las coordenadas malformadas nunca superan la validación', function (string $latitud, string $longitud) {
    expect(fn () => app(UbicacionLaPazService::class)->validar($latitud, $longitud))->toThrow(ValidationException::class);
})->with([
    ['', ''], ['xx', '-68.133'], ['-16.495', 'NaN'], ['91', '-68.133'],
    ['-16.495', '181'], ['-16.49500001', '-68.133'], ['1e1', '-68.133'],
]);
