<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

class ArchivoGlb implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()
            || strtolower($value->getClientOriginalExtension()) !== 'glb'
            || ! $this->contenidoValido($value)) {
            $fail('El archivo debe ser un modelo GLB 2.0 válido, no un archivo renombrado o dañado.');
        }
    }

    private function contenidoValido(UploadedFile $archivo): bool
    {
        $longitud = $archivo->getSize();

        if ($longitud < 20 || $longitud > 50 * 1024 * 1024) {
            return false;
        }

        $flujo = fopen($archivo->getRealPath(), 'rb');

        if ($flujo === false) {
            return false;
        }

        try {
            $cabecera = fread($flujo, 12);
            if (strlen($cabecera) !== 12) {
                return false;
            }

            $datos = unpack('a4firma/Vversion/Vlongitud', $cabecera);
            if ($datos['firma'] !== 'glTF' || $datos['version'] !== 2 || $datos['longitud'] !== $longitud) {
                return false;
            }

            $posicion = 12;
            while ($posicion < $longitud) {
                $cabeceraBloque = fread($flujo, 8);
                if (strlen($cabeceraBloque) !== 8) {
                    return false;
                }

                $bloque = unpack('Vlongitud/Vtipo', $cabeceraBloque);
                if ($bloque['longitud'] === 0 || $bloque['longitud'] % 4 !== 0
                    || $posicion + 8 + $bloque['longitud'] > $longitud) {
                    return false;
                }

                if ($posicion === 12) {
                    if ($bloque['tipo'] !== 0x4E4F534A) {
                        return false;
                    }

                    $json = json_decode(fread($flujo, $bloque['longitud']), true);
                    if (! is_array($json) || ($json['asset']['version'] ?? null) !== '2.0') {
                        return false;
                    }
                } elseif (fseek($flujo, $bloque['longitud'], SEEK_CUR) !== 0) {
                    return false;
                }

                $posicion += 8 + $bloque['longitud'];
            }

            return $posicion === $longitud;
        } finally {
            fclose($flujo);
        }
    }
}
