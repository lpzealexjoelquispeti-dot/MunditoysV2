<?php

namespace App\Services;

use GdImage;

/**
 * Servicio autónomo en PHP puro para la generación de códigos QR (PNG base64 y Data URI)
 * compatible con DomPDF y vistas web sin requerir extensiones o paquetes externos.
 */
class QrCodeService
{
    /**
     * Genera un Data URI listo para usar en etiquetas <img> HTML o DomPDF.
     * Ejemplo: "data:image/png;base64,iVBORw0..."
     */
    public function generarDataUri(string $contenido, int $tamano = 300): string
    {
        $pngBinario = $this->generarPngBinario($contenido, $tamano);

        return 'data:image/png;base64,'.base64_encode($pngBinario);
    }

    /**
     * Genera la cadena binaria PNG del código QR.
     */
    public function generarPngBinario(string $contenido, int $tamano = 300): string
    {
        $qr = new PurePhpQrCodeEngine($contenido, [
            's' => 'qrm', // Corrección de error nivel Medio
            'w' => $tamano,
            'h' => $tamano,
            'p' => 12,    // Padding de margen blanco
        ]);

        $imagen = $qr->render_image();

        ob_start();
        imagepng($imagen);
        $png = ob_get_clean();
        imagedestroy($imagen);

        return (string) $png;
    }
}

/**
 * Motor interno de generación QR en PHP puro (Licencia MIT).
 * Basado en la especificación ISO/IEC 18004.
 */
class PurePhpQrCodeEngine
{
    private string $data;

    private array $options;

    private array $qr_capacity = [
        [[17, 14, 11, 7], [14, 11, 7, 4], [27, 20, 14, 8], [34, 26, 17, 10]],
        [[32, 26, 20, 14], [26, 20, 14, 9], [48, 38, 28, 16], [63, 48, 34, 20]],
        [[53, 42, 32, 24], [42, 32, 22, 15], [77, 61, 44, 26], [101, 77, 55, 32]],
        [[78, 62, 46, 34], [62, 46, 34, 24], [111, 89, 64, 40], [149, 114, 82, 48]],
        [[106, 84, 60, 44], [84, 60, 42, 30], [144, 115, 84, 52], [202, 154, 110, 64]],
        [[134, 106, 74, 58], [106, 76, 54, 38], [178, 142, 102, 64], [255, 195, 140, 84]],
        [[154, 122, 86, 64], [122, 88, 62, 42], [207, 165, 118, 72], [293, 224, 158, 96]],
        [[192, 152, 108, 84], [152, 110, 78, 56], [259, 207, 148, 92], [365, 279, 198, 120]],
        [[230, 180, 130, 98], [180, 132, 94, 68], [312, 249, 178, 110], [432, 335, 236, 144]],
        [[271, 213, 151, 119], [213, 154, 110, 78], [364, 291, 210, 132], [513, 395, 278, 172]],
    ];

    private array $qr_ec_params = [
        [19, 7, 1, 19, 7, 0, 0], [16, 10, 1, 16, 10, 0, 0], [13, 13, 1, 13, 13, 0, 0], [9, 17, 1, 9, 17, 0, 0],
        [34, 10, 1, 34, 10, 0, 0], [28, 16, 1, 28, 16, 0, 0], [22, 22, 1, 22, 22, 0, 0], [16, 28, 1, 16, 28, 0, 0],
        [55, 15, 1, 55, 15, 0, 0], [44, 26, 1, 44, 26, 0, 0], [34, 18, 2, 17, 9, 0, 0], [26, 22, 2, 13, 11, 0, 0],
        [80, 20, 1, 80, 20, 0, 0], [64, 18, 2, 32, 9, 0, 0], [48, 26, 2, 24, 13, 0, 0], [36, 16, 4, 9, 4, 0, 0],
        [108, 26, 1, 108, 26, 0, 0], [86, 24, 2, 43, 12, 0, 0], [62, 18, 2, 15, 9, 2, 16], [46, 22, 2, 11, 11, 2, 12],
        [136, 18, 2, 68, 9, 0, 0], [108, 16, 4, 27, 8, 0, 0], [76, 24, 4, 19, 12, 0, 0], [60, 28, 4, 15, 14, 0, 0],
        [156, 20, 2, 78, 10, 0, 0], [124, 18, 4, 31, 9, 0, 0], [88, 18, 2, 14, 9, 4, 15], [66, 26, 4, 13, 13, 1, 14],
        [194, 24, 2, 97, 12, 0, 0], [154, 22, 2, 38, 11, 2, 39], [110, 22, 4, 18, 11, 2, 19], [86, 26, 4, 14, 13, 2, 15],
        [232, 30, 2, 116, 15, 0, 0], [182, 22, 3, 36, 11, 2, 37], [132, 20, 4, 16, 10, 4, 17], [100, 24, 4, 12, 12, 4, 13],
        [274, 18, 2, 68, 9, 2, 69], [216, 26, 4, 43, 13, 1, 44], [154, 24, 6, 19, 12, 2, 20], [122, 28, 6, 15, 14, 2, 16],
    ];

