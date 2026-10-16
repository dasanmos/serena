<?php
declare(strict_types=1);

/**
 * Escritor de PDF para certificados (sin librerías): Helvetica estándar, texto con ajuste y justificado,
 * líneas, rectángulos, imágenes JPEG y códigos QR dibujados como rectángulos vectoriales.
 *
 * Es el porte 1:1 de un prototipo que se validó con qpdf (estructura), pdftotext (tildes) y la lectura del
 * QR sobre el PDF renderizado. Las coordenadas se miden DESDE ARRIBA de la página (como en un layout web).
 *
 *   $pdf = new CertificadoPDF();
 *   CertificadoPDF::dibujar($pdf, $institucion, $certificado, QrCode::matriz($url));
 *   $bytes = $pdf->contenido();
 */
final class CertificadoPDF
{
    private const ANCHOS_REG = '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584';
    private const ANCHOS_NEG = '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,556,556,778,556,556,500,389,280,389,584';
    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** @var array<int, array<int,int>>|null */
    private static ?array $tablas = null;

    private float $ancho;
    private float $alto;
    /** @var string[] cuerpos de los objetos PDF (el id es el índice + 1) */
    private array $objetos = [];
    /** @var array<int, array{0:int, 1:array<string,int>}> [id del contenido, imágenes de la página] */
    private array $paginas = [];
    /** @var string[] */
    private array $flujo = [];
    /** @var array<string,int> */
    private array $imgs = [];
    private int $nImg = 0;
    private int $fReg;
    private int $fNeg;
    public string $titulo = '';
    public string $autor = '';
    /** Se ejecuta al armar el PDF, una vez por página: function (self $p, int $indice, int $total). Sirve para pies con «Hoja n de N». */
    public ?\Closure $alCerrar = null;

