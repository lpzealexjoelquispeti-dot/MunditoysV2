<?php

namespace App\Services;

class NumeroALetrasService
{
    private static array $unidades = [
        '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE',
        'DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE',
    ];

    private static array $decenas = [
        '', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
    ];

    private static array $centenas = [
        '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
    ];

    public static function convertir(float $numero): string
    {
        $entero = (int) floor($numero);
        $centavos = (int) round(($numero - $entero) * 100);
        $centavosStr = str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);

        if ($entero === 0) {
            $literal = 'CERO';
        } else {
            $literal = self::convertirGrupo($entero);
        }

        return trim($literal).' CON '.$centavosStr.'/100 BOLIVIANOS';
    }

    private static function convertirGrupo(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }

        if ($n < 20) {
            return self::$unidades[$n];
        }

        if ($n < 30) {
            return ($n === 20) ? 'VEINTE' : 'VEINTI'.self::$unidades[$n - 20];
        }

        if ($n < 100) {
            $d = (int) floor($n / 10);
            $u = $n % 10;

            return self::$decenas[$d].($u > 0 ? ' Y '.self::$unidades[$u] : '');
        }

        if ($n < 1000) {
            $c = (int) floor($n / 100);
            $resto = $n % 100;

            return self::$centenas[$c].($resto > 0 ? ' '.self::convertirGrupo($resto) : '');
        }

        if ($n < 1000000) {
            $miles = (int) floor($n / 1000);
            $resto = $n % 1000;
            $prefijo = ($miles === 1) ? 'MIL' : self::convertirGrupo($miles).' MIL';

            return $prefijo.($resto > 0 ? ' '.self::convertirGrupo($resto) : '');
        }

        $millones = (int) floor($n / 1000000);
        $resto = $n % 1000000;
        $prefijo = ($millones === 1) ? 'UN MILLÓN' : self::convertirGrupo($millones).' MILLONES';

        return $prefijo.($resto > 0 ? ' '.self::convertirGrupo($resto) : '');
    }
}