    private array $qr_rs_poly = [
        [0],
        [0, 0],
        [0, 25, 1],
        [0, 198, 199, 3],
        [0, 75, 199, 101, 6],
        [0, 8, 120, 131, 227, 10],
        [0, 168, 223, 200, 104, 235, 15],
        [0, 87, 229, 146, 149, 238, 102, 21],
        [0, 175, 238, 208, 249, 215, 252, 196, 28],
        [0, 182, 137, 236, 185, 201, 44, 9, 185, 36],
        [0, 216, 194, 159, 111, 199, 142, 65, 169, 0, 45],
        [0, 72, 131, 247, 87, 211, 248, 157, 252, 219, 124, 55],
        [0, 62, 111, 181, 176, 201, 54, 37, 235, 235, 242, 63, 66],
        [0, 105, 109, 50, 47, 59, 210, 121, 81, 24, 229, 22, 147, 78],
        [0, 144, 219, 211, 93, 157, 147, 160, 27, 219, 245, 94, 205, 178, 91],
        [0, 115, 202, 210, 86, 151, 172, 249, 133, 54, 38, 6, 133, 113, 214, 105],
        [0, 59, 116, 79, 161, 252, 98, 128, 205, 128, 161, 247, 57, 163, 56, 235, 120],
        [0, 152, 185, 240, 5, 111, 99, 6, 220, 112, 150, 69, 36, 187, 22, 228, 198, 136],
        [0, 177, 134, 70, 214, 19, 241, 91, 86, 234, 191, 130, 218, 197, 228, 165, 226, 19, 153],
        [0, 158, 129, 223, 17, 62, 42, 158, 172, 81, 230, 26, 236, 151, 96, 149, 158, 39, 82, 171],
        [0, 111, 102, 211, 86, 41, 146, 48, 141, 188, 105, 128, 19, 171, 111, 93, 65, 41, 223, 199, 190],
        [0, 251, 175, 46, 200, 83, 150, 172, 21, 35, 51, 253, 7, 233, 243, 112, 22, 244, 89, 152, 81, 210],
        [0, 237, 128, 236, 138, 21, 122, 3, 208, 133, 48, 207, 35, 60, 59, 58, 22, 171, 60, 89, 153, 40, 231],
        [0, 236, 230, 124, 35, 221, 231, 254, 199, 175, 74, 242, 4, 169, 107, 234, 99, 19, 133, 14, 142, 116, 80, 253],
        [0, 65, 32, 1, 205, 173, 213, 25, 217, 109, 152, 139, 157, 149, 211, 44, 83, 160, 122, 126, 23, 41, 111, 38, 21],
        [0, 102, 99, 242, 220, 244, 236, 161, 20, 102, 110, 190, 179, 44, 180, 186, 168, 139, 249, 230, 111, 126, 21, 57, 249, 46],
        [0, 226, 99, 11, 12, 110, 68, 55, 235, 169, 5, 24, 24, 199, 92, 42, 138, 158, 37, 84, 254, 211, 105, 77, 62, 173, 72],
        [0, 183, 26, 201, 87, 210, 221, 113, 21, 46, 65, 45, 50, 238, 184, 249, 225, 102, 58, 209, 219, 109, 226, 222, 7, 202, 226, 99],
        [0, 132, 180, 98, 152, 185, 206, 214, 81, 170, 229, 50, 242, 107, 245, 168, 83, 62, 35, 91, 159, 135, 134, 160, 78, 49, 226, 79, 127],
        [0, 91, 194, 238, 53, 200, 9, 223, 210, 39, 222, 221, 52, 223, 255, 73, 110, 83, 81, 160, 89, 39, 3, 62, 139, 251, 232, 205, 39, 156],
        [0, 250, 196, 35, 62, 59, 17, 199, 135, 226, 35, 97, 28, 142, 215, 176, 225, 22, 53, 51, 218, 196, 54, 210, 214, 218, 202, 57, 249, 50, 186],
    ];

