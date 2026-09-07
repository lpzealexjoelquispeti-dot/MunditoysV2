<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;

class PrecioLoteService
{
    /** @return array{costo_unitario: string, margen_porcentaje: string, precio_venta: string, ganancia_unitaria: string} */
    public function calcular(string $costo, string $margen): array
    {
        Validator::make(['costoUnitario' => $costo, 'margen' => $margen], [
            'costoUnitario' => ['bail', 'required', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99999999.99'],
            'margen' => ['bail', 'required', 'regex:/\A\d{1,2}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99.99'],
        ], [
            'costoUnitario.regex' => 'El costo debe ser un número positivo con hasta 2 decimales.',
            'margen.regex' => 'El margen debe ser un porcentaje con hasta 2 decimales.',
        ], ['costoUnitario' => 'costo unitario', 'margen' => 'margen sobre venta'])->validate();

        $centavos = $this->centavos($costo);
        $puntos = $this->centavos($margen);
        $denominador = 10000 - $puntos;
        $venta = intdiv($centavos * 10000 + $denominador - 1, $denominador);
        Validator::make(['margen' => $venta], ['margen' => ['numeric', 'max:9999999999']], [
            'margen.max' => 'La combinación de costo y margen supera el precio máximo permitido.',
        ])->validate();

        return [
            'costo_unitario' => $this->decimal($centavos),
            'margen_porcentaje' => $this->decimal($puntos),
            'precio_venta' => $this->decimal($venta),
            'ganancia_unitaria' => $this->decimal($venta - $centavos),
        ];
    }

    private function centavos(string $valor): int
    {
        [$entero, $decimales] = array_pad(explode('.', $valor, 2), 2, '');

        return ((int) $entero * 100) + (int) str_pad($decimales, 2, '0');
    }

    private function decimal(int $centavos): string
    {
        return intdiv($centavos, 100).'.'.str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }
}
