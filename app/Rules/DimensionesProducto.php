<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class DimensionesProducto implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $numero = '(\d+(?:[.,]\d{1,2})?)';
        $patron = '/\A *'.$numero.' *[xX×] *'.$numero.' *[xX×] *'.$numero.' *(?:cm)? *\z/u';

        if (! is_string($value) || ! preg_match($patron, $value, $medidas)) {
            $fail('Escribe largo x ancho x alto en centímetros. Ejemplo: 30 x 20 x 15.');

            return;
        }

        foreach (array_slice($medidas, 1) as $medida) {
            if ((float) str_replace(',', '.', $medida) <= 0) {
                $fail('Las tres dimensiones deben ser mayores que cero.');

                return;
            }
        }
    }
}
