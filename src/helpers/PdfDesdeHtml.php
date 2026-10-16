<?php
declare(strict_types=1);

require_once __DIR__ . '/DocumentoPdf.php';

/**
 * PDF institucional de CUALQUIER informe de pantalla, sin duplicar sus consultas ni sus permisos.
 *
 * Funciona así: en las páginas autorizadas, cuando la dirección lleva ?pdf=1 (o ?pdf=qr para la versión con código QR),
 * parciales/encabezado.php llama a iniciar(), que captura TODO lo que la página va a imprimir. Al terminar el script,
 * finalizar() toma el contenido principal (<main class="contenido-panel">), lo convierte (títulos, párrafos, listas,
 * indicadores y tablas) con el formato institucional de DocumentoPdf y lo envía como descarga.
 * La página hizo ya sus comprobaciones de rol y colegio: el PDF solo contiene lo que esa persona ya veía.
 *
 * Los gráficos interactivos (canvas/SVG) no se pueden convertir: el PDF lo avisa en una nota al final.
 */
final class PdfDesdeHtml
{
    /** Páginas (ruta dentro de public/, sin .php) que ofrecen PDF. Agregar una aquí basta para habilitarla. */
    private const PAGINAS = [
        'boletin', 'asistencia_reporte', 'horario', 'horarios_admin', 'casos', 'estudiantes',
        'docente/seguimiento', 'docente/grupo', 'docente/pulso',
        'coordinador/reportes', 'superadmin/reportes', 'superadmin/comparativo', 'superadmin/colegios',
        'secretaria_academica/boletines', 'secretaria_academica/certificados', 'secretaria_academica/matriculas', 'secretaria_academica/inscripciones',
        'administrador/auditoria', 'administrador/grupos', 'administrador/anios_lectivos', 'administrador/plan_academico', 'administrador/salud',
        'simat', 'simpade',
    ];

    private static int $nivel = 0;
    private static bool $activo = false;
    private static bool $hayGraficos = false;

    private static function clavePagina(): string
    {
        $ruta = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $ruta = (string) preg_replace('#\.php$#', '', $ruta);
        if (defined('BASE_URL') && BASE_URL !== '' && str_starts_with($ruta, BASE_URL)) {
            $ruta = substr($ruta, strlen(BASE_URL));
        }
        return ltrim($ruta, '/');
    }

    /** ¿Esta página ofrece PDF? (la usa el encabezado para mostrar los botones) */
    public static function disponible(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && in_array(self::clavePagina(), self::PAGINAS, true);
    }

    /** Llamar al principio de parciales/encabezado.php, antes de imprimir nada. */
    public static function iniciar(): void
    {
        if (self::$activo || !self::disponible() || !in_array((string) ($_GET['pdf'] ?? ''), ['1', 'qr'], true)) {
            return;
        }
        self::$activo = true;
        ob_start();
        self::$nivel = ob_get_level();
        register_shutdown_function([self::class, 'finalizar']);
    }