    private array $qr_galois_exp = [];

    private array $qr_galois_log = [];

    public function __construct(string $data, array $options = [])
    {
        $this->data = $data;
        $this->options = array_merge(['s' => 'qrm'], $options);
        $this->initGalois();
    }

    private function initGalois(): void
    {
        $this->qr_galois_exp = array_fill(0, 512, 0);
        $this->qr_galois_log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $this->qr_galois_exp[$i] = $x;
            $this->qr_galois_exp[$i + 255] = $x;
            $this->qr_galois_log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
    }

    public function render_image(): GdImage
    {
        [$code, $widths, $width, $height, $x, $y, $w, $h] = $this->encode_and_calculate_size($this->data, $this->options);

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        $bgcolor = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgcolor);

        $fgcolor = imagecolorallocate($image, 15, 23, 42); // slate-900
        $colors = [$bgcolor, $fgcolor];

        [$mWidth, $mHeight] = $this->calculate_size($code, $widths);
        $scale = ($mWidth && $mHeight) ? max(1, (int) min($w / $mWidth, $h / $mHeight)) : 1;
        $x = (int) floor($x + ($w - $mWidth * $scale) / 2);
        $y = (int) floor($y + ($h - $mHeight * $scale) / 2);

        $x += $code['q'][3] * $widths[0] * $scale;
        $y += $code['q'][0] * $widths[0] * $scale;
        $wh = $widths[1] * $scale;

        foreach ($code['b'] as $by => $row) {
            $y1 = $y + $by * $wh;
            foreach ($row as $bx => $color) {
                $x1 = $x + $bx * $wh;
                $mc = $colors[$color ? 1 : 0];
                imagefilledrectangle($image, $x1, $y1, $x1 + $wh - 1, $y1 + $wh - 1, $mc);
            }
        }

