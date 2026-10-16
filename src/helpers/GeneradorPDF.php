<?php
declare(strict_types=1);

/**
 * Generador de PDF mínimo y autocontenido — sin Composer, sin librerías externas,
 * sin archivos de fuente que vendorizar. Escribe directamente la sintaxis PDF 1.4
 * usando Helvetica/Helvetica-Bold (fuentes "estándar 14": cualquier lector de PDF
 * las trae incorporadas, así que no hace falta empaquetar ningún .php/.z de fuente).
 *
 * Solo cubre lo que Serena necesita para sus reportes: títulos, texto con salto de
 * línea automático, tablas simples y salto de página automático. No es una librería
 * general (no maneja imágenes ni fuentes personalizadas) — si más adelante hace falta
 * algo más elaborado, se amplía esta misma clase.
 */
class GeneradorPDF
{
    private const ANCHO_A4 = 595.28; // A4 vertical, en puntos
    private const ALTO_A4  = 841.89;
    private float $anchoPt = self::ANCHO_A4;
    private float $altoPt = self::ALTO_A4;
    private const MARGEN   = 50.0;

    /** @var array<int, string> cuerpo de cada objeto PDF, indexado desde 1 */
    private array $objetos = [1 => '', 2 => '']; // 1=Catalog, 2=Pages (se completan al final)
    /** @var int[] ids de objeto de cada página ya cerrada */
    private array $paginas = [];
    /** @var string[] comandos de contenido acumulados de la página actual */
    private array $flujoPagina = [];
    private float $x;
    private float $y;
    private const COLOR_TEXTO  = '0.17 0.17 0.17';
    private const COLOR_DORADO = '0.68 0.42 0.02';
    private const COLOR_SUAVE  = '0.45 0.45 0.45';
    private ?string $textoPie = null;
    private bool $pieVisible = true;
    /** @var array<int, array{flujo: string[], imgs: array<string,int>, pie: bool}> páginas cerradas; los objetos PDF se crean en contenido() para conocer el total de páginas */
    private array $paginasPend = [];
    private ?array $membrete = null;
    private string $colorAcento = '0.68 0.42 0.02';
    private float $margenSupPrimera = 50.0;
    private float $margenSupResto = 50.0;
    private float $margenInf = 50.0;
    private ?string $pdfFinal = null;
    private string $metaTitulo = '';
    private string $metaAutor = '';
    /** @var array<string, int> imágenes usadas en la página actual: nombre => id de objeto */
    private array $imagenesPagina = [];
    /** @var array<string, array> imágenes ya cargadas: ruta => [id objeto, ancho, alto] */
    private array $imagenesCargadas = [];
    private int $contadorImagenes = 0;
    private static ?array $tablasAnchos = null;
    private int $idFuenteRegular;
    private int $idFuenteNegrita;

    /** @param bool $horizontal true = A4 horizontal (informes con tablas anchas) */
    public function __construct(bool $horizontal = false)
    {
        if ($horizontal) {
            [$this->anchoPt, $this->altoPt] = [self::ALTO_A4, self::ANCHO_A4];
        }
        $this->idFuenteRegular = $this->nuevoObjeto('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
        $this->idFuenteNegrita = $this->nuevoObjeto('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>');
        $this->x = self::MARGEN;
        $this->y = $this->altoPt - $this->margenSupPrimera;
    }

    private function nuevoObjeto(string $cuerpo): int
    {
        $this->objetos[] = $cuerpo;
        return count($this->objetos);
    }

    private function cerrarPaginaActual(): void
    {
        $this->paginasPend[] = [
            'flujo' => $this->flujoPagina,
            'imgs' => $this->imagenesPagina,
            'pie' => $this->textoPie !== null && $this->pieVisible,
        ];
        $this->flujoPagina = [];
        $this->imagenesPagina = [];
    }

    /** Crea los objetos PDF de una página ya completa (con membrete y pie dibujados). */
    private function cerrarPaginaDefinitiva(array $flujo, array $imgs): void
    {
        $contenido = implode("\n", $flujo);
        $idContenido = $this->nuevoObjeto('<< /Length ' . strlen($contenido) . " >>\nstream\n{$contenido}\nendstream");
        $recursosImagenes = '';
        if (!empty($imgs)) {
            $entradas = '';
            foreach ($imgs as $nombre => $idObjeto) {
                $entradas .= sprintf('/%s %d 0 R ', $nombre, $idObjeto);
            }
            $recursosImagenes = ' /XObject << ' . $entradas . '>>';
        }
        $idPagina = $this->nuevoObjeto(sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 %d 0 R /F2 %d 0 R >>%s >> /Contents %d 0 R >>',
            $this->anchoPt, $this->altoPt, $this->idFuenteRegular, $this->idFuenteNegrita, $recursosImagenes, $idContenido
        ));
        $this->paginas[] = $idPagina;
    }

    private function nuevaPagina(): void
    {
        $this->cerrarPaginaActual();
        $this->x = self::MARGEN;
        $this->y = $this->altoPt - $this->margenSupResto;
    }

    private function saltoDePaginaSiHaceFalta(float $alturaNecesaria): void
    {
        if ($this->y - $alturaNecesaria < $this->margenInf) {
            $this->nuevaPagina();
        }
    }