    public function __construct(float $ancho = 612.0, float $alto = 792.0)
    {
        $this->ancho = $ancho;
        $this->alto = $alto;
        $this->fReg = $this->obj('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $this->fNeg = $this->obj('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    private function obj(string $cuerpo): int
    {
        $this->objetos[] = $cuerpo;
        return count($this->objetos);
    }

    /** UTF-8 → WinAnsi (Windows-1252), que es lo que entiende la fuente estándar. */
    private static function aBytes(string $t): string
    {
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $t);
            if ($r !== false) {
                return $r;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        }
        return $t;
    }

    private static function esc(string $b): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $b);
    }

    /** @return array<int,int> */
    private static function tabla(int $i): array
    {
        if (self::$tablas === null) {
            $bases = [
                'a' => [0xE0, 0xE1, 0xE2, 0xE3, 0xE4, 0xE5], 'c' => [0xE7], 'e' => [0xE8, 0xE9, 0xEA, 0xEB], 'i' => [0xEC, 0xED, 0xEE, 0xEF], 'n' => [0xF1],
                'o' => [0xF2, 0xF3, 0xF4, 0xF5, 0xF6], 'u' => [0xF9, 0xFA, 0xFB, 0xFC], 'y' => [0xFD, 0xFF], 'A' => [0xC0, 0xC1, 0xC2, 0xC3, 0xC4, 0xC5],
                'C' => [0xC7], 'E' => [0xC8, 0xC9, 0xCA, 0xCB], 'I' => [0xCC, 0xCD, 0xCE, 0xCF], 'N' => [0xD1], 'O' => [0xD2, 0xD3, 0xD4, 0xD5, 0xD6],
                'U' => [0xD9, 0xDA, 0xDB, 0xDC], 'Y' => [0xDD],
            ];
            $extra = [0xBF => 611, 0xA1 => 333, 0xAB => 556, 0xBB => 556, 0x95 => 350, 0x96 => 556, 0x97 => 1000, 0x93 => 333, 0x94 => 333, 0x91 => 222, 0x92 => 222, 0x85 => 1000, 0xB7 => 333, 0xBA => 365];
            self::$tablas = [];
            foreach ([self::ANCHOS_REG, self::ANCHOS_NEG] as $k => $lista) {
                $t = [];
                foreach (explode(',', $lista) as $j => $v) {
                    $t[32 + $j] = (int) $v;
                }
                foreach ($bases as $letra => $codigos) {
                    foreach ($codigos as $c) {
                        $t[$c] = $t[ord($letra)];
                    }
                }
                foreach ($extra as $c => $a) {
                    $t[$c] = $a;
                }
                self::$tablas[$k] = $t;
            }
        }
        return self::$tablas[$i];
    }

    public static function hexARgb(string $h): array
    {
        $h = ltrim($h, '#');
        if (!preg_match('/^[0-9A-Fa-f]{6}$/', $h)) {
            $h = 'AD6B05';
        }
        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }

    public static function fechaLarga(string $iso): string
    {
        $t = strtotime($iso);
        if ($t === false) {
            return $iso;
        }
        return (int) date('j', $t) . ' de ' . self::MESES[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
    }

    /** @return array{0:int, 1:string, 2:int} día, nombre del mes y año de una fecha ISO */
    public static function partesFecha(string $iso): array
    {
        $t = strtotime($iso);
        if ($t === false) {
            $t = time();
        }
        return [(int) date('j', $t), self::MESES[(int) date('n', $t) - 1], (int) date('Y', $t)];
    }

    /** Lee un JPEG: [bytes, ancho, alto, canales] o null si no se puede usar (solo escala de grises o RGB). */
    public static function imagenJpeg(string $bytes): ?array
    {
        $i = @getimagesizefromstring($bytes);
        if ($i === false || ($i['mime'] ?? '') !== 'image/jpeg') {
            return null;
        }
        $canales = (int) ($i['channels'] ?? 3);
        if ($canales !== 1 && $canales !== 3) {
            return null;
        }
        return [$bytes, (int) $i[0], (int) $i[1], $canales];
    }

    // ------------------------------------------------------------------
    // Primitivas
    // ------------------------------------------------------------------

    public function anchoTexto(string $t, float $tam, bool $negrita = false): float
    {
        $tabla = self::tabla($negrita ? 1 : 0);
        $b = self::aBytes($t);
        $suma = 0;
        $n = strlen($b);
        for ($i = 0; $i < $n; $i++) {
            $suma += $tabla[ord($b[$i])] ?? 556;
        }
        return $suma * $tam / 1000.0;
    }

    private function y(float $y): float
    {
        return $this->alto - $y;
    }

    /** @param float[] $c */
    private function col(array $c): string
    {
        return sprintf('%.3F %.3F %.3F', $c[0], $c[1], $c[2]);
    }

    /** @param float[] $color */
    public function texto(float $x, float $y, string $t, float $tam = 11, bool $negrita = false, array $color = [0.1, 0.1, 0.1], string $alin = 'l', float $anchoCaja = 0.0, float $tw = 0.0): void
    {
        $w = $this->anchoTexto($t, $tam, $negrita);
        if ($alin === 'c') {
            $x += ($anchoCaja - $w) / 2;
        } elseif ($alin === 'r') {
            $x += $anchoCaja - $w;
        }
        $this->flujo[] = sprintf('BT /%s %.2F Tf %s rg %.3F Tw %.2F %.2F Td (%s) Tj ET', $negrita ? 'F2' : 'F1', $tam, $this->col($color), $tw, $x, $this->y($y), self::esc(self::aBytes($t)));
    }

    /** Parte una palabra que no cabe en $ancho en trozos que sí caben. @return string[] */
    private function partirPalabra(string $pal, float $ancho, float $tam, bool $negrita): array
    {
        $trozos = [];
        $actual = '';
        foreach (preg_split('//u', $pal, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if ($actual !== '' && $this->anchoTexto($actual . $ch, $tam, $negrita) > $ancho) {
                $trozos[] = $actual;
                $actual = '';
            }
            $actual .= $ch;
        }
        if ($actual !== '') {
            $trozos[] = $actual;
        }
        return $trozos ?: [$pal];
    }

    /** @return array<int, array{0:string, 1:bool}> [línea, ¿es la última del párrafo?] */
    public function lineasDe(string $t, float $ancho, float $tam, bool $negrita = false): array
    {
        $res = [];
        foreach (explode("\n", $t) as $parrafo) {
            $actual = '';
            foreach (explode(' ', $parrafo) as $pal) {
                if ($pal !== '' && $this->anchoTexto($pal, $tam, $negrita) > $ancho) {
                    $trozos = $this->partirPalabra($pal, $ancho, $tam, $negrita);
                    foreach ($trozos as $k => $trozo) {
                        if ($k === 0 && $actual !== '' && $this->anchoTexto($actual . ' ' . $trozo, $tam, $negrita) <= $ancho) {
                            $actual .= ' ' . $trozo;
                            continue;
                        }
                        if ($actual !== '') {
                            $res[] = [$actual, false];
                        }
                        $actual = $trozo;
                    }
                    continue;
                }
                $prueba = $actual === '' ? $pal : $actual . ' ' . $pal;
                if ($this->anchoTexto($prueba, $tam, $negrita) <= $ancho || $actual === '') {
                    $actual = $prueba;
                } else {
                    $res[] = [$actual, false];
                    $actual = $pal;
                }
            }
            $res[] = [$actual, true];
        }
        return $res;
    }

    /** Mayor tamaño (entre $max y $min) con el que $t cabe en una sola línea de $ancho; si ni con $min cabe, devuelve $min. */
    public function tamanoQueCabe(string $t, float $ancho, float $max, float $min, bool $negrita = false): float
    {
        for ($tam = $max; $tam > $min; $tam -= 0.5) {
            if ($this->anchoTexto($t, $tam, $negrita) <= $ancho) {
                return $tam;
            }
        }
        return $min;
    }

    /** Escribe un párrafo (alin: l, c, j) y devuelve la y siguiente. @param float[] $color */
    public function parrafo(float $x, float $y, float $ancho, string $t, float $tam = 11, float $interlinea = 16, bool $negrita = false, string $alin = 'l', array $color = [0.1, 0.1, 0.1]): float
    {
        foreach ($this->lineasDe($t, $ancho, $tam, $negrita) as [$linea, $ultima]) {
            if ($alin === 'j' && !$ultima && strpos($linea, ' ') !== false) {
                $extra = ($ancho - $this->anchoTexto($linea, $tam, $negrita)) / substr_count($linea, ' ');
                $this->texto($x, $y, $linea, $tam, $negrita, $color, 'l', 0.0, $extra);
            } else {
                $this->texto($x, $y, $linea, $tam, $negrita, $color, $alin === 'c' ? 'c' : 'l', $ancho);
            }
            $y += $interlinea;
        }
        return $y;
    }

    /** @param float[] $color */
    public function linea(float $x1, float $y1, float $x2, float $y2, float $grosor = 0.8, array $color = [0.5, 0.5, 0.5]): void
    {
        $this->flujo[] = sprintf('%.2F w %s RG %.2F %.2F m %.2F %.2F l S', $grosor, $this->col($color), $x1, $this->y($y1), $x2, $this->y($y2));
    }

    /** @param float[]|null $relleno @param float[]|null $borde */
    public function rect(float $x, float $y, float $w, float $h, ?array $relleno = null, ?array $borde = null, float $grosor = 0.8): void
    {
        $op = '';
        if ($relleno !== null) {
            $op .= $this->col($relleno) . ' rg ';
        }
        if ($borde !== null) {
            $op .= $this->col($borde) . sprintf(' RG %.2F w ', $grosor);
        }
        $pinta = ($relleno !== null && $borde !== null) ? 'B' : ($relleno !== null ? 'f' : 'S');
        $this->flujo[] = sprintf('%s%.2F %.2F %.2F %.2F re %s', $op, $x, $this->y($y + $h), $w, $h, $pinta);
    }

    /** @param array{0:string,1:int,2:int,3:int} $img resultado de imagenJpeg() */
    public function imagen(array $img, float $x, float $y, float $w, float $h): void
    {
        [$datos, $wp, $hp, $canales] = $img;
        $this->nImg++;
        $nombre = 'Im' . $this->nImg;
        $id = $this->obj(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n", $wp, $hp, $canales === 1 ? '/DeviceGray' : '/DeviceRGB', strlen($datos)) . $datos . "\nendstream");
        $this->imgs[$nombre] = $id;
        $this->flujo[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $this->y($y + $h), $nombre);
    }

    /** Dibuja la matriz del QR (1 = oscuro) como rectángulos vectoriales de lado total $tam. @param array<int, array<int,int>> $matriz */
    public function qr(array $matriz, float $x, float $y, float $tam): void
    {
        $n = count($matriz);
        $m = $tam / $n;
        $this->flujo[] = '0.000 0.000 0.000 rg';
        for ($r = 0; $r < $n; $r++) {
            $c = 0;
            while ($c < $n) {
                if ($matriz[$r][$c]) {
                    $ini = $c;
                    while ($c < $n && $matriz[$r][$c]) {
                        $c++;
                    }
                    $this->flujo[] = sprintf('%.3F %.3F %.3F %.3F re f', $x + $ini * $m, $this->y($y + ($r + 1) * $m), ($c - $ini) * $m, $m + 0.02);
                } else {
                    $c++;
                }
            }
        }
    }

    public function nuevaPagina(): void
    {
        $this->cerrarPagina();
    }

    private function cerrarPagina(): void
    {
        if (!$this->flujo && $this->paginas) {
            return;
        }
        $this->paginas[] = [$this->flujo, $this->imgs];
        $this->flujo = [];
        $this->imgs = [];
    }

    /** Páginas ya cerradas + la que se está escribiendo. */
    public function numeroPaginas(): int
    {
        return count($this->paginas) + ($this->flujo ? 1 : 0);
    }

    /** Termina el documento y devuelve los bytes del PDF. */
    public function contenido(): string
    {
        $this->cerrarPagina();
        $total = count($this->paginas);
        $contenidos = [];
        foreach ($this->paginas as $i => [$flujoPag, $imgsPag]) {
            $this->flujo = $flujoPag;
            $this->imgs = $imgsPag;
            if ($this->alCerrar !== null) {
                ($this->alCerrar)($this, $i, $total);
            }
            $cont = implode("\n", $this->flujo);
            $contenidos[] = [$this->obj(sprintf("<< /Length %d >>\nstream\n", strlen($cont)) . $cont . "\nendstream"), $this->imgs];
        }
        $this->flujo = [];
        $this->imgs = [];
        $idsPag = [];
        $idPages = count($this->objetos) + $total + 1;
        foreach ($contenidos as [$idc, $imgs]) {
            $xo = '';
            if ($imgs) {
                $partes = [];
                foreach ($imgs as $k => $v) {
                    $partes[] = '/' . $k . ' ' . $v . ' 0 R';
                }
                $xo = ' /XObject << ' . implode(' ', $partes) . ' >>';
            }
            $idsPag[] = $this->obj(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >>%s >> /Contents %d 0 R >>', $idPages, $this->ancho, $this->alto, $this->fReg, $this->fNeg, $xo, $idc));
        }
        $kids = [];
        foreach ($idsPag as $i) {
            $kids[] = $i . ' 0 R';
        }
        $this->obj(sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($idsPag)));
        $info = $this->obj(sprintf('<< /Title (%s) /Author (%s) /Producer (Serena) >>', self::esc(self::aBytes($this->titulo)), self::esc(self::aBytes($this->autor))));
        $cat = $this->obj(sprintf('<< /Type /Catalog /Pages %d 0 R >>', $idPages));

        $buf = "%PDF-1.4\n";
        $offs = [];
        foreach ($this->objetos as $i => $o) {
            $offs[] = strlen($buf);
            $buf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
        }
        $ini = strlen($buf);
        $buf .= 'xref' . "\n" . '0 ' . (count($this->objetos) + 1) . "\n" . "0000000000 65535 f \n";
        foreach ($offs as $o) {
            $buf .= sprintf("%010d 00000 n \n", $o);
        }
        $buf .= sprintf("trailer\n<< /Size %d /Root %d 0 R /Info %d 0 R >>\nstartxref\n%d\n%%%%EOF", count($this->objetos) + 1, $cat, $info, $ini);
        return $buf;
    }

    // ------------------------------------------------------------------
    // Diseño del certificado (carta, con borde, encabezado institucional, cuerpo, firmas y pie con QR)
    // ------------------------------------------------------------------

    /** @return float[] [ancho, alto] ajustados a la caja manteniendo la proporción */
    private static function ajustar(int $wp, int $hp, float $maxW, float $maxH): array
    {
        $esc = min($maxW / max(1, $wp), $maxH / max(1, $hp));
        return [$wp * $esc, $hp * $esc];
    }

    /** @param float[] $color */
    private static function borde(self $p, array $color): void
    {
        $p->rect(24, 24, 564, 744, null, $color, 2.2);
        $p->rect(30, 30, 552, 732, null, $color, 0.6);
    }

    /** Sube un color hacia el blanco (0 = igual, 1 = blanco) para fondos suaves. @param float[] $c @return float[] */
    private static function aclarar(array $c, float $f): array
    {
        return [$c[0] + (1 - $c[0]) * $f, $c[1] + (1 - $c[1]) * $f, $c[2] + (1 - $c[2]) * $f];
    }

    /**
     * Encabezado completo (primera hoja): logo, nombre, datos legales y ubicación. Devuelve la y donde termina (ya con su línea).
     * @param array<string,mixed> $ins @param float[] $color @param float[] $negro @param float[] $gris
     */
    private static function encabezadoCompleto(self $p, array $ins, array $color, array $negro, array $gris, float $X, float $ANCHO): float
    {
        $y = 52.0;
        $xTxt = $X;
        $wTxt = $ANCHO;
        $altoLogo = 0.0;
        if (!empty($ins['logo'])) {
            [$lw, $lh] = self::ajustar($ins['logo'][1], $ins['logo'][2], 84, 84);
            $p->imagen($ins['logo'], $X, $y, $lw, $lh);
            $xTxt = $X + 98;
            $wTxt = $ANCHO - 98;
            $altoLogo = $lh;
        }
        $nombre = mb_strtoupper((string) $ins['nombre'], 'UTF-8');
        $tamNombre = $p->tamanoQueCabe($nombre, $wTxt, 15, 12, true);
        $yt = $y + 12;
        $yt = $p->parrafo($xTxt, $yt, $wTxt, $nombre, $tamNombre, $tamNombre + 3, true, 'c', $negro);
        $lineas = [];
        if (!empty($ins['naturaleza'])) {
            $lineas[] = (string) $ins['naturaleza'];
        }
        $ids = [];
        if (!empty($ins['dane'])) { $ids[] = 'Código DANE: ' . $ins['dane']; }
        if (!empty($ins['nit'])) { $ids[] = 'NIT: ' . $ins['nit']; }
        if (!empty($ins['icfes'])) { $ids[] = 'ICFES: ' . $ins['icfes']; }
        if ($ids) {
            $lineas[] = implode(' · ', $ids);
        }
        if (!empty($ins['resolucion'])) {
            $lineas[] = (string) $ins['resolucion'];
        }
        $lugar = implode(', ', array_filter([(string) ($ins['municipio'] ?? ''), (string) ($ins['departamento'] ?? '')]));
        $ub = implode(' · ', array_filter([(string) ($ins['direccion'] ?? ''), $lugar, (string) ($ins['telefono'] ?? ''), (string) ($ins['correo'] ?? '')]));
        if ($ub !== '') {
            $lineas[] = $ub;
        }
        foreach ($lineas as $l) {
            $yt = $p->parrafo($xTxt, $yt + 1, $wTxt, $l, 8.6, 11, false, 'c', $gris);
        }
        $y = max($y + $altoLogo, $yt) + 10;
        $p->linea($X, $y, $X + $ANCHO, $y, 1.2, $color);
        return $y;
    }

    /** Encabezado compacto de las hojas siguientes. Devuelve la y donde empieza el contenido. @param float[] $color @param float[] $negro @param float[] $gris */
    private static function encabezadoCompacto(self $p, array $ins, string $titulo, array $color, array $negro, array $gris, float $X, float $ANCHO): float
    {
        $y = 54.0;
        $xTxt = $X;
        $wTxt = $ANCHO;
        if (!empty($ins['logo'])) {
            [$lw, $lh] = self::ajustar($ins['logo'][1], $ins['logo'][2], 30, 30);
            $p->imagen($ins['logo'], $X, $y - 8, $lw, $lh);
            $xTxt = $X + 40;
            $wTxt = $ANCHO - 40;
        }
        $nombre = mb_strtoupper((string) $ins['nombre'], 'UTF-8');
        $p->texto($xTxt, $y + 6, $nombre, $p->tamanoQueCabe($nombre, $wTxt, 11, 8, true), true, $negro, 'l', $wTxt);
        $p->texto($xTxt, $y + 19, mb_strtoupper($titulo, 'UTF-8') . ' (continuación)', $p->tamanoQueCabe($titulo . ' (continuación)', $wTxt, 8.5, 6.5), false, $gris, 'l', $wTxt);
        $p->linea($X, $y + 28, $X + $ANCHO, $y + 28, 1.0, $color);
        return $y + 52;
    }

    /**
     * Dibuja el certificado con paginación real: el cuerpo, la tabla y las firmas pasan a otra hoja cuando no caben
     * (siempre dentro del borde), cada hoja repite el borde, y el bloque de verificación (con QR) queda al final de la última.
     *
     * @param array<string,mixed> $ins  datos de la institución (ver Certificados::datosInstitucion)
     * @param array<string,mixed> $cert titulo, cuerpo, tabla?, fecha_texto, url_corta, codigo_fmt, emitido, folio
     * @param array<int, array<int,int>>|null $matriz matriz del QR (null = versión sin QR: solo código y dirección)
     */
    public static function dibujar(self $p, array $ins, array $cert, ?array $matriz): void
    {
        $color = self::hexARgb((string) ($ins['color'] ?? '#AD6B05'));
        $gris = [0.35, 0.35, 0.35];
        $negro = [0.1, 0.1, 0.1];
        $X = 60.0;
        $ANCHO = 492.0;
        $LIM = 736.0;        // última línea base permitida en hojas intermedias (el borde interior está en y = 762)
        $titulo = (string) $cert['titulo'];
        $p->titulo = $titulo . ' - ' . $ins['nombre'];
        $p->autor = (string) $ins['nombre'];
        $primeraHoja = $p->numeroPaginas(); // en un lote, índice (desde 0) de la primera hoja de ESTE certificado

        // ---- Medida del bloque de verificación (para reservarle sitio al final de la última hoja) ----
        $conQr = $matriz !== null;
        $xt = $X + ($conQr ? 92 : 0);
        $wt = $ANCHO - ($conQr ? 92 : 0);
        $l1 = count($p->lineasDe('Escanee el código QR o ingrese a la dirección y digite el código:', $wt, 8.5));
        if (!$conQr) {
            $l1 = count($p->lineasDe('Ingrese a la dirección y digite el código para verificar este documento:', $wt, 8.5));
        }
        $l2 = count($p->lineasDe((string) $cert['url_corta'], $wt, 8.5, true));
        $nota = sprintf('Emitido el %s · Folio N.º %s. Es válido solo si la verificación en línea lo confirma.', $cert['emitido'], $cert['folio']);
        $l3 = count($p->lineasDe($nota, $wt, 8));
        $altoTexto = 13 + $l1 * 11 + 1 + $l2 * 11 + 3 + 14 + $l3 * 10.5 + 2;
        $altoPie = max($conQr ? 78.0 : 0.0, $altoTexto);
        $yPie = 754.0 - $altoPie;           // arriba del bloque de verificación
        $limFinal = $yPie - 14.0;           // el contenido de la última hoja debe terminar antes

        // ---- Primera hoja ----
        self::borde($p, $color);
        $y = self::encabezadoCompleto($p, $ins, $color, $negro, $gris, $X, $ANCHO);

        $nuevaHoja = static function () use ($p, $ins, $titulo, $color, $negro, $gris, $X, $ANCHO, &$y): void {
            $p->nuevaPagina();
            self::borde($p, $color);
            $y = self::encabezadoCompacto($p, $ins, $titulo, $color, $negro, $gris, $X, $ANCHO);
        };
        /** Escribe un párrafo que puede continuar en otra hoja. */
        $flujo = static function (string $texto, float $tam, float $inter, string $alin, array $col, bool $neg = false, float $limite = 736.0) use ($p, $X, $ANCHO, &$y, $nuevaHoja): void {
            foreach ($p->lineasDe($texto, $ANCHO, $tam, $neg) as [$linea, $ultima]) {
                if ($y > $limite) {
                    $nuevaHoja();
                }
                if ($alin === 'j' && !$ultima && strpos($linea, ' ') !== false) {
                    $p->texto($X, $y, $linea, $tam, $neg, $col, 'l', 0.0, ($ANCHO - $p->anchoTexto($linea, $tam, $neg)) / substr_count($linea, ' '));
                } else {
                    $p->texto($X, $y, $linea, $tam, $neg, $col, $alin === 'c' ? 'c' : 'l', $ANCHO);
                }
                $y += $inter;
            }
        };

        // ---- Título (si es largo, se achica o pasa a dos líneas: nunca se sale del borde) ----
        $y += 36;
        $tit = mb_strtoupper($titulo, 'UTF-8');
        $tam = $p->tamanoQueCabe($tit, $ANCHO, 17, 12.5, true);
        if ($p->anchoTexto($tit, $tam, true) > $ANCHO) {
            $lineasTit = $p->lineasDe($tit, $ANCHO, 13.5, true);
            foreach ($lineasTit as [$lt]) {
                $p->texto($X, $y, $lt, 13.5, true, $color, 'c', $ANCHO);
                $y += 18;
            }
            $y -= 18;
        } else {
            $p->texto($X, $y, $tit, $tam, true, $color, 'c', $ANCHO);
        }
        $p->linea($X + $ANCHO / 2 - 40, $y + 8, $X + $ANCHO / 2 + 40, $y + 8, 1.4, $color);
        $y += 34;

        // ---- Cuerpo ----
        $flujo((string) $cert['cuerpo'], 11.5, 17.5, 'j', [0.1, 0.1, 0.1], false, $LIM);

        // ---- Tabla (calificaciones, horas...) con filas que se ajustan y encabezado repetido ----
        if (!empty($cert['tabla'])) {
            $t = $cert['tabla'];
            $y += 6;
            $tot = max(1.0, (float) array_sum($t['anchos']));
            $anchos = array_map(static fn ($a): float => $a * $ANCHO / $tot, $t['anchos']);
            $fondoCab = self::aclarar($color, 0.78);
            $medir = static function (array $vals, bool $cab) use ($p, $anchos): array {
                $lineas = [];
                $max = 1;
                foreach ($anchos as $i => $w) {
                    $lineas[$i] = $p->lineasDe((string) ($vals[$i] ?? ''), max(10.0, $w - 8), 9, $cab);
                    $max = max($max, count($lineas[$i]));
                }
                return [$lineas, max(16.0, $max * 11.0 + 5.0)];
            };
            $dibujarFila = static function (array $vals, bool $cab) use ($p, $X, $ANCHO, $anchos, $fondoCab, $negro, $medir, &$y): void {
                [$lineas, $alto] = $medir($vals, $cab);
                if ($cab) {
                    $p->rect($X, $y, $ANCHO, $alto, $fondoCab, null);
                }
                $xx = $X;
                foreach ($anchos as $i => $w) {
                    foreach ($lineas[$i] as $k => [$linea]) {
                        $p->texto($xx + 4, $y + 11 + $k * 11, $linea, 9, $cab, $negro, $i === 0 ? 'l' : 'c', $w - 8);
                    }
                    $xx += $w;
                }
                $p->linea($X, $y + $alto, $X + $ANCHO, $y + $alto, 0.4, [0.75, 0.75, 0.75]);
                $y += $alto;
            };
            [, $altoCab] = $medir($t['encabezados'], true);
            if ($y + $altoCab + 40 > $LIM + 14) {
                $nuevaHoja();
            }
            $dibujarFila($t['encabezados'], true);
            foreach ($t['filas'] as $f) {
                if (isset($f['area'])) {
                    // Banda con el nombre del Área: nunca queda sola al final de una hoja (se pasa con su primera asignatura).
                    $lineasArea = $p->lineasDe(mb_strtoupper((string) $f['area'], 'UTF-8'), $ANCHO - 12, 9, true);
                    $altoArea = count($lineasArea) * 11.0 + 6.0;
                    if ($y + $altoArea + 22 > $LIM + 14) {
                        $nuevaHoja();
                        $dibujarFila($t['encabezados'], true);
                    }
                    $p->rect($X, $y, $ANCHO, $altoArea, self::aclarar($color, 0.9), null);
                    $p->rect($X, $y, 3, $altoArea, $color, null);
                    foreach ($lineasArea as $k => [$la]) {
                        $p->texto($X + 8, $y + 10.5 + $k * 11, $la, 9, true, $negro);
                    }
                    $y += $altoArea;
                    continue;
                }
                [, $alto] = $medir($f, false);
                if ($y + $alto > $LIM + 14) {
                    $nuevaHoja();
                    $dibujarFila($t['encabezados'], true);
                }
                $dibujarFila($f, false);
            }
            $y += 8;
        }

        // ---- Fecha y firmas (juntas: si no caben en esta hoja, pasan completas a la siguiente) ----
        $firmas = [[(string) ($ins['rector_nombre'] ?? ''), (string) (($ins['rector_cargo'] ?? '') ?: 'Rector(a)'), $ins['firma_rector'] ?? null]];
        if (!empty($ins['secretaria_nombre'])) {
            $firmas[] = [(string) $ins['secretaria_nombre'], (string) (($ins['secretaria_cargo'] ?? '') ?: 'Secretario(a) académico(a)'), $ins['firma_secretaria'] ?? null];
        }
        $nFecha = count($p->lineasDe((string) $cert['fecha_texto'], $ANCHO, 11.5));
        $altoCierre = 14 + $nFecha * 17 + 14 + 46 + 30;
        if ($y + $altoCierre > $limFinal) {
            $nuevaHoja();
        }
        $y += 12;
        $flujo((string) $cert['fecha_texto'], 11.5, 17, 'l', [0.1, 0.1, 0.1], false, $limFinal);
        $y += 14 + 46;
        $cols = count($firmas) === 2 ? [[$X, 214.0], [$X + 278, 214.0]] : [[$X + 139, 214.0]];
        foreach ($firmas as $i => [$nombre, $cargo, $firma]) {
            [$cx, $cw] = $cols[$i];
            if (!empty($firma)) {
                [$fw, $fh] = self::ajustar($firma[1], $firma[2], 140, 46);
                $p->imagen($firma, $cx + ($cw - $fw) / 2, $y - $fh - 1, $fw, $fh);
            }
            $p->linea($cx, $y, $cx + $cw, $y, 0.8, [0.2, 0.2, 0.2]);
            $p->texto($cx, $y + 13, $nombre, $p->tamanoQueCabe($nombre, $cw, 10.5, 7.5, true), true, $negro, 'c', $cw);
            $p->texto($cx, $y + 25, $cargo, $p->tamanoQueCabe($cargo, $cw, 9, 6.5), false, $gris, 'c', $cw);
        }

        // ---- Bloque de verificación: siempre al final de la última hoja, dentro del borde ----
        $p->linea($X, $yPie - 8, $X + $ANCHO, $yPie - 8, 0.6, [0.7, 0.7, 0.7]);
        if ($conQr) {
            $p->rect($X - 2, $yPie, 78, 78, [1, 1, 1], null);
            $p->qr($matriz, $X + 2, $yPie + 4, 70);
        }
        $yy = $yPie + 10;
        $p->texto($xt, $yy, 'Documento con verificación digital', 9.5, true, $color);
        $yy = $p->parrafo($xt, $yy + 13, $wt, $conQr ? 'Escanee el código QR o ingrese a la dirección y digite el código:' : 'Ingrese a la dirección y digite el código para verificar este documento:', 8.5, 11, false, 'l', $gris);
        $yy = $p->parrafo($xt, $yy + 1, $wt, (string) $cert['url_corta'], 8.5, 11, true, 'l', [0.1, 0.25, 0.55]);
        $p->texto($xt, $yy + 3, 'Código de verificación: ' . $cert['codigo_fmt'], 10, true, $negro);
        $p->parrafo($xt, $yy + 17, $wt, $nota, 8, 10.5, false, 'l', $gris);
        $p->texto($X, 782, 'Generado con Serena · Gestión escolar', 7, false, [0.6, 0.6, 0.6], 'c', $ANCHO);

        // ---- Numeración de hojas y aviso de continuación (por certificado, también dentro de un lote) ----
        $ultimaHoja = $p->numeroPaginas() - 1;
        $anterior = $p->alCerrar;
        $p->alCerrar = static function (self $pp, int $i, int $total) use ($anterior, $primeraHoja, $ultimaHoja, $ANCHO, $X, $gris, $cert): void {
            if ($anterior !== null) {
                $anterior($pp, $i, $total);
            }
            if ($i < $primeraHoja || $i > $ultimaHoja) {
                return;
            }
            $n = $ultimaHoja - $primeraHoja + 1;
            if ($n > 1) {
                $pp->texto($X, 43, sprintf('Hoja %d de %d · Código %s', $i - $primeraHoja + 1, $n, $cert['codigo_fmt']), 7.5, false, $gris, 'r', $ANCHO);
                if ($i < $ultimaHoja) {
                    $pp->texto($X, 752, 'Continúa en la siguiente hoja', 7.5, false, $gris, 'c', $ANCHO);
                }
            }
        };
    }
}