        return $image;
    }

    private function encode_and_calculate_size(string $data, array $options): array
    {
        $code = $this->qr_encode($data, 1); // 1 = QRM (Medium ECC)
        $widths = [1, 1];
        $size = $this->calculate_size($code, $widths);
        $scale = 6;
        $padding = (int) ($options['p'] ?? 16);
        $iwidth = (int) ($options['w'] ?? ceil($size[0] * $scale) + $padding * 2);
        $iheight = (int) ($options['h'] ?? ceil($size[1] * $scale) + $padding * 2);
        $swidth = $iwidth - $padding * 2;
        $sheight = $iheight - $padding * 2;

        return [$code, $widths, $iwidth, $iheight, $padding, $padding, $swidth, $sheight];
    }

    private function calculate_size(array $code, array $widths): array
    {
        $width = ($code['q'][3] * $widths[0] + $code['s'][0] * $widths[1] + $code['q'][1] * $widths[0]);
        $height = ($code['q'][0] * $widths[0] + $code['s'][1] * $widths[1] + $code['q'][2] * $widths[0]);

        return [$width, $height];
    }

    private function qr_encode(string $data, int $ecl): array
    {
        [$mode, $vers, $ec, $encodedData] = $this->qr_encode_data($data, $ecl);
        $encodedData = $this->qr_encode_ec($encodedData, $ec, $vers);
        [$size, $mtx] = $this->qr_create_matrix($vers, $encodedData);
        [$mask, $mtx] = $this->qr_apply_best_mask($mtx, $size);
        $mtx = $this->qr_finalize_matrix($mtx, $size, $ecl, $mask, $vers);

        return [
            'q' => [4, 4, 4, 4],
            's' => [$size, $size],
            'b' => $mtx,
        ];
    }

    private function qr_encode_data(string $data, int $ecl): array
    {
        $mode = 2; // Byte mode (supports any ASCII/UTF-8)
        $version = $this->qr_detect_version($data, $mode, $ecl);
        $version_group = ($version < 10) ? 0 : (($version < 27) ? 1 : 2);
        $ec_params = $this->qr_ec_params[($version - 1) * 4 + $ecl];

        $max_chars = $this->qr_capacity[$version - 1][$ecl][$mode];
        $data = substr($data, 0, $max_chars);
        $code = $this->qr_encode_binary($data, $version_group);

        for ($i = 0; $i < 4; $i++) {
            $code[] = 0;
        }
        while (count($code) % 8) {
            $code[] = 0;
        }

        $byteData = [];
        for ($i = 0, $n = count($code); $i < $n; $i += 8) {
            $byte = 0;
            for ($b = 0; $b < 8; $b++) {
                if ($code[$i + $b]) {
                    $byte |= (1 << (7 - $b));
                }
            }
            $byteData[] = $byte;
        }

        for ($i = count($byteData), $a = 1, $n = $ec_params[0]; $i < $n; $i++, $a ^= 1) {
            $byteData[] = $a ? 236 : 17;
        }

        return [$mode, $version, $ec_params, $byteData];
    }

    private function qr_detect_version(string $data, int $mode, int $ecl): int
    {
        $length = strlen($data);
        for ($v = 0; $v < 10; $v++) {
            if ($length <= $this->qr_capacity[$v][$ecl][$mode]) {
                return $v + 1;
            }
        }

        return 10;
    }

    private function qr_encode_binary(string $data, int $version_group): array
    {
        $code = [0, 1, 0, 0];
        $length = strlen($data);
        if ($version_group === 0) {
            for ($b = 7; $b >= 0; $b--) {
                $code[] = ($length >> $b) & 1;
            }
        } else {
            for ($b = 15; $b >= 0; $b--) {
                $code[] = ($length >> $b) & 1;
            }
        }

        for ($i = 0; $i < $length; $i++) {
            $byte = ord($data[$i]);
            for ($b = 7; $b >= 0; $b--) {
                $code[] = ($byte >> $b) & 1;
            }
        }

        return $code;
    }

    private function qr_encode_ec(array $data, array $ec_params, int $version): array
    {
        $blocks = [];
        $offset = 0;
        for ($b = 0; $b < $ec_params[2]; $b++) {
            $blocks[] = [
                'dc' => array_slice($data, $offset, $ec_params[3]),
                'ec' => $this->qr_calculate_ec(array_slice($data, $offset, $ec_params[3]), $ec_params[1]),
            ];
            $offset += $ec_params[3];
        }
        for ($b = 0; $b < $ec_params[5]; $b++) {
            $blocks[] = [
                'dc' => array_slice($data, $offset, $ec_params[6]),
                'ec' => $this->qr_calculate_ec(array_slice($data, $offset, $ec_params[6]), $ec_params[1]),
            ];
            $offset += $ec_params[6];
        }

        $result = [];
        $max_dc = max($ec_params[3], $ec_params[6]);
        for ($i = 0; $i < $max_dc; $i++) {
            foreach ($blocks as $block) {
                if ($i < count($block['dc'])) {
                    $result[] = $block['dc'][$i];
                }
            }
        }
        for ($i = 0; $i < $ec_params[1]; $i++) {
            foreach ($blocks as $block) {
                $result[] = $block['ec'][$i];
            }
        }

        return $result;
    }

    private function qr_calculate_ec(array $data, int $count): array
    {
        $poly = $this->qr_rs_poly[$count];
        $ec = array_fill(0, $count, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $ec[0];
            array_shift($ec);
            $ec[] = 0;
            if ($factor !== 0) {
                $factor_log = $this->qr_galois_log[$factor];
                for ($i = 0; $i < $count; $i++) {
                    $ec[$i] ^= $this->qr_galois_exp[$poly[$i + 1] + $factor_log];
                }
            }
        }

        return $ec;
    }

    private function qr_create_matrix(int $version, array $data): array
    {
        $size = 17 + 4 * $version;
        $mtx = array_fill(0, $size, array_fill(0, $size, null));

        // Finder patterns
        $this->place_finder($mtx, 0, 0);
        $this->place_finder($mtx, $size - 7, 0);
        $this->place_finder($mtx, 0, $size - 7);

        // Timing patterns
        for ($i = 8; $i < $size - 8; $i++) {
            $mtx[6][$i] = ($i % 2 === 0) ? 1 : 0;
            $mtx[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }

        // Dark module
        $mtx[4 * $version + 9][8] = 1;

        // Reserve format areas
        for ($i = 0; $i < 9; $i++) {
            if ($mtx[8][$i] === null) {
                $mtx[8][$i] = 0;
            }
            if ($mtx[$i][8] === null) {
                $mtx[$i][8] = 0;
            }
        }
        for ($i = $size - 8; $i < $size; $i++) {
            if ($mtx[8][$i] === null) {
                $mtx[8][$i] = 0;
            }
            if ($mtx[$i][8] === null) {
                $mtx[$i][8] = 0;
            }
        }

        // Place data bits
        $bitIdx = 0;
        $totalBits = count($data) * 8;
        for ($right = $size - 1; $right > 0; $right -= 2) {
            if ($right === 6) {
                $right--;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                $y = (($right + 1) & 2) ? ($size - 1 - $vert) : $vert;
                for ($col = 0; $col < 2; $col++) {
                    $x = $right - $col;
                    if ($mtx[$y][$x] === null) {
                        $bit = ($bitIdx < $totalBits) ? (($data[$bitIdx >> 3] >> (7 - ($bitIdx & 7))) & 1) : 0;
                        $mtx[$y][$x] = $bit;
                        $bitIdx++;
                    }
                }
            }
        }

        return [$size, $mtx];
    }

    private function place_finder(array &$mtx, int $ox, int $oy): void
    {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $px = $ox + $x;
                $py = $oy + $y;
                if ($px >= 0 && $px < count($mtx) && $py >= 0 && $py < count($mtx)) {
                    if ($x >= 0 && $x <= 6 && $y >= 0 && $y <= 6) {
                        $mtx[$py][$px] = ($x === 0 || $x === 6 || $y === 0 || $y === 6 || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4)) ? 1 : 0;
                    } else {
                        $mtx[$py][$px] = 0;
                    }
                }
            }
        }
    }

    private function qr_apply_best_mask(array $mtx, int $size): array
    {
        $bestMask = 0;
        $bestMtx = $mtx;
        // Aplicar máscara estándar 0: (x + y) % 2 == 0
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (($x + $y) % 2 === 0) {
                    // Solo aplicar a datos, no a finder o timing
                    if (! ($y <= 8 && ($x <= 8 || $x >= $size - 8)) && ! ($y >= $size - 8 && $x <= 8) && $y !== 6 && $x !== 6) {
                        $bestMtx[$y][$x] ^= 1;
                    }
                }
            }
        }

        return [$bestMask, $bestMtx];
    }

    private function qr_finalize_matrix(array $mtx, int $size, int $ecl, int $mask, int $version): array
    {
        // Formato info (nivel M + mascara 0)
        $formatInfo = 0x5412; // 01 000 con BCH
        for ($i = 0; $i < 15; $i++) {
            $bit = ($formatInfo >> $i) & 1;
            if ($i < 6) {
                $mtx[8][$i] = $bit;
                $mtx[$size - 1 - $i][8] = $bit;
            } elseif ($i === 6) {
                $mtx[8][7] = $bit;
                $mtx[$size - 7][8] = $bit;
            } elseif ($i < 8) {
                $mtx[8][8] = $bit;
                $mtx[$size - 8][8] = $bit;
            } elseif ($i === 8) {
                $mtx[7][8] = $bit;
                $mtx[8][$size - 7] = $bit;
            } else {
                $mtx[14 - $i][8] = $bit;
                $mtx[8][$size - 15 + $i] = $bit;
            }
        }

        return $mtx;
    }
}