    /** WinAnsiEncoding ≈ Windows-1252: cubre tildes, ñ, ¿ y ¡ del español. */
    private function aWinAnsi(string $texto): string
    {
        if (function_exists('iconv')) {
            $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $texto);
            if ($convertido !== false) return $convertido;
        }
        if (function_exists('mb_convert_encoding')) {
            $convertido = @mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
            if ($convertido !== false) return $convertido;
        }
        return $texto;
    }

    private function escaparTexto(string $texto): string
    {
        $texto = $this->aWinAnsi($texto);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    /** Reparte texto en líneas que quepan en el ancho disponible (estimación por ancho promedio de carácter: suficiente para reportes, no requiere métricas exactas de la fuente). */
    private function envolverTexto(string $texto, float $anchoDisponible, float $tamanoFuente): array
    {
        $anchoPromedioCaracter = $tamanoFuente * 0.52;
        $caracteresPorLinea = max(15, (int) floor($anchoDisponible / $anchoPromedioCaracter));
        $lineas = [];
        foreach (explode("\n", $texto) as $parrafo) {
            $lineas = array_merge($lineas, explode("\n", wordwrap($parrafo, $caracteresPorLinea, "\n", true)));
        }
        return $lineas;
    }

    private function escribirLinea(string $fuente, float $tamano, string $color, string $texto): void
    {
        $this->flujoPagina[] = sprintf(
            'BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET',
            $fuente, $tamano, $color, $this->x, $this->y, $this->escaparTexto($texto)
        );
    }

    public function titulo(string $texto): void
    {
        $this->saltoDePaginaSiHaceFalta(30);
        $this->y -= 4;
        $this->bloqueTexto('**' . str_replace('**', '', $texto) . '**', self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 17.0, 21.0, $this->colorAcento);
        $this->y -= 6;
    }

    public function subtitulo(string $texto): void
    {
        $this->saltoDePaginaSiHaceFalta(24);
        $this->y -= 2;
        $this->bloqueTexto('**' . str_replace('**', '', $texto) . '**', self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 12.0, 15.5, self::COLOR_TEXTO);
        $this->y -= 3;
    }

    public function textoSuave(string $texto): void
    {
        $this->bloqueTexto(str_replace('**', '', $texto), self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 9.0, 12.0, self::COLOR_SUAVE);
    }

    public function texto(string $texto, bool $negrita = false): void
    {
        $anchoUtil = $this->anchoPt - 2 * self::MARGEN;
        foreach (explode("\n", str_replace('**', '', $texto)) as $parrafo) {
            if (trim($parrafo) === '') {
                $this->y -= 6;
                continue;
            }
            $this->bloqueTexto($negrita ? '**' . $parrafo . '**' : $parrafo, self::MARGEN, $anchoUtil, 10.0, 14.0, self::COLOR_TEXTO);
        }
    }

    public function espaciador(float $alto = 10): void
    {
        $this->y -= $alto;
    }

    public function lineaSeparadora(): void
    {
        $this->saltoDePaginaSiHaceFalta(10);
        $this->y -= 6;
        $this->flujoPagina[] = sprintf(
            '0.85 0.85 0.85 RG %.2F %.2F m %.2F %.2F l S',
            $this->x, $this->y, $this->anchoPt - self::MARGEN, $this->y
        );
        $this->y -= 8;
    }

    /**
     * Tabla: el texto de cada celda se ajusta a su columna (varias líneas si hace falta), el encabezado se repite en cada
     * hoja y una fila nunca se parte entre dos hojas. Los anchos de columna se reparten según el contenido.
     */
    public function tabla(array $encabezados, array $filas): void
    {
        $this->tablaAjustada($encabezados, $filas);
    }

    // ==================================================================
    // Soporte para documentos largos (manuales): pie de página, texto con
    // negritas y ancho real de Helvetica, listas, cuadros e imágenes.
    // No cambia nada de lo anterior: los reportes existentes siguen igual.
    // ==================================================================

    private const ANCHOS_REGULAR = '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584';
    private const ANCHOS_NEGRITA = '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584';

    /** @return array<int,int> ancho (en milésimas de em) por código WinAnsi */
    private static function tablaAnchos(bool $negrita): array
    {
        if (self::$tablasAnchos === null) {
            self::$tablasAnchos = [
                0 => self::construirTabla(self::ANCHOS_REGULAR),
                1 => self::construirTabla(self::ANCHOS_NEGRITA),
            ];
        }
        return self::$tablasAnchos[$negrita ? 1 : 0];
    }

    private static function construirTabla(string $lista): array
    {
        $tabla = [];
        foreach (explode(',', $lista) as $i => $valor) {
            $tabla[32 + $i] = (int) $valor;
        }
        // Letras con tilde: mismo ancho que la letra base (códigos Windows-1252).
        $bases = [
            'a' => [0xE0, 0xE1, 0xE2, 0xE3, 0xE4, 0xE5], 'c' => [0xE7],
            'e' => [0xE8, 0xE9, 0xEA, 0xEB], 'i' => [0xEC, 0xED, 0xEE, 0xEF], 'n' => [0xF1],
            'o' => [0xF2, 0xF3, 0xF4, 0xF5, 0xF6], 'u' => [0xF9, 0xFA, 0xFB, 0xFC], 'y' => [0xFD, 0xFF],
            'A' => [0xC0, 0xC1, 0xC2, 0xC3, 0xC4, 0xC5], 'C' => [0xC7],
            'E' => [0xC8, 0xC9, 0xCA, 0xCB], 'I' => [0xCC, 0xCD, 0xCE, 0xCF], 'N' => [0xD1],
            'O' => [0xD2, 0xD3, 0xD4, 0xD5, 0xD6], 'U' => [0xD9, 0xDA, 0xDB, 0xDC], 'Y' => [0xDD],
        ];
        foreach ($bases as $letra => $codigos) {
            foreach ($codigos as $codigo) {
                $tabla[$codigo] = $tabla[ord($letra)];
            }
        }
        // Signos frecuentes en español: ¿ ¡ « » • – — comillas.
        foreach ([0xBF => 611, 0xA1 => 333, 0xAB => 556, 0xBB => 556, 0x95 => 350, 0x96 => 556,
                  0x97 => 1000, 0x93 => 333, 0x94 => 333, 0x91 => 222, 0x92 => 222, 0x85 => 1000] as $codigo => $ancho) {
            $tabla[$codigo] = $ancho;
        }
        return $tabla;
    }

    private function anchoBytes(string $bytes, float $tam, bool $negrita): float
    {
        $tabla = self::tablaAnchos($negrita);
        $suma = 0;
        $n = strlen($bytes);
        for ($i = 0; $i < $n; $i++) {
            $suma += $tabla[ord($bytes[$i])] ?? 556;
        }
        return $suma * $tam / 1000.0;
    }

    private function escaparBytes(string $bytes): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $bytes);
    }

    /**
     * Convierte un texto con **negritas** en palabras listas para componer.
     * Cada palabra: ['segs' => [[bytes WinAnsi, negrita], ...], 'esp' => lleva espacio antes].
     * Los fragmentos pegados (sin espacio) como "**Guardar**." forman una sola palabra.
     */
    private function palabrasDeTexto(string $texto): array
    {
        $palabras = [];
        $espacioPendiente = false;
        foreach (explode('**', $texto) as $indice => $parte) {
            if ($parte === '') {
                continue;
            }
            $negrita = ($indice % 2) === 1;
            $bytes = $this->aWinAnsi($parte);
            $largo = strlen($bytes);
            $i = 0;
            while ($i < $largo) {
                if ($bytes[$i] === ' ') {
                    $espacioPendiente = true;
                    $i++;
                    continue;
                }
                $j = $i;
                while ($j < $largo && $bytes[$j] !== ' ') {
                    $j++;
                }
                $trozo = substr($bytes, $i, $j - $i);
                if ($espacioPendiente || empty($palabras)) {
                    $palabras[] = ['segs' => [[$trozo, $negrita]], 'esp' => ($espacioPendiente && !empty($palabras))];
                } else {
                    $ultimo = count($palabras) - 1;
                    $palabras[$ultimo]['segs'][] = [$trozo, $negrita];
                }
                $espacioPendiente = false;
                $i = $j;
            }
        }
        return $palabras;
    }

    /** Parte en trozos que caben en $anchoMax una palabra más ancha que la línea. @return array<int, array> */
    private function partirPalabraLarga(array $palabra, float $anchoMax, float $tam): array
    {
        $piezas = [];
        $segs = [];
        $anchoPieza = 0.0;
        foreach ($palabra['segs'] as [$bytes, $neg]) {
            $trozo = '';
            $n = strlen($bytes);
            for ($i = 0; $i < $n; $i++) {
                $c = $bytes[$i];
                $w = $this->anchoBytes($c, $tam, (bool) $neg);
                if ($anchoPieza + $w > $anchoMax && ($trozo !== '' || !empty($segs))) {
                    if ($trozo !== '') {
                        $segs[] = [$trozo, $neg];
                        $trozo = '';
                    }
                    $piezas[] = $segs;
                    $segs = [];
                    $anchoPieza = 0.0;
                }
                $trozo .= $c;
                $anchoPieza += $w;
            }
            if ($trozo !== '') {
                $segs[] = [$trozo, $neg];
            }
        }
        if (!empty($segs)) {
            $piezas[] = $segs;
        }
        $res = [];
        foreach ($piezas as $k => $ss) {
            $res[] = ['segs' => $ss, 'esp' => $k === 0 ? $palabra['esp'] : false, 'salto' => $k > 0];
        }
        return $res ?: [$palabra];
    }

    /** Reparte las palabras en líneas que caben en $anchoMax. */
    private function componerLineas(array $palabras, float $anchoMax, float $tam): array
    {
        $anchoEspacio = $this->anchoBytes(' ', $tam, false);
        $lineas = [];
        $actual = [];
        $anchoActual = 0.0;
        $expandidas = [];
        foreach ($palabras as $palabra) {
            $w = 0.0;
            foreach ($palabra['segs'] as $seg) {
                $w += $this->anchoBytes($seg[0], $tam, $seg[1]);
            }
            if ($w > $anchoMax) {
                foreach ($this->partirPalabraLarga($palabra, $anchoMax, $tam) as $pieza) {
                    $expandidas[] = $pieza;
                }
            } else {
                $expandidas[] = $palabra;
            }
        }
        foreach ($expandidas as $palabra) {
            $ancho = 0.0;
            foreach ($palabra['segs'] as $seg) {
                $ancho += $this->anchoBytes($seg[0], $tam, $seg[1]);
            }
            $sep = (!empty($actual) && $palabra['esp']) ? $anchoEspacio : 0.0;
            $forzar = !empty($palabra['salto']);
            if (!empty($actual) && ($forzar || $anchoActual + $sep + $ancho > $anchoMax)) {
                $lineas[] = $actual;
                $actual = [];
                $anchoActual = 0.0;
                $sep = 0.0;
            }
            $palabra['sep'] = $sep;
            $actual[] = $palabra;
            $anchoActual += $sep + $ancho;
        }
        if (!empty($actual)) {
            $lineas[] = $actual;
        }
        return $lineas;
    }

    private function dibujarLinea(array $linea, float $x, float $y, float $tam, string $color): void
    {
        $cursor = $x;
        foreach ($linea as $palabra) {
            $cursor += $palabra['sep'];
            foreach ($palabra['segs'] as $seg) {
                $this->flujoPagina[] = sprintf(
                    'BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET',
                    $seg[1] ? 'F2' : 'F1', $tam, $color, $cursor, $y, $this->escaparBytes($seg[0])
                );
                $cursor += $this->anchoBytes($seg[0], $tam, $seg[1]);
            }
        }
    }

    private function bloqueTexto(string $texto, float $x, float $ancho, float $tam, float $altoLinea, string $color): void
    {
        foreach ($this->componerLineas($this->palabrasDeTexto($texto), $ancho, $tam) as $linea) {
            $this->saltoDePaginaSiHaceFalta($altoLinea);
            $this->y -= $altoLinea;
            $this->dibujarLinea($linea, $x, $this->y, $tam, $color);
        }
    }

    /** Texto en negrita completa (títulos), con ajuste de línea. */
    private function bloqueTitulo(string $texto, float $tam, float $altoLinea, string $color): void
    {
        $limpio = str_replace('**', '', $texto);
        $this->bloqueTexto('**' . $limpio . '**', self::MARGEN, $this->anchoPt - 2 * self::MARGEN, $tam, $altoLinea, $color);
    }

    private function agregarPie(int $numero): void
    {
        $x0 = self::MARGEN;
        $x1 = $this->anchoPt - self::MARGEN;
        $this->flujoPagina[] = sprintf('0.85 0.85 0.85 RG %.2F 40.00 m %.2F 40.00 l S', $x0, $x1);
        $izquierda = $this->aWinAnsi((string) $this->textoPie);
        $this->flujoPagina[] = sprintf('BT /F1 8 Tf %s rg %.2F 27.00 Td (%s) Tj ET', self::COLOR_SUAVE, $x0, $this->escaparBytes($izquierda));
        $derecha = $this->aWinAnsi('Página ' . $numero);
        $ancho = $this->anchoBytes($derecha, 8.0, false);
        $this->flujoPagina[] = sprintf('BT /F1 8 Tf %s rg %.2F 27.00 Td (%s) Tj ET', self::COLOR_SUAVE, $x1 - $ancho, $this->escaparBytes($derecha));
    }

    public function configurarPie(string $texto): void
    {
        $this->textoPie = $texto;
    }

    public function pieVisible(bool $visible): void
    {
        $this->pieVisible = $visible;
    }

    /** Número (desde 1) de la página en la que se está escribiendo. */
    public function paginaActual(): int
    {
        return count($this->paginasPend) + 1;
    }

    /** Salto de página; no hace nada si la página actual está vacía. */
    public function saltoDePagina(): void
    {
        $yInicio = $this->altoPt - (empty($this->paginasPend) ? $this->margenSupPrimera : $this->margenSupResto);
        if (empty($this->flujoPagina) && $this->y >= $yInicio - 0.01) {
            return;
        }
        $this->nuevaPagina();
    }

    public function portada(string $titulo, string $subtitulo, array $lineasPie): void
    {
        $this->flujoPagina[] = sprintf('0.91 0.72 0.14 rg 0 %.2F %.2F 300.00 re f', $this->altoPt - 300.0, $this->anchoPt);
        $this->flujoPagina[] = sprintf('BT /F2 54 Tf %s rg %.2F %.2F Td (SERENA) Tj ET', self::COLOR_TEXTO, self::MARGEN, $this->altoPt - 170.0);
        $this->flujoPagina[] = sprintf(
            'BT /F1 13 Tf %s rg %.2F %.2F Td (%s) Tj ET',
            self::COLOR_TEXTO, self::MARGEN, $this->altoPt - 200.0,
            $this->escaparTexto('Plataforma de Bienestar Emocional Institucional')
        );
        $this->y = $this->altoPt - 350.0;
        $this->bloqueTitulo($titulo, 28.0, 34.0, self::COLOR_TEXTO);
        $this->y -= 6;
        $this->bloqueTexto($subtitulo, self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 15.0, 20.0, self::COLOR_DORADO);
        $yPie = 130.0;
        foreach ($lineasPie as $linea) {
            $this->flujoPagina[] = sprintf(
                'BT /F1 10 Tf %s rg %.2F %.2F Td (%s) Tj ET',
                self::COLOR_SUAVE, self::MARGEN, $yPie, $this->escaparTexto((string) $linea)
            );
            $yPie -= 15.0;
        }
        $this->y = self::MARGEN;
    }

    private function reglaDorada(float $ancho = 60.0): void
    {
        $this->y -= 6;
        $this->flujoPagina[] = sprintf('0.91 0.72 0.14 RG 2.5 w %.2F %.2F m %.2F %.2F l S 1 w', self::MARGEN, $this->y, self::MARGEN + $ancho, $this->y);
        $this->y -= 8;
    }

    /** Portadilla de una parte del libro: siempre empieza en página nueva. */
    public function parte(string $etiqueta, string $titulo, string $intro = ''): void
    {
        $this->saltoDePagina();
        $this->y -= 10;
        $this->y -= 14;
        $this->escribirLinea('F2', 10, self::COLOR_DORADO, mb_strtoupper($etiqueta));
        $this->bloqueTitulo($titulo, 24.0, 30.0, self::COLOR_TEXTO);
        $this->reglaDorada(80.0);
        if ($intro !== '') {
            $this->bloqueTexto($intro, self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 11.0, 15.0, self::COLOR_SUAVE);
            $this->y -= 6;
        }
    }

    public function encabezadoCapitulo(string $etiqueta, string $titulo, string $resumen = ''): void
    {
        $this->saltoDePaginaSiHaceFalta(160);
        $this->y -= 26;
        $this->escribirLinea('F2', 9, self::COLOR_DORADO, mb_strtoupper($etiqueta));
        $this->bloqueTitulo($titulo, 17.0, 22.0, self::COLOR_TEXTO);
        $this->reglaDorada(50.0);
        if ($resumen !== '') {
            $this->bloqueTexto($resumen, self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 10.0, 14.0, self::COLOR_SUAVE);
            $this->y -= 4;
        }
    }

    public function encabezadoSeccion(string $titulo): void
    {
        $this->saltoDePaginaSiHaceFalta(70);
        $this->y -= 12;
        $this->bloqueTitulo($titulo, 12.5, 16.0, self::COLOR_TEXTO);
        $this->y -= 2;
    }

    public function parrafo(string $texto): void
    {
        $this->bloqueTexto($texto, self::MARGEN, $this->anchoPt - 2 * self::MARGEN, 10.5, 14.5, self::COLOR_TEXTO);
        $this->y -= 5;
    }

    /** Lista con viñetas o numerada; cada elemento admite **negritas**. */
    public function lista(array $elementos, bool $numerada = false): void
    {
        $sangria = $numerada ? 22.0 : 16.0;
        $anchoTexto = $this->anchoPt - 2 * self::MARGEN - $sangria;
        $numero = 0;
        foreach ($elementos as $elemento) {
            $numero++;
            $lineas = $this->componerLineas($this->palabrasDeTexto((string) $elemento), $anchoTexto, 10.5);
            $primera = true;
            foreach ($lineas as $linea) {
                $this->saltoDePaginaSiHaceFalta(14.5);
                $this->y -= 14.5;
                if ($primera) {
                    $marca = $numerada ? ($numero . '.') : "\x95";
                    $this->flujoPagina[] = sprintf(
                        'BT /F2 10.5 Tf %s rg %.2F %.2F Td (%s) Tj ET',
                        self::COLOR_DORADO, self::MARGEN + 2, $this->y, $marca
                    );
                    $primera = false;
                }
                $this->dibujarLinea($linea, self::MARGEN + $sangria, $this->y, 10.5, self::COLOR_TEXTO);
            }
            $this->y -= 3;
        }
        $this->y -= 3;
    }

    /** Recuadro destacado. $tipo: 'consejo' (dorado) o 'aviso' (rojo suave). */
    public function cuadro(string $etiqueta, string $texto, string $tipo = 'consejo'): void
    {
        $relleno = $tipo === 'aviso' ? '0.99 0.92 0.90' : '0.99 0.96 0.85';
        $barra = $tipo === 'aviso' ? '0.75 0.22 0.17' : '0.91 0.72 0.14';
        $colorEtiqueta = $tipo === 'aviso' ? '0.60 0.15 0.10' : '0.55 0.35 0.02';
        $margenInterno = 10.0;
        $anchoBarra = 4.0;
        $anchoCaja = $this->anchoPt - 2 * self::MARGEN;
        $anchoTexto = $anchoCaja - $anchoBarra - 2 * $margenInterno;
        $altoLinea = 13.5;
        $lineas = $this->componerLineas($this->palabrasDeTexto($texto), $anchoTexto, 10.0);
        $alto = 2 * $margenInterno + 12.0 + count($lineas) * $altoLinea;

        $this->saltoDePaginaSiHaceFalta($alto + 10);
        $this->y -= 4;
        $arriba = $this->y;
        $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $relleno, self::MARGEN, $arriba - $alto, $anchoCaja, $alto);
        $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $barra, self::MARGEN, $arriba - $alto, $anchoBarra, $alto);
        $xTexto = self::MARGEN + $anchoBarra + $margenInterno;
        $this->flujoPagina[] = sprintf(
            'BT /F2 9 Tf %s rg %.2F %.2F Td (%s) Tj ET',
            $colorEtiqueta, $xTexto, $arriba - $margenInterno - 9.0, $this->escaparTexto(mb_strtoupper($etiqueta))
        );
        foreach ($lineas as $k => $linea) {
            $yLinea = $arriba - $margenInterno - 12.0 - ($k + 1) * $altoLinea + 3.5;
            $this->dibujarLinea($linea, $xTexto, $yLinea, 10.0, self::COLOR_TEXTO);
        }
        $this->y = $arriba - $alto - 8;
    }

    /** Línea de índice: texto, puntos guía y número de página a la derecha. */
    public function lineaIndice(string $texto, int $pagina, int $nivel = 0): void
    {
        $this->saltoDePaginaSiHaceFalta(18);
        $this->y -= ($nivel > 0 ? 16 : 20);
        $tam = $nivel > 0 ? 10.0 : 11.0;
        $negrita = $nivel === 0;
        $x0 = self::MARGEN + ($nivel > 0 ? 16.0 : 0.0);
        $bytes = $this->aWinAnsi($texto);
        $this->flujoPagina[] = sprintf(
            'BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET',
            $negrita ? 'F2' : 'F1', $tam, self::COLOR_TEXTO, $x0, $this->y, $this->escaparBytes($bytes)
        );
        $numeroTexto = (string) $pagina;
        $anchoNumero = $this->anchoBytes($numeroTexto, $tam, false);
        $xNumero = $this->anchoPt - self::MARGEN - $anchoNumero;
        $inicioPuntos = $x0 + $this->anchoBytes($bytes, $tam, $negrita) + 6.0;
        $finPuntos = $xNumero - 6.0;
        if ($finPuntos > $inicioPuntos) {
            $this->flujoPagina[] = sprintf(
                '0.65 0.65 0.65 RG 0.6 w [1 3] 0 d %.2F %.2F m %.2F %.2F l S [] 0 d 1 w',
                $inicioPuntos, $this->y + 2, $finPuntos, $this->y + 2
            );
        }
        $this->flujoPagina[] = sprintf(
            'BT /F1 %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET',
            $tam, self::COLOR_TEXTO, $xNumero, $this->y, $this->escaparBytes($this->aWinAnsi($numeroTexto))
        );
    }

    /**
     * Inserta una captura (JPEG, o PNG si la extensión GD está disponible) centrada,
     * con pie opcional. Si el archivo no existe o no se puede leer, no hace nada.
     */
    public function imagen(string $ruta, string $pie = ''): bool
    {
        $datos = $this->cargarImagen($ruta);
        if ($datos === null) {
            return false;
        }
        [$idObjeto, $anchoPx, $altoPx] = $datos;
        $anchoMax = $this->anchoPt - 2 * self::MARGEN - 40.0;
        $altoMax = 300.0;
        $escala = min($anchoMax / $anchoPx, $altoMax / $altoPx, 1.0 * 0.75);
        $ancho = $anchoPx * $escala;
        $alto = $altoPx * $escala;

        $this->saltoDePaginaSiHaceFalta($alto + 34);
        $this->y -= 6;
        $x = self::MARGEN + ($this->anchoPt - 2 * self::MARGEN - $ancho) / 2;
        $yFondo = $this->y - $alto;
        $nombre = 'Im' . $idObjeto;
        $this->imagenesPagina[$nombre] = $idObjeto;
        $this->flujoPagina[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $ancho, $alto, $x, $yFondo, $nombre);
        $this->flujoPagina[] = sprintf('0.80 0.80 0.80 RG 0.6 w %.2F %.2F %.2F %.2F re S 1 w', $x, $yFondo, $ancho, $alto);
        $this->y = $yFondo - 4;
        if ($pie !== '') {
            $bytes = $this->aWinAnsi($pie);
            $anchoPie = $this->anchoBytes($bytes, 8.5, false);
            $this->y -= 10;
            $this->flujoPagina[] = sprintf(
                'BT /F1 8.5 Tf %s rg %.2F %.2F Td (%s) Tj ET',
                self::COLOR_SUAVE, self::MARGEN + ($this->anchoPt - 2 * self::MARGEN - $anchoPie) / 2, $this->y, $this->escaparBytes($bytes)
            );
        }
        $this->y -= 8;
        return true;
    }

    /** @return array{0:int,1:int,2:int}|null [id de objeto, ancho px, alto px] */
    private function cargarImagen(string $ruta): ?array
    {
        if (isset($this->imagenesCargadas[$ruta])) {
            return $this->imagenesCargadas[$ruta];
        }
        if (!is_file($ruta)) {
            return null;
        }
        $info = @getimagesize($ruta);
        if ($info === false) {
            return null;
        }
        $bytes = null;
        $canales = 3;
        if ($info[2] === IMAGETYPE_JPEG) {
            $bytes = @file_get_contents($ruta);
            $canales = (int) ($info['channels'] ?? 3);
        } elseif ($info[2] === IMAGETYPE_PNG && function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
            // Se convierte a JPEG sobre fondo blanco (sin transparencia): PDF 1.4 simple.
            $origen = @imagecreatefrompng($ruta);
            if ($origen !== false) {
                $ancho = imagesx($origen);
                $alto = imagesy($origen);
                $lienzo = imagecreatetruecolor($ancho, $alto);
                imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
                imagecopy($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto);
                ob_start();
                imagejpeg($lienzo, null, 88);
                $bytes = ob_get_clean();
                $canales = 3;
                imagedestroy($origen);
                imagedestroy($lienzo);
            }
        }
        if (!is_string($bytes) || $bytes === '' || ($canales !== 3 && $canales !== 1)) {
            return null;
        }
        $espacio = $canales === 1 ? '/DeviceGray' : '/DeviceRGB';
        $idObjeto = $this->nuevoObjeto(sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            $info[0], $info[1], $espacio, strlen($bytes), $bytes
        ));
        return $this->imagenesCargadas[$ruta] = [$idObjeto, (int) $info[0], (int) $info[1]];
    }

    // ==================================================================
    // Formato institucional: membrete, pie con "Página n de N" y QR de verificación,
    // imágenes desde bytes (logo), tablas que ajustan el texto, gráficos e indicadores.
    // ==================================================================

    /** Color #RRGGBB → "r g b" (0-1) para operadores PDF. */
    private static function hexAColor(string $hex, string $porDefecto = '0.68 0.42 0.02'): string
    {
        if (!preg_match('/^#?([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/', trim($hex), $m)) {
            return $porDefecto;
        }
        return sprintf('%.3F %.3F %.3F', hexdec($m[1]) / 255, hexdec($m[2]) / 255, hexdec($m[3]) / 255);
    }

    /** Acerca el color de acento al blanco: 0 = igual, 1 = blanco. */
    private function tinte(float $f): string
    {
        $c = array_map('floatval', explode(' ', $this->colorAcento));
        return sprintf('%.3F %.3F %.3F', $c[0] + (1 - $c[0]) * $f, $c[1] + (1 - $c[1]) * $f, $c[2] + (1 - $c[2]) * $f);
    }

    /** Recorta con «...» un texto (UTF-8) para que quepa en $ancho. Devuelve bytes WinAnsi listos para dibujar. */
    private function recortarBytes(string $texto, float $ancho, float $tam, bool $negrita = false): string
    {
        $bytes = $this->aWinAnsi($texto);
        if ($this->anchoBytes($bytes, $tam, $negrita) <= $ancho) {
            return $bytes;
        }
        while ($bytes !== '' && $this->anchoBytes($bytes . '...', $tam, $negrita) > $ancho) {
            $bytes = substr($bytes, 0, -1);
        }
        return rtrim($bytes) . '...';
    }

    private function textoBytes(float $x, float $y, string $bytes, float $tam, bool $negrita, string $color, string $alin = 'l', float $anchoCaja = 0.0): void
    {
        $w = $this->anchoBytes($bytes, $tam, $negrita);
        if ($alin === 'r') {
            $x += $anchoCaja - $w;
        } elseif ($alin === 'c') {
            $x += ($anchoCaja - $w) / 2;
        }
        $this->flujoPagina[] = sprintf('BT /%s %.1F Tf %s rg %.2F %.2F Td (%s) Tj ET', $negrita ? 'F2' : 'F1', $tam, $color, $x, $y, $this->escaparBytes($bytes));
    }

    /** @return array{0:int,1:int,2:int}|null [id de objeto, ancho px, alto px] */
    private function cargarImagenBytes(string $bytes): ?array
    {
        if ($bytes === '') {
            return null;
        }
        $clave = 'bytes:' . md5($bytes);
        if (isset($this->imagenesCargadas[$clave])) {
            return $this->imagenesCargadas[$clave];
        }
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            return null;
        }
        $datos = null;
        $canales = 3;
        if ($info[2] === IMAGETYPE_JPEG) {
            $datos = $bytes;
            $canales = (int) ($info['channels'] ?? 3);
        } elseif ($info[2] === IMAGETYPE_PNG && function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $origen = @imagecreatefromstring($bytes);
            if ($origen !== false) {
                $ancho = imagesx($origen);
                $alto = imagesy($origen);
                $lienzo = imagecreatetruecolor($ancho, $alto);
                imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
                imagecopy($lienzo, $origen, 0, 0, 0, 0, $ancho, $alto);
                ob_start();
                imagejpeg($lienzo, null, 88);
                $datos = ob_get_clean();
                imagedestroy($origen);
                imagedestroy($lienzo);
            }
        }
        if (!is_string($datos) || $datos === '' || ($canales !== 3 && $canales !== 1)) {
            return null;
        }
        $idObjeto = $this->nuevoObjeto(sprintf(
            "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace %s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
            $info[0], $info[1], $canales === 1 ? '/DeviceGray' : '/DeviceRGB', strlen($datos), $datos
        ));
        return $this->imagenesCargadas[$clave] = [$idObjeto, (int) $info[0], (int) $info[1]];
    }

    /** Coloca una imagen ya cargada en la página que se está armando ($yBase = borde inferior, contado desde abajo). */
    private function colocarImagen(array $datos, float $x, float $yBase, float $w, float $h): void
    {
        $nombre = 'Im' . $datos[0];
        $this->imagenesPagina[$nombre] = $datos[0];
        $this->flujoPagina[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w, $h, $x, $yBase, $nombre);
    }

    /** Dibuja una matriz de QR (1 = oscuro) con zona blanca alrededor. @param array<int, array<int,int>> $matriz */
    private function dibujarQr(array $matriz, float $x, float $y, float $tam): void
    {
        $n = count($matriz);
        if ($n === 0) {
            return;
        }
        $m = $tam / $n;
        $this->flujoPagina[] = sprintf('1 1 1 rg %.2F %.2F %.2F %.2F re f', $x - 2, $y - 2, $tam + 4, $tam + 4);
        $this->flujoPagina[] = '0 0 0 rg';
        for ($r = 0; $r < $n; $r++) {
            $c = 0;
            while ($c < $n) {
                if (!empty($matriz[$r][$c])) {
                    $ini = $c;
                    while ($c < $n && !empty($matriz[$r][$c])) {
                        $c++;
                    }
                    $this->flujoPagina[] = sprintf('%.3F %.3F %.3F %.3F re f', $x + $ini * $m, $y + $tam - ($r + 1) * $m, ($c - $ini) * $m, $m + 0.02);
                } else {
                    $c++;
                }
            }
        }
    }

    /**
     * Activa el formato institucional (todas las hojas llevan membrete y pie).
     * Claves: nombre, lineas[] (datos legales / ubicación), color (#RRGGBB), logo (bytes JPEG/PNG), tipo (etiqueta del documento),
     * fecha, usuario (quién lo genera), codigo (código de documento), url_corta (dirección de verificación), qr (matriz de QrCode::matriz o null).
     * @param array<string,mixed> $m
     */
    public function configurarMembrete(array $m): void
    {
        $m['logoDatos'] = !empty($m['logo']) ? $this->cargarImagenBytes((string) $m['logo']) : null;
        $this->membrete = $m;
        $this->colorAcento = self::hexAColor((string) ($m['color'] ?? ''));
        $this->margenSupPrimera = 104.0;
        $this->margenSupResto = 58.0;
        $this->margenInf = 76.0;
        if (empty($this->paginasPend) && empty($this->flujoPagina)) {
            $this->y = $this->altoPt - $this->margenSupPrimera;
        }
        $this->metaTitulo = trim((string) ($m['tipo'] ?? '') . ' - ' . (string) ($m['nombre'] ?? ''), ' -');
        $this->metaAutor = (string) ($m['nombre'] ?? 'Serena');
    }

    private function dibujarMembrete(int $i): void
    {
        $m = $this->membrete;
        $x0 = self::MARGEN;
        $x1 = $this->anchoPt - self::MARGEN;
        $acento = $this->colorAcento;
        $this->flujoPagina[] = sprintf('%s rg 0 %.2F %.2F 7 re f', $acento, $this->altoPt - 7, $this->anchoPt);
        $nombre = mb_strtoupper((string) ($m['nombre'] ?? 'SERENA'), 'UTF-8');
        $tipo = mb_strtoupper((string) ($m['tipo'] ?? ''), 'UTF-8');
        if ($i === 0) {
            $xTxt = $x0;
            $anchoDer = 190.0;
            if (!empty($m['logoDatos'])) {
                [$id, $wpx, $hpx] = $m['logoDatos'];
                $esc = min(54.0 / max(1, $wpx), 54.0 / max(1, $hpx));
                $w = $wpx * $esc;
                $h = $hpx * $esc;
                $this->colocarImagen($m['logoDatos'], $x0, $this->altoPt - 20 - $h, $w, $h);
                $xTxt = $x0 + 64;
            }
            $anchoNombre = $x1 - $xTxt - $anchoDer;
            $tam = 13.0;
            while ($tam > 8.5 && $this->anchoBytes($this->aWinAnsi($nombre), $tam, true) > $anchoNombre) {
                $tam -= 0.5;
            }
            $this->textoBytes($xTxt, $this->altoPt - 34, $this->recortarBytes($nombre, $anchoNombre, $tam, true), $tam, true, self::COLOR_TEXTO);
            $yL = $this->altoPt - 46;
            foreach (array_slice(array_values(array_filter((array) ($m['lineas'] ?? []), 'strlen')), 0, 3) as $linea) {
                $this->textoBytes($xTxt, $yL, $this->recortarBytes((string) $linea, $anchoNombre, 7.8), 7.8, false, self::COLOR_SUAVE);
                $yL -= 10.5;
            }
            if ($tipo !== '') {
                $tamTipo = 9.5;
                while ($tamTipo > 7.0 && $this->anchoBytes($this->aWinAnsi($tipo), $tamTipo, true) > $anchoDer) {
                    $tamTipo -= 0.5;
                }
                $this->textoBytes($x1 - $anchoDer, $this->altoPt - 32, $this->recortarBytes($tipo, $anchoDer, $tamTipo, true), $tamTipo, true, $acento, 'r', $anchoDer);
            }
            if (!empty($m['fecha'])) {
                $this->textoBytes($x1 - $anchoDer, $this->altoPt - 44, $this->recortarBytes((string) $m['fecha'], $anchoDer, 8), 8, false, self::COLOR_SUAVE, 'r', $anchoDer);
            }
            $this->flujoPagina[] = sprintf('%s RG 1.4 w %.2F %.2F m %.2F %.2F l S 1 w', $acento, $x0, $this->altoPt - 90, $x1, $this->altoPt - 90);
        } else {
            $this->textoBytes($x0, $this->altoPt - 28, $this->recortarBytes($nombre, 300, 9, true), 9, true, self::COLOR_TEXTO);
            if ($tipo !== '') {
                $this->textoBytes($x1 - 200, $this->altoPt - 28, $this->recortarBytes($tipo . ' (continuación)', 200, 8), 8, false, self::COLOR_SUAVE, 'r', 200);
            }
            $this->flujoPagina[] = sprintf('%s RG 0.9 w %.2F %.2F m %.2F %.2F l S 1 w', $acento, $x0, $this->altoPt - 36, $x1, $this->altoPt - 36);
        }
    }

    private function dibujarPieInstitucional(int $i, int $total): void
    {
        $m = $this->membrete;
        $x0 = self::MARGEN;
        $x1 = $this->anchoPt - self::MARGEN;
        $qr = !empty($m['qr']) ? $m['qr'] : null;
        $reserva = $qr !== null ? 56.0 : 0.0;
        $this->flujoPagina[] = sprintf('0.82 0.82 0.82 RG 0.6 w %.2F 62.00 m %.2F 62.00 l S 1 w', $x0, $x1);
        $ancho = $x1 - $x0 - $reserva - 90;
        $quien = trim('Generado por ' . (string) ($m['usuario'] ?? '') . (!empty($m['fecha_hora']) ? ' el ' . $m['fecha_hora'] : '') . (!empty($m['ref']) ? ' · Ref. ' . $m['ref'] : ''));
        $this->textoBytes($x0, 49, $this->recortarBytes((string) ($m['nombre'] ?? ''), $ancho, 8, true), 8, true, self::COLOR_TEXTO);
        $this->textoBytes($x0, 38, $this->recortarBytes($quien, $ancho, 7.5), 7.5, false, self::COLOR_SUAVE);
        if (!empty($m['aviso_qr'])) {
            $this->textoBytes($x0, 27, $this->recortarBytes((string) $m['aviso_qr'], $x1 - $x0 - 90, 7.5), 7.5, true, '0.70 0.15 0.10');
        } elseif (!empty($m['codigo'])) {
            $linea = 'Código del documento: ' . $m['codigo'] . (!empty($m['url_corta']) ? ' · Verificación: ' . $m['url_corta'] : '');
            $this->textoBytes($x0, 27, $this->recortarBytes($linea, $x1 - $x0 - $reserva - 90, 7.5), 7.5, false, self::COLOR_SUAVE);
        }
        $pagina = $this->aWinAnsi('Página ' . ($i + 1) . ' de ' . $total);
        $this->textoBytes($x1 - $reserva - 90, 49, $pagina, 8, false, self::COLOR_SUAVE, 'r', 90);
        if ($qr !== null) {
            $this->dibujarQr($qr, $x1 - 46, 12, 46);
        }
    }

    /** Ancho (pt) de una línea ya compuesta. */
    private function anchoLinea(array $linea, float $tam): float
    {
        $w = 0.0;
        foreach ($linea as $palabra) {
            $w += $palabra['sep'];
            foreach ($palabra['segs'] as $seg) {
                $w += $this->anchoBytes($seg[0], $tam, $seg[1]);
            }
        }
        return $w;
    }

    /**
     * Tabla con ajuste de texto por celda. $pesos = anchos relativos (por defecto según el contenido);
     * $alinea = 'l' | 'c' | 'r' por columna (por defecto: números al centro).
     * @param array<int, string|int|float|null> $encabezados
     * @param array<int, array<int, string|int|float|null>> $filas
     */
    public function tablaAjustada(array $encabezados, array $filas, array $pesos = [], array $alinea = [], ?array $filaTotal = null): void
    {
        $encabezados = array_values($encabezados);
        $n = max(1, count($encabezados));
        $anchoUtil = $this->anchoPt - 2 * self::MARGEN;
        // Muchas columnas → letra más pequeña (para que ninguna celda se desborde)
        $tam = $n >= 13 ? 6.0 : ($n >= 10 ? 6.8 : ($n >= 8 ? 7.5 : 8.5));
        $pad = $n >= 10 ? 3.0 : 5.0;
        $altoLinea = $tam + 2.0;

        if (count($pesos) !== $n) {
            $max = array_fill(0, $n, 4);
            foreach (array_merge([$encabezados], array_slice($filas, 0, 80)) as $fila) {
                if (isset($fila['area'])) {
                    continue;
                }
                foreach (array_values($fila) as $i => $celda) {
                    if ($i < $n) {
                        $max[$i] = max($max[$i], min(40, mb_strlen(trim((string) preg_replace('/\s+/', ' ', (string) $celda)))));
                    }
                }
            }
            $pesos = array_map(static fn (int $m): float => $m + 3.0, $max);
        }
        $suma = max(1.0, (float) array_sum($pesos));
        $anchos = array_map(static fn ($p): float => $anchoUtil * (float) $p / $suma, array_values($pesos));

        // Ancho mínimo por columna: que quepa su palabra más larga (así «4.0» o «Alto» nunca se parten en dos líneas).
        $minimos = array_fill(0, $n, 0.0);
        foreach (array_merge([$encabezados], array_slice($filas, 0, 80)) as $r => $fila) {
            if (isset($fila['area'])) {
                continue;
            }
            foreach (array_values((array) $fila) as $i => $celda) {
                if ($i >= $n) {
                    continue;
                }
                foreach (explode(' ', trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', (string) $celda)))) as $palabra) {
                    if ($palabra !== '') {
                        $minimos[$i] = max($minimos[$i], $this->anchoBytes($this->aWinAnsi($palabra), $tam, $r === 0) + 2 * $pad + 1.0);
                    }
                }
            }
        }
        foreach ($minimos as $i => $m) {
            $minimos[$i] = min($m, $anchoUtil * 0.34);
        }
        for ($vuelta = 0; $vuelta < 3; $vuelta++) {
            $falta = 0.0;
            $holgura = 0.0;
            foreach ($anchos as $i => $w) {
                if ($w < $minimos[$i]) {
                    $falta += $minimos[$i] - $w;
                    $anchos[$i] = $minimos[$i];
                } else {
                    $holgura += $w - $minimos[$i];
                }
            }
            if ($falta <= 0.01 || $holgura <= 0.01) {
                break;
            }
            foreach ($anchos as $i => $w) {
                if ($w > $minimos[$i]) {
                    $anchos[$i] = $w - $falta * (($w - $minimos[$i]) / $holgura);
                }
            }
        }

        if (count($alinea) !== $n) {
            $alinea = [];
            for ($i = 0; $i < $n; $i++) {
                $numerica = true;
                $vistos = 0;
                foreach (array_slice($filas, 0, 8) as $fila) {
                    if (isset($fila['area'])) {
                        continue;
                    }
                    $v = trim((string) (array_values($fila)[$i] ?? ''));
                    if ($v === '') {
                        continue;
                    }
                    $vistos++;
                    if (!preg_match('/^[\d.,%\-+\/:\s]+$/', $v)) {
                        $numerica = false;
                        break;
                    }
                }
                $alinea[$i] = ($numerica && $vistos > 0) ? 'c' : 'l';
            }
        }

        $dibujarFila = null;
        $dibujarFila = function (array $celdas, bool $cab, int $indice, bool $total = false) use (&$dibujarFila, $n, $anchos, $alinea, $tam, $pad, $altoLinea): void {
            // Banda de ÁREA: una fila con solo el nombre del área (['area' => 'Humanidades']); sus asignaturas van debajo.
            if (isset($celdas['area'])) {
                $texto = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', (string) $celdas['area'])));
                $lineasArea = $this->componerLineas($this->palabrasDeTexto('**' . mb_strtoupper($texto, 'UTF-8') . '**'), max(20.0, array_sum($anchos) - 2 * $pad - 4), $tam + 0.5);
                $altoArea = max(1, count($lineasArea)) * ($altoLinea + 0.5) + 6.0;
                // La banda nunca queda sola al final de la hoja: necesita sitio para ella y para su primera asignatura.
                if ($this->y - ($altoArea + $altoLinea * 2 + 8) < $this->margenInf) {
                    $this->nuevaPagina();
                    $dibujarFila($this->encabezadosActuales, true, 0);
                }
                $yTop = $this->y;
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->membrete !== null ? $this->tinte(0.88) : '0.90 0.90 0.90', self::MARGEN, $yTop - $altoArea, array_sum($anchos), $altoArea);
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F 3 %.2F re f', $this->membrete !== null ? $this->colorAcento : '0.45 0.45 0.45', self::MARGEN, $yTop - $altoArea, $altoArea);
                foreach ($lineasArea as $k => $linea) {
                    $this->dibujarLinea($linea, self::MARGEN + $pad + 3, $yTop - ($tam + 3.2) - $k * ($altoLinea + 0.5), $tam + 0.5, self::COLOR_TEXTO);
                }
                $this->y -= $altoArea;
                return;
            }
            $celdas = array_values($celdas);
            $lineasCelda = [];
            $maxLineas = 1;
            for ($i = 0; $i < $n; $i++) {
                $lineasCelda[$i] = [];
                // Un salto de línea («\n») dentro de la celda empieza una línea nueva (p. ej. asignatura y debajo el docente).
                foreach (explode("\n", (string) ($celdas[$i] ?? '')) as $parte) {
                    $texto = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', $parte)));
                    if ($texto === '') {
                        continue;
                    }
                    if ($cab || $total) {
                        $texto = '**' . $texto . '**';
                    }
                    foreach ($this->componerLineas($this->palabrasDeTexto($texto), max(8.0, $anchos[$i] - 2 * $pad), $tam) as $l) {
                        $lineasCelda[$i][] = $l;
                    }
                }
                $maxLineas = max($maxLineas, count($lineasCelda[$i]));
            }
            $alto = $maxLineas * $altoLinea + 6.0;
            if ($this->y - $alto < $this->margenInf) {
                $this->nuevaPagina();
                if (!$cab) {
                    $dibujarFila($this->encabezadosActuales, true, 0);
                }
            }
            $yTop = $this->y;
            if ($cab) {
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->membrete !== null ? $this->tinte(0.82) : '0.93 0.93 0.93', self::MARGEN, $yTop - $alto, array_sum($anchos), $alto);
            } elseif ($total) {
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->membrete !== null ? $this->tinte(0.9) : '0.95 0.95 0.95', self::MARGEN, $yTop - $alto, array_sum($anchos), $alto);
            } elseif ($indice % 2 === 1) {
                $this->flujoPagina[] = sprintf('0.965 0.965 0.965 rg %.2F %.2F %.2F %.2F re f', self::MARGEN, $yTop - $alto, array_sum($anchos), $alto);
            }
            $this->flujoPagina[] = sprintf('0.86 0.86 0.86 RG 0.5 w %.2F %.2F m %.2F %.2F l S 1 w', self::MARGEN, $yTop - $alto, self::MARGEN + array_sum($anchos), $yTop - $alto);
            $xCol = self::MARGEN;
            for ($i = 0; $i < $n; $i++) {
                foreach ($lineasCelda[$i] as $k => $linea) {
                    $disp = $anchos[$i] - 2 * $pad;
                    $w = $this->anchoLinea($linea, $tam);
                    $xTxt = $xCol + $pad;
                    if (($alinea[$i] ?? 'l') === 'c') {
                        $xTxt += max(0.0, ($disp - $w) / 2);
                    } elseif (($alinea[$i] ?? 'l') === 'r') {
                        $xTxt += max(0.0, $disp - $w);
                    }
                    $this->dibujarLinea($linea, $xTxt, $yTop - ($tam + 2.7) - $k * $altoLinea, $tam, self::COLOR_TEXTO);
                }
                $xCol += $anchos[$i];
            }
            $this->y -= $alto;
        };

        $this->encabezadosActuales = $encabezados;
        $this->saltoDePaginaSiHaceFalta(60);
        $dibujarFila($encabezados, true, 0);
        $k = 0;
        foreach (array_values($filas) as $fila) {
            $esArea = isset($fila['area']);
            $dibujarFila((array) $fila, false, $esArea ? 0 : $k);
            $k = $esArea ? 0 : $k + 1;
        }
        if ($filaTotal !== null) {
            $dibujarFila($filaTotal, false, 0, true);
        }
        $this->y -= 6;
    }

    /** @var array<int, mixed> encabezados de la tabla en curso (para repetirlos al pasar de hoja) */
    private array $encabezadosActuales = [];

    /** Indicadores en cajas (hasta 4 por fila). @param array<int, array{0:string,1:string}> $items [[etiqueta, valor], ...] */
    public function indicadores(array $items): void
    {
        $items = array_values($items);
        $util = $this->anchoPt - 2 * self::MARGEN;
        foreach (array_chunk($items, 4) as $fila) {
            $k = count($fila);
            $sep = 8.0;
            $w = ($util - $sep * ($k - 1)) / $k;
            $this->saltoDePaginaSiHaceFalta(52);
            $this->y -= 4;
            $yTop = $this->y;
            foreach ($fila as $j => [$etiqueta, $valor]) {
                $x = self::MARGEN + $j * ($w + $sep);
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F 44 re f', $this->tinte(0.9), $x, $yTop - 44, $w);
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F 3 44 re f', $this->colorAcento, $x, $yTop - 44);
                $this->textoBytes($x + 10, $yTop - 20, $this->recortarBytes((string) $valor, $w - 14, 15, true), 15, true, $this->colorAcento);
                $this->textoBytes($x + 10, $yTop - 35, $this->recortarBytes((string) $etiqueta, $w - 14, 8), 8, false, self::COLOR_SUAVE);
            }
            $this->y = $yTop - 48;
        }
        $this->y -= 4;
    }

    /** Gráfico de barras horizontales (una fila por dato; sirve con etiquetas largas). @param array<int,string> $etiquetas @param array<int,int|float> $valores */
    public function graficoBarras(string $titulo, array $etiquetas, array $valores, ?float $max = null, string $sufijo = ''): void
    {
        $valores = array_values($valores);
        $etiquetas = array_values($etiquetas);
        $n = count($valores);
        if ($n === 0) {
            return;
        }
        $fila = 17.0;
        $this->saltoDePaginaSiHaceFalta(min(40 + $n * $fila, 220));
        if ($titulo !== '') {
            $this->y -= 14;
            $this->textoBytes(self::MARGEN, $this->y, $this->recortarBytes($titulo, $this->anchoPt - 2 * self::MARGEN, 10, true), 10, true, self::COLOR_TEXTO);
            $this->y -= 4;
        }
        $maxEtq = 0.0;
        foreach ($etiquetas as $e) {
            $maxEtq = max($maxEtq, $this->anchoBytes($this->aWinAnsi((string) $e), 8.5, false));
        }
        $anchoEtq = min(160.0, $maxEtq + 10.0);
        $xBarra = self::MARGEN + $anchoEtq;
        $anchoMax = $this->anchoPt - 2 * self::MARGEN - $anchoEtq - 52;
        $tope = $max ?? max(1.0, (float) max($valores));
        foreach ($valores as $i => $v) {
            $this->saltoDePaginaSiHaceFalta($fila);
            $this->y -= $fila;
            $this->textoBytes(self::MARGEN, $this->y + 4, $this->recortarBytes((string) ($etiquetas[$i] ?? ''), $anchoEtq - 6, 8.5), 8.5, false, self::COLOR_TEXTO);
            $this->flujoPagina[] = sprintf('0.94 0.94 0.94 rg %.2F %.2F %.2F 10 re f', $xBarra, $this->y + 1, $anchoMax);
            $wBarra = max(0.0, min(1.0, (float) $v / $tope)) * $anchoMax;
            if ($wBarra > 0) {
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F 10 re f', $this->colorAcento, $xBarra, $this->y + 1, $wBarra);
            }
            $txt = (fmod((float) $v, 1.0) === 0.0 ? (string) (int) $v : number_format((float) $v, 1, ',', '.')) . $sufijo;
            $this->textoBytes($xBarra + $anchoMax + 6, $this->y + 3, $this->aWinAnsi($txt), 8.5, true, self::COLOR_TEXTO);
        }
        $this->y -= 8;
    }

    /**
     * Gráfico de líneas (serie en el tiempo). Los valores null cortan la línea.
     * @param array<int,string> $etiquetas @param array<int,int|float|null> $valores
     */
    public function graficoLinea(string $titulo, array $etiquetas, array $valores, ?float $min = null, ?float $max = null, float $alto = 120.0): void
    {
        $valores = array_values($valores);
        $etiquetas = array_values($etiquetas);
        $n = count($valores);
        if ($n === 0) {
            return;
        }
        $this->saltoDePaginaSiHaceFalta($alto + 56);
        if ($titulo !== '') {
            $this->y -= 14;
            $this->textoBytes(self::MARGEN, $this->y, $this->recortarBytes($titulo, $this->anchoPt - 2 * self::MARGEN, 10, true), 10, true, self::COLOR_TEXTO);
            $this->y -= 8;
        }
        $reales = array_filter($valores, static fn ($v) => $v !== null);
        $lo = $min ?? (empty($reales) ? 0.0 : min(0.0, (float) min($reales)));
        $hi = $max ?? (empty($reales) ? 1.0 : max(1.0, (float) max($reales)));
        if ($hi <= $lo) {
            $hi = $lo + 1;
        }
        $izq = 32.0;
        $xPlot = self::MARGEN + $izq;
        $wPlot = $this->anchoPt - 2 * self::MARGEN - $izq - 10;
        $yArriba = $this->y;
        $yAbajo = $yArriba - $alto;
        for ($g = 0; $g <= 4; $g++) {
            $yy = $yAbajo + $alto * $g / 4;
            $this->flujoPagina[] = sprintf('0.90 0.90 0.90 RG 0.5 w %.2F %.2F m %.2F %.2F l S 1 w', $xPlot, $yy, $xPlot + $wPlot, $yy);
            $val = $lo + ($hi - $lo) * $g / 4;
            $this->textoBytes(self::MARGEN, $yy - 2.5, $this->aWinAnsi(fmod($val, 1.0) === 0.0 ? (string) (int) $val : number_format($val, 1, ',', '.')), 7, false, self::COLOR_SUAVE, 'r', $izq - 6);
        }
        $puntos = [];
        foreach ($valores as $i => $v) {
            $px = $xPlot + ($n > 1 ? $wPlot * $i / ($n - 1) : $wPlot / 2);
            $puntos[$i] = $v === null ? null : [$px, $yAbajo + ($alto * (max($lo, min($hi, (float) $v)) - $lo) / ($hi - $lo))];
        }
        $trazo = '';
        $abierto = false;
        foreach ($puntos as $p) {
            if ($p === null) {
                $abierto = false;
                continue;
            }
            $trazo .= sprintf('%.2F %.2F %s ', $p[0], $p[1], $abierto ? 'l' : 'm');
            $abierto = true;
        }
        if ($trazo !== '') {
            $this->flujoPagina[] = sprintf('%s RG 1.6 w %sS 1 w', $this->colorAcento, $trazo);
        }
        foreach ($puntos as $p) {
            if ($p !== null) {
                $this->flujoPagina[] = sprintf('%s rg %.2F %.2F 3.4 3.4 re f', $this->colorAcento, $p[0] - 1.7, $p[1] - 1.7);
            }
        }
        $paso = max(1, (int) ceil($n / 8));
        foreach ($etiquetas as $i => $e) {
            if ($i % $paso === 0 || $i === $n - 1) {
                $bytes = $this->recortarBytes((string) $e, 54, 7);
                $px = $xPlot + ($n > 1 ? $wPlot * $i / ($n - 1) : $wPlot / 2);
                $this->textoBytes($px - 27, $yAbajo - 11, $bytes, 7, false, self::COLOR_SUAVE, 'c', 54);
            }
        }
        $this->y = $yAbajo - 20;
    }

    /** Parejas «etiqueta: valor» en cuadrícula (2 por fila): datos del estudiante, de la institución, filtros del informe... @param array<int, array{0:string,1:string}> $items */
    public function pares(array $items, int $columnas = 2): void
    {
        $columnas = max(1, min(3, $columnas));
        $util = $this->anchoPt - 2 * self::MARGEN;
        $colAncho = $util / $columnas;
        $tam = 8.8;
        $lh = 11.5;
        foreach (array_chunk(array_values($items), $columnas) as $fila) {
            $celdas = [];
            $max = 1;
            foreach ($fila as $j => $par) {
                $et = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', (string) ($par[0] ?? ''))));
                $va = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', (string) ($par[1] ?? ''))));
                $anchoEt = $colAncho * 0.38;
                $le = $et === '' ? [] : $this->componerLineas($this->palabrasDeTexto('**' . $et . '**'), $anchoEt - 4, $tam);
                $lv = $va === '' ? [] : $this->componerLineas($this->palabrasDeTexto($va), $colAncho - $anchoEt - 6, $tam);
                $celdas[$j] = [$le, $lv, $anchoEt];
                $max = max($max, count($le), count($lv));
            }
            $alto = $max * $lh + 5.0;
            $this->saltoDePaginaSiHaceFalta($alto);
            $yTop = $this->y;
            $this->flujoPagina[] = sprintf('0.88 0.88 0.88 RG 0.4 w %.2F %.2F m %.2F %.2F l S 1 w', self::MARGEN, $yTop - $alto, self::MARGEN + $util, $yTop - $alto);
            foreach ($celdas as $j => [$le, $lv, $anchoEt]) {
                $x = self::MARGEN + $j * $colAncho;
                foreach ($le as $k => $l) {
                    $this->dibujarLinea($l, $x + 3, $yTop - ($tam + 2.2) - $k * $lh, $tam, self::COLOR_TEXTO);
                }
                foreach ($lv as $k => $l) {
                    $this->dibujarLinea($l, $x + $anchoEt + 3, $yTop - ($tam + 2.2) - $k * $lh, $tam, self::COLOR_TEXTO);
                }
            }
            $this->y -= $alto;
        }
        $this->y -= 6;
    }

    /** Banda de sección (por ejemplo el nombre de un grupo o un área) con barra de color; nunca queda sola al pie de una hoja. */
    public function banda(string $texto): void
    {
        $texto = trim((string) preg_replace('/\s+/', ' ', str_replace('**', '', $texto)));
        $ancho = $this->anchoPt - 2 * self::MARGEN;
        $lineas = $this->componerLineas($this->palabrasDeTexto('**' . $texto . '**'), $ancho - 14, 9.5);
        $alto = max(1, count($lineas)) * 12.0 + 7.0;
        $this->saltoDePaginaSiHaceFalta($alto + 40);
        $this->y -= 4;
        $yTop = $this->y;
        $this->flujoPagina[] = sprintf('%s rg %.2F %.2F %.2F %.2F re f', $this->membrete !== null ? $this->tinte(0.86) : '0.92 0.92 0.92', self::MARGEN, $yTop - $alto, $ancho, $alto);
        $this->flujoPagina[] = sprintf('%s rg %.2F %.2F 3 %.2F re f', $this->membrete !== null ? $this->colorAcento : '0.45 0.45 0.45', self::MARGEN, $yTop - $alto, $alto);
        foreach ($lineas as $k => $l) {
            $this->dibujarLinea($l, self::MARGEN + 9, $yTop - 12.5 - $k * 12.0, 9.5, self::COLOR_TEXTO);
        }
        $this->y -= $alto + 4;
    }

    /** Líneas de firma: [[nombre, cargo], ...] repartidas en el ancho. Se mantienen juntas (si no caben, pasan a la hoja siguiente). */
    public function firmas(array $items): void
    {
        $items = array_values($items);
        $n = max(1, count($items));
        $this->saltoDePaginaSiHaceFalta(80);
        $this->y -= 46;
        $ancho = ($this->anchoPt - 2 * self::MARGEN) / $n;
        foreach ($items as $i => $par) {
            $x = self::MARGEN + $i * $ancho + 14;
            $w = $ancho - 28;
            $this->flujoPagina[] = sprintf('0.25 0.25 0.25 RG 0.7 w %.2F %.2F m %.2F %.2F l S 1 w', $x, $this->y, $x + $w, $this->y);
            $nombre = (string) ($par[0] ?? '');
            $cargo = (string) ($par[1] ?? '');
            $yy = $this->y - 11;
            if ($nombre !== '') {
                $this->textoBytes($x, $yy, $this->recortarBytes($nombre, $w, 8.6, true), 8.6, true, self::COLOR_TEXTO, 'c', $w);
                $yy -= 11;
            }
            $this->textoBytes($x, $yy, $this->recortarBytes($cargo, $w, 8), 8, false, self::COLOR_SUAVE, 'c', $w);
        }
        $this->y -= 34;
    }

    private function sanearNombreArchivo(string $nombre): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9\-_ ]/', '', $nombre) ?? '';
        $nombre = trim($nombre);
        return $nombre !== '' ? $nombre : 'reporte-serena';
    }

    /** Devuelve el PDF completo como cadena binaria (sin enviarlo). */
    public function contenido(): string
    {
        if ($this->pdfFinal !== null) {
            return $this->pdfFinal;
        }
        if (!empty($this->flujoPagina) || empty($this->paginasPend)) {
            $this->cerrarPaginaActual();
        }
        $total = count($this->paginasPend);
        foreach ($this->paginasPend as $i => $pg) {
            $this->flujoPagina = $pg['flujo'];
            $this->imagenesPagina = $pg['imgs'];
            if ($this->membrete !== null) {
                $this->dibujarMembrete($i);
                $this->dibujarPieInstitucional($i, $total);
            } elseif ($pg['pie']) {
                $this->agregarPie($i + 1);
            }
            $this->cerrarPaginaDefinitiva($this->flujoPagina, $this->imagenesPagina);
        }
        $this->flujoPagina = [];
        $this->imagenesPagina = [];

        $kids = implode(' ', array_map(static fn (int $id) => "{$id} 0 R", $this->paginas));
        $this->objetos[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $this->objetos[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($this->paginas) . ' >>';
        $info = '';
        if ($this->metaTitulo !== '' || $this->metaAutor !== '') {
            $idInfo = $this->nuevoObjeto(sprintf(
                '<< /Title (%s) /Author (%s) /Producer (Serena) >>',
                $this->escaparTexto($this->metaTitulo), $this->escaparTexto($this->metaAutor)
            ));
            $info = " /Info {$idInfo} 0 R";
        }

        $buffer = "%PDF-1.4\n";
        $offsets = [];
        $totalObj = count($this->objetos);
        for ($i = 1; $i <= $totalObj; $i++) {
            $offsets[$i] = strlen($buffer);
            $buffer .= "{$i} 0 obj\n{$this->objetos[$i]}\nendobj\n";
        }
        $inicioXref = strlen($buffer);
        $buffer .= 'xref' . "\n" . "0 " . ($totalObj + 1) . "\n";
        $buffer .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObj; $i++) {
            $buffer .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $buffer .= "trailer\n<< /Size " . ($totalObj + 1) . " /Root 1 0 R{$info} >>\nstartxref\n{$inicioXref}\n%%EOF";
        return $this->pdfFinal = $buffer;
    }

    /** Genera el PDF completo y lo envía al navegador como descarga. Termina la ejecución. */
    public function salidaDescarga(string $nombreArchivo): void
    {
        $buffer = $this->contenido();

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $this->sanearNombreArchivo($nombreArchivo) . '.pdf"');
            header('Content-Length: ' . strlen($buffer));
            header('Cache-Control: private, max-age=0, must-revalidate');
        }
        echo $buffer;
        exit;
    }
}
