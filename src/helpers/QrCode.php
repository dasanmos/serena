<?php
declare(strict_types=1);

/**
 * Generador de códigos QR en PHP puro (sin librerías ni extensiones): modo byte, corrección de errores M,
 * versiones 1 a 10 (hasta 213 bytes: de sobra para una URL de verificación).
 *
 * Es el porte 1:1 de un prototipo que se verificó decodificando los QR generados con un lector
 * (OpenCV) para todas las versiones 1-10. Si algún día se cambia, repetir esa verificación.
 *
 *   $matriz = QrCode::matriz('https://sitio/verificar.php?c=ABC');   // array de filas; 1 = módulo oscuro
 */
final class QrCode
{
    /** version => [bytes de corrección por bloque, [[n.º de bloques, datos por bloque], ...]] (nivel M) */
    private const BLOQUES_M = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ];
    private const ALINEACION = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];
    private const INFO_VERSION = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];

    /** @var int[]|null */
    private static ?array $exp = null;
    /** @var int[]|null */
    private static ?array $log = null;

    private static function iniciarCampo(): void
    {
        if (self::$exp !== null) {
            return;
        }
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function mul(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** @return int[] */
    private static function generadorRs(int $n): array
    {
        $g = [1];
        for ($i = 0; $i < $n; $i++) {
            $sig = array_fill(0, count($g) + 1, 0);
            foreach ($g as $j => $c) {
                $sig[$j] ^= $c;
                $sig[$j + 1] ^= self::mul($c, self::$exp[$i]);
            }
            $g = $sig;
        }
        return $g;
    }

    /** @param int[] $datos @return int[] */
    private static function restoRs(array $datos, int $n): array
    {
        $g = self::generadorRs($n);
        $total = count($datos);
        $r = array_merge($datos, array_fill(0, $n, 0));
        for ($i = 0; $i < $total; $i++) {
            $c = $r[$i];
            if ($c !== 0) {
                for ($j = 1; $j < count($g); $j++) {
                    $r[$i + $j] ^= self::mul($g[$j], $c);
                }
            }
        }
        return array_slice($r, $total, $n);
    }

    private static function capacidadDatos(int $v): int
    {
        $suma = 0;
        foreach (self::BLOQUES_M[$v][1] as [$n, $d]) {
            $suma += $n * $d;
        }
        return $suma;
    }

    /** @param int[] $bytes */
    private static function elegirVersion(array $bytes): int
    {
        for ($v = 1; $v <= 10; $v++) {
            $bitsCuenta = $v < 10 ? 8 : 16;
            if (4 + $bitsCuenta + count($bytes) * 8 <= self::capacidadDatos($v) * 8) {
                return $v;
            }
        }
        throw new InvalidArgumentException('El texto es demasiado largo para el código QR.');
    }

    /** @param int[] $bytes @return int[] palabras de datos con relleno */
    private static function codificarDatos(array $bytes, int $v): array
    {
        $cap = self::capacidadDatos($v);
        $bits = [0, 1, 0, 0];
        $bitsCuenta = $v < 10 ? 8 : 16;
        $n = count($bytes);
        for ($i = 0; $i < $bitsCuenta; $i++) {
            $bits[] = ($n >> ($bitsCuenta - 1 - $i)) & 1;
        }
        foreach ($bytes as $b) {
            for ($i = 0; $i < 8; $i++) {
                $bits[] = ($b >> (7 - $i)) & 1;
            }
        }
        $limite = $cap * 8;
        $relleno = min(4, $limite - count($bits));
        for ($i = 0; $i < $relleno; $i++) {
            $bits[] = 0;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }
        $palabras = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $w = 0;
            for ($j = 0; $j < 8; $j++) {
                $w = ($w << 1) | $bits[$i + $j];
            }
            $palabras[] = $w;
        }
        $pad = [0xEC, 0x11];
        $i = 0;
        while (count($palabras) < $cap) {
            $palabras[] = $pad[$i % 2];
            $i++;
        }
        return $palabras;
    }

    /** @param int[] $palabras @return int[] */
    private static function intercalar(array $palabras, int $v): array
    {
        [$ec, $grupos] = self::BLOQUES_M[$v];
        $bloques = [];
        $pos = 0;
        foreach ($grupos as [$n, $d]) {
            for ($k = 0; $k < $n; $k++) {
                $bloques[] = array_slice($palabras, $pos, $d);
                $pos += $d;
            }
        }
        $ecs = [];
        $maxLen = 0;
        foreach ($bloques as $b) {
            $ecs[] = self::restoRs($b, $ec);
            $maxLen = max($maxLen, count($b));
        }
        $res = [];
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($bloques as $b) {
                if ($i < count($b)) {
                    $res[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ec; $i++) {
            foreach ($ecs as $e) {
                $res[] = $e[$i];
            }
        }
        return $res;
    }

    private static function bchFormato(int $datos): int
    {
        $r = $datos << 10;
        for ($i = 4; $i >= 0; $i--) {
            if (($r >> ($i + 10)) & 1) {
                $r ^= 0x537 << $i;
            }
        }
        return (($datos << 10) | $r) ^ 0x5412;
    }

    /** Matriz con las funciones fijas dibujadas; null = módulo de datos libre. @return array<int, array<int, int|null>> */
    private static function matrizBase(int $v): array
    {
        $n = 17 + 4 * $v;
        $m = array_fill(0, $n, array_fill(0, $n, null));
        $buscador = static function (int $r, int $c) use (&$m, $n): void {
            for ($dr = -1; $dr <= 7; $dr++) {
                for ($dc = -1; $dc <= 7; $dc++) {
                    $rr = $r + $dr;
                    $cc = $c + $dc;
                    if ($rr >= 0 && $rr < $n && $cc >= 0 && $cc < $n) {
                        $en = $dr >= 0 && $dr <= 6 && $dc >= 0 && $dc <= 6;
                        $borde = $dr === 0 || $dr === 6 || $dc === 0 || $dc === 6;
                        $centro = $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4;
                        $m[$rr][$cc] = ($en && ($borde || $centro)) ? 1 : 0;
                    }
                }
            }
        };
        $buscador(0, 0);
        $buscador(0, $n - 7);
        $buscador($n - 7, 0);
        for ($i = 8; $i < $n - 8; $i++) {
            $m[6][$i] = $m[$i][6] = ($i % 2 === 0) ? 1 : 0;
        }
        $pos = self::ALINEACION[$v];
        $ultimo = $pos ? $pos[count($pos) - 1] : 0;
        foreach ($pos as $r) {
            foreach ($pos as $c) {
                // los tres que caerían sobre un buscador se omiten (los de la línea de temporización SÍ van)
                if (($r === $pos[0] && $c === $pos[0]) || ($r === $pos[0] && $c === $ultimo) || ($r === $ultimo && $c === $pos[0])) {
                    continue;
                }
                for ($dr = -2; $dr <= 2; $dr++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $m[$r + $dr][$c + $dc] = max(abs($dr), abs($dc)) !== 1 ? 1 : 0;
                    }
                }
            }
        }
        $m[$n - 8][8] = 1; // módulo oscuro fijo
        for ($i = 0; $i < 9; $i++) {
            if ($m[8][$i] === null) { $m[8][$i] = 0; }
            if ($m[$i][8] === null) { $m[$i][8] = 0; }
        }
        for ($i = 0; $i < 8; $i++) {
            if ($m[8][$n - 1 - $i] === null) { $m[8][$n - 1 - $i] = 0; }
            if ($m[$n - 1 - $i][8] === null) { $m[$n - 1 - $i][8] = 0; }
        }
        if ($v >= 7) {
            for ($i = 0; $i < 6; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $m[$i][$n - 11 + $j] = 0;
                    $m[$n - 11 + $j][$i] = 0;
                }
            }
        }
        return $m;
    }

    private static function mascara(int $k, int $r, int $c): bool
    {
        switch ($k) {
            case 0: return ($r + $c) % 2 === 0;
            case 1: return $r % 2 === 0;
            case 2: return $c % 3 === 0;
            case 3: return ($r + $c) % 3 === 0;
            case 4: return (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0;
            case 5: return ($r * $c) % 2 + ($r * $c) % 3 === 0;
            case 6: return (($r * $c) % 2 + ($r * $c) % 3) % 2 === 0;
            default: return (($r + $c) % 2 + ($r * $c) % 3) % 2 === 0;
        }
    }

    /** @param array<int, array<int, int>> $m */
    private static function penalizacion(array $m): int
    {
        $n = count($m);
        $p = 0;
        $trans = [];
        for ($c = 0; $c < $n; $c++) {
            $col = [];
            for ($r = 0; $r < $n; $r++) {
                $col[] = $m[$r][$c];
            }
            $trans[] = $col;
        }
        foreach ([$m, $trans] as $cuadro) {
            foreach ($cuadro as $fila) {
                $run = 1;
                for ($i = 1; $i < $n; $i++) {
                    if ($fila[$i] === $fila[$i - 1]) {
                        $run++;
                    } else {
                        if ($run >= 5) { $p += $run - 2; }
                        $run = 1;
                    }
                }
                if ($run >= 5) { $p += $run - 2; }
                $cadena = implode('', $fila);
                $p += 40 * (substr_count($cadena, '10111010000') + substr_count($cadena, '00001011101'));
            }
        }
        for ($r = 0; $r < $n - 1; $r++) {
            for ($c = 0; $c < $n - 1; $c++) {
                if ($m[$r][$c] === $m[$r][$c + 1] && $m[$r][$c] === $m[$r + 1][$c] && $m[$r][$c] === $m[$r + 1][$c + 1]) {
                    $p += 3;
                }
            }
        }
        $oscuros = 0;
        foreach ($m as $fila) {
            $oscuros += array_sum($fila);
        }
        $p += 10 * intdiv(abs(intdiv($oscuros * 100, $n * $n) - 50), 5);
        return $p;
    }

    /** @param array<int, array<int, int>> $m */
    private static function ponerFormato(array &$m, int $k): void
    {
        $n = count($m);
        $bits = self::bchFormato($k); // nivel M = 00 en los dos primeros bits
        for ($i = 0; $i < 15; $i++) {
            $b = ($bits >> $i) & 1;
            if ($i < 6) { $m[$i][8] = $b; }
            elseif ($i === 6) { $m[7][8] = $b; }
            elseif ($i === 7) { $m[8][8] = $b; }
            elseif ($i === 8) { $m[8][7] = $b; }
            else { $m[8][14 - $i] = $b; }
            if ($i < 8) { $m[8][$n - 1 - $i] = $b; }
            else { $m[$n - 15 + $i][8] = $b; }
        }
        $m[$n - 8][8] = 1;
    }

    /** @param array<int, array<int, int>> $m */
    private static function ponerVersion(array &$m, int $v): void
    {
        if ($v < 7) {
            return;
        }
        $n = count($m);
        $info = self::INFO_VERSION[$v];
        for ($i = 0; $i < 18; $i++) {
            $b = ($info >> $i) & 1;
            $m[intdiv($i, 3)][$n - 11 + $i % 3] = $b;
            $m[$n - 11 + $i % 3][intdiv($i, 3)] = $b;
        }
    }

    /** @return array<int, array<int, int>> matriz cuadrada de 0/1 (sin zona de silencio) */
    public static function matriz(string $texto): array
    {
        self::iniciarCampo();
        $bytes = $texto === '' ? [] : array_values(unpack('C*', $texto));
        $v = self::elegirVersion($bytes);
        $palabras = self::intercalar(self::codificarDatos($bytes, $v), $v);
        $bits = [];
        foreach ($palabras as $w) {
            for ($i = 0; $i < 8; $i++) {
                $bits[] = ($w >> (7 - $i)) & 1;
            }
        }
        $n = 17 + 4 * $v;
        $base = self::matrizBase($v);
        $res = [];
        foreach ($base as $r => $fila) {
            foreach ($fila as $c => $celda) {
                $res[$r][$c] = $celda !== null;
            }
        }
        $mejor = null;
        $mejorPen = PHP_INT_MAX;
        for ($k = 0; $k < 8; $k++) {
            $m = [];
            foreach ($base as $r => $fila) {
                foreach ($fila as $c => $celda) {
                    $m[$r][$c] = $celda ?? 0;
                }
            }
            // colocar datos en zigzag desde abajo a la derecha, saltando la columna de temporización
            $i = 0;
            $col = $n - 1;
            $arriba = true;
            while ($col > 0) {
                if ($col === 6) {
                    $col--;
                }
                for ($paso = 0; $paso < $n; $paso++) {
                    $r = $arriba ? $n - 1 - $paso : $paso;
                    foreach ([$col, $col - 1] as $c) {
                        if (!$res[$r][$c]) {
                            $m[$r][$c] = $i < count($bits) ? $bits[$i] : 0;
                            $i++;
                        }
                    }
                }
                $arriba = !$arriba;
                $col -= 2;
            }
            for ($r = 0; $r < $n; $r++) {
                for ($c = 0; $c < $n; $c++) {
                    if (!$res[$r][$c] && self::mascara($k, $r, $c)) {
                        $m[$r][$c] ^= 1;
                    }
                }
            }
            self::ponerFormato($m, $k);
            self::ponerVersion($m, $v);
            $pen = self::penalizacion($m);
            if ($pen < $mejorPen) {
                $mejorPen = $pen;
                $mejor = $m;
            }
        }
        return $mejor;
    }
}