    public static function finalizar(): void
    {
        if (!self::$activo) {
            return;
        }
        self::$activo = false;
        $html = '';
        while (ob_get_level() >= self::$nivel && self::$nivel > 0) {
            $html = (string) ob_get_clean() . $html;
        }
        // Redirecciones o errores: se entrega tal cual, sin convertir.
        $redirige = false;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') === 0) {
                $redirige = true;
            }
        }
        if ($redirige || http_response_code() >= 300 || trim($html) === '') {
            echo $html;
            return;
        }
        try {
            $pdo = $GLOBALS['pdo'] ?? null;
            [$titulo, $pdf] = self::convertir($html, $pdo instanceof PDO ? $pdo : null, DocumentoPdf::pideQr());
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'No se pudo generar el PDF de esta página.';
            return;
        }
        $sinTildes = strtr($titulo, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $nombre = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $sinTildes), '-');
        $pdf->salidaDescarga(($nombre !== '' ? $nombre : 'Informe-Serena') . '-' . date('Y-m-d'));
    }

    /** @return array{0:string,1:GeneradorPDF} [título, pdf] */
    private static function convertir(string $html, ?PDO $pdo, bool $conQr): array
    {
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $xp = new DOMXPath($dom);
        $main = $xp->query("//main[contains(concat(' ', normalize-space(@class), ' '), ' contenido-panel ')]")->item(0)
            ?? $xp->query('//main')->item(0) ?? $xp->query('//body')->item(0);
        if (!$main instanceof DOMNode) {
            throw new RuntimeException('Sin contenido');
        }
        $h1 = $xp->query('.//h1', $main)->item(0);
        $titulo = trim((string) ($GLOBALS['tituloPagina'] ?? ''));
        if ($titulo === '') {
            $titulo = $h1 ? self::texto($h1) : 'Informe';
        }
        if ($titulo === '') {
            $titulo = 'Informe';
        }

        self::$hayGraficos = false;
        if ($pdo !== null) {
            $pdf = DocumentoPdf::crear($pdo, $titulo, $titulo, $conQr);
        } else {
            $pdf = new GeneradorPDF();
        }
        self::recorrer($main, $pdf);
        if (self::$hayGraficos) {
            $pdf->espaciador(6);
            $pdf->textoSuave('Nota: los gráficos interactivos de la pantalla no se incluyen en este PDF; sus datos aparecen en las tablas cuando la página los muestra.');
        }
        return [$titulo, $pdf];
    }

    private static function texto(DOMNode $n): string
    {
        $t = self::limpiar($n->textContent ?? '');
        return $t;
    }

    private static function limpiar(string $t): string
    {
        $t = (string) preg_replace('/[\x{1F000}-\x{1FFFF}\x{2190}-\x{21FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}]/u', '', $t);
        $t = str_replace(["\u{00A0}", '**'], [' ', ''], $t);
        return trim((string) preg_replace('/\s+/u', ' ', $t));
    }

    private static function clases(DOMElement $e): string
    {
        return ' ' . strtolower((string) $e->getAttribute('class')) . ' ';
    }

    private static function oculto(DOMElement $e): bool
    {
        $c = self::clases($e);
        if ($e->hasAttribute('hidden') || stripos((string) $e->getAttribute('style'), 'display:none') !== false || stripos((string) $e->getAttribute('style'), 'display: none') !== false) {
            return true;
        }
        foreach ([' no-print ', ' no-imprimir ', ' oculto ', ' sr-only ', ' solo-lectores ', ' acad-filtros ', ' form-filtro-fechas ', ' barra-filtros ', ' barra-pdf ', ' boton-pdf ', ' acad-acciones ', ' barra-acciones ', ' paginacion '] as $k) {
            if (str_contains($c, $k)) {
                return true;
            }
        }
        return false;
    }

    /** Valor visible de una celda: texto, o el valor de sus campos (planillas con notas). */
    private static function celda(DOMElement $td): string
    {
        $valores = [];
        foreach ($td->getElementsByTagName('input') as $in) {
            $tipo = strtolower((string) $in->getAttribute('type'));
            if (in_array($tipo, ['hidden', 'submit', 'button', 'checkbox', 'radio', 'file', 'password'], true)) {
                if (in_array($tipo, ['checkbox', 'radio'], true) && $in->hasAttribute('checked')) {
                    $valores[] = 'X';
                }
                continue;
            }
            $v = trim((string) $in->getAttribute('value'));
            if ($v !== '') {
                $valores[] = $v;
            }
        }
        foreach ($td->getElementsByTagName('select') as $sel) {
            foreach ($sel->getElementsByTagName('option') as $op) {
                if ($op->hasAttribute('selected')) {
                    $valores[] = trim($op->textContent);
                }
            }
        }
        $texto = '';
        foreach ($td->childNodes as $hijo) {
            if ($hijo instanceof DOMElement && (self::oculto($hijo) || in_array(strtolower($hijo->nodeName), ['button', 'script', 'style', 'select', 'input', 'svg', 'canvas'], true))) {
                continue;
            }
            if ($hijo instanceof DOMElement) {
                $nombreHijo = strtolower($hijo->nodeName);
                $corta = in_array($nombreHijo, ['small', 'div', 'p', 'li', 'br'], true);
                $texto .= ($corta ? self::SALTO : ' ') . ($nombreHijo === 'br' ? '' : self::textoSinControles($hijo)) . ($corta ? self::SALTO : '');
            } else {
                $texto .= ' ' . (string) $hijo->textContent;
            }
        }
        $texto = self::limpiar($texto);
        $texto = trim((string) preg_replace('/ *' . self::SALTO . '[ ' . self::SALTO . ']*/u', "\n", $texto));
        $extra = self::limpiar(implode(' ', $valores));
        return trim($texto . ($texto !== '' && $extra !== '' ? ' ' : '') . $extra);
    }

    /** Marca interna de «nueva línea» dentro de una celda (<br>, <small>, <div>, <p>); celda() la vuelve «\n». */
    private const SALTO = "\u{E000}";

    private static function textoSinControles(DOMElement $e): string
    {
        $t = '';
        foreach ($e->childNodes as $h) {
            if ($h instanceof DOMElement) {
                $nombre = strtolower($h->nodeName);
                if (self::oculto($h) || in_array($nombre, ['button', 'script', 'style', 'select', 'input', 'svg', 'canvas'], true)) {
                    continue;
                }
                if ($nombre === 'br') {
                    $t .= self::SALTO;
                    continue;
                }
                $corta = in_array($nombre, ['small', 'div', 'p', 'li'], true);
                $t .= ($corta ? self::SALTO : ' ') . self::textoSinControles($h) . ($corta ? self::SALTO : '');
            } else {
                $t .= $h->textContent;
            }
        }
        return $t;
    }

    private static function tabla(DOMElement $tabla, GeneradorPDF $pdf): void
    {
        $filas = [];
        $hayTh = false;
        foreach ($tabla->getElementsByTagName('tr') as $tr) {
            if (!$tr instanceof DOMElement || self::oculto($tr)) {
                continue;
            }
            // Solo las filas de ESTA tabla (no las de tablas anidadas)
            $padre = $tr->parentNode;
            while ($padre && !($padre instanceof DOMElement && strtolower($padre->nodeName) === 'table')) {
                $padre = $padre->parentNode;
            }
            if ($padre !== $tabla) {
                continue;
            }
            $celdas = [];
            $esCab = false;
            $tdsDeLaFila = 0;
            $spanPrimero = 1;
            foreach ($tr->childNodes as $td) {
                if (!$td instanceof DOMElement || !in_array(strtolower($td->nodeName), ['td', 'th'], true) || self::oculto($td)) {
                    continue;
                }
                if (strtolower($td->nodeName) === 'th') {
                    $esCab = true;
                }
                $tdsDeLaFila++;
                $celdas[] = self::celda($td);
                $span = max(1, (int) $td->getAttribute('colspan'));
                if ($tdsDeLaFila === 1) {
                    $spanPrimero = $span;
                }
                for ($k = 1; $k < min($span, 20); $k++) {
                    $celdas[] = '';
                }
            }
            if ($celdas) {
                // Fila de ÁREA: una sola celda que abarca varias columnas (o con clase «area»).
                $esArea = !$esCab && $tdsDeLaFila === 1 && ($spanPrimero >= 2 || str_contains(self::clases($tr), ' area '));
                $filas[] = ['celdas' => $celdas, 'cab' => $esCab, 'area' => $esArea ? str_replace("\n", ' ', $celdas[0]) : null];
                $hayTh = $hayTh || $esCab;
            }
        }
        if (!$filas) {
            return;
        }
        $columnas = max(array_map(static fn (array $f): int => $f['area'] !== null ? 1 : count($f['celdas']), $filas));
        $columnas = max($columnas, ...array_map(static fn (array $f): int => $f['area'] !== null ? 0 : count($f['celdas']), $filas));
        $pad = static fn (array $c): array => array_pad($c, $columnas, '');
        $encabezados = null;
        $cuerpo = [];
        foreach ($filas as $i => $f) {
            if ($f['area'] !== null) {
                if ($encabezados !== null) {
                    $cuerpo[] = ['area' => (string) $f['area']];
                }
                continue;
            }
            if ($encabezados === null && ($f['cab'] || (!$hayTh && $i === 0))) {
                $encabezados = $pad($f['celdas']);
                continue;
            }
            $cuerpo[] = $pad($f['celdas']);
        }
        if ($encabezados === null) {
            $encabezados = array_fill(0, $columnas, '');
        }
        $pdf->espaciador(4);
        $pdf->tablaAjustada($encabezados, $cuerpo);
    }

    /** Indicadores: tarjetas con un número grande y su etiqueta. */
    private static function indicadores(DOMElement $contenedor, GeneradorPDF $pdf): bool
    {
        $items = [];
        foreach ($contenedor->childNodes as $tarjeta) {
            if (!$tarjeta instanceof DOMElement || self::oculto($tarjeta)) {
                continue;
            }
            $segmentos = [];
            $hoja = static function (DOMNode $n) use (&$hoja, &$segmentos): void {
                foreach ($n->childNodes as $h) {
                    if ($h instanceof DOMElement) {
                        if (in_array(strtolower($h->nodeName), ['script', 'style', 'svg', 'canvas', 'button'], true)) {
                            continue;
                        }
                        $hoja($h);
                    } else {
                        $t = self::limpiar((string) $h->textContent);
                        if ($t !== '') {
                            $segmentos[] = $t;
                        }
                    }
                }
            };
            $hoja($tarjeta);
            if (count($segmentos) === 0) {
                continue;
            }
            $valor = null;
            foreach ($segmentos as $k => $sgm) {
                if (preg_match('/\d/', $sgm) && mb_strlen($sgm) <= 14) {
                    $valor = $k;
                    break;
                }
            }
            if ($valor === null) {
                $items[] = [implode(' ', array_slice($segmentos, 1)), $segmentos[0]];
            } else {
                $etq = $segmentos;
                unset($etq[$valor]);
                $items[] = [implode(' · ', $etq), $segmentos[$valor]];
            }
        }
        if (!$items) {
            return false;
        }
        $pdf->indicadores($items);
        return true;
    }

    private static function recorrer(DOMNode $nodo, GeneradorPDF $pdf): void
    {
        foreach ($nodo->childNodes as $n) {
            if ($n instanceof DOMText) {
                $t = self::limpiar($n->textContent);
                if ($t !== '') {
                    $pdf->texto($t);
                }
                continue;
            }
            if (!$n instanceof DOMElement || self::oculto($n)) {
                continue;
            }
            $tag = strtolower($n->nodeName);
            $c = self::clases($n);
            switch ($tag) {
                case 'script': case 'style': case 'nav': case 'button': case 'select': case 'input': case 'textarea':
                case 'noscript': case 'template': case 'dialog': case 'iframe': case 'audio': case 'video': case 'img': case 'hr': case 'br':
                    break;
                case 'canvas':
                case 'svg':
                    self::$hayGraficos = true;
                    break;
                case 'h1':
                    if (($t = self::texto($n)) !== '') { $pdf->titulo($t); }
                    break;
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'legend':
                case 'summary':
                    if (($t = self::texto($n)) !== '') { $pdf->subtitulo($t); }
                    break;
                case 'p':
                case 'small':
                case 'figcaption':
                case 'blockquote':
                    if (($t = self::texto($n)) !== '') {
                        if (str_contains($c, 'texto-suave') || $tag === 'small' || str_contains($c, 'pie-')) { $pdf->textoSuave($t); } else { $pdf->texto($t); }
                    }
                    break;
                case 'ul':
                case 'ol':
                    $items = [];
                    foreach ($n->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->nodeName) === 'li' && !self::oculto($li) && ($t = self::limpiar(self::textoSinControles($li))) !== '') {
                            $items[] = $t;
                        }
                    }
                    if ($items) { $pdf->lista($items, $tag === 'ol'); }
                    break;
                case 'dl':
                    $dt = '';
                    foreach ($n->childNodes as $h) {
                        if (!$h instanceof DOMElement) { continue; }
                        if (strtolower($h->nodeName) === 'dt') { $dt = self::texto($h); }
                        elseif (strtolower($h->nodeName) === 'dd') { $pdf->texto(($dt !== '' ? $dt . ': ' : '') . self::texto($h)); $dt = ''; }
                        elseif ($h->nodeName === 'div') {
                            $a = $h->getElementsByTagName('dt')->item(0); $b = $h->getElementsByTagName('dd')->item(0);
                            if ($a && $b) { $pdf->texto(self::texto($a) . ': ' . self::texto($b)); }
                        }
                    }
                    break;
                case 'table':
                    self::tabla($n, $pdf);
                    break;
                case 'form':
                    // Un formulario de solo filtros (sin tablas) no aporta al informe; uno que envuelve una tabla (planillas) sí.
                    if ($n->getElementsByTagName('table')->length > 0) { self::recorrer($n, $pdf); }
                    break;
                default:
                    if (str_contains($c, 'tarjetas-resumen') || str_contains($c, 'acad-resumen') || str_contains($c, 'kpi')) {
                        if (self::indicadores($n, $pdf)) { break; }
                    }
                    if (str_contains($c, 'grafico') || str_contains($c, 'chart')) {
                        self::$hayGraficos = true;
                    }
                    self::recorrer($n, $pdf);
            }
        }
    }
}
