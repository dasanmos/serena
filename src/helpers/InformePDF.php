<?php
declare(strict_types=1);

require_once __DIR__ . '/DocumentoPdf.php';

/**
 * Dibuja en PDF un informe del catálogo (src/informes) con el FORMATO INSTITUCIONAL único de Serena (DocumentoPdf):
 * membrete con logo/DANE/NIT/resolución, título, bloques, y pie con «Página n de N» (+ QR de verificación si se pide).
 *
 * Bloques que entiende (los mismos que producen los generadores Gen*.php):
 *   tabla (enc, filas, alin, anchos, titulo, pie) · pares · texto · nota · banda · firmas · salto
 * Las tablas ajustan el texto de cada celda, repiten el encabezado en cada hoja y no parten una fila entre dos hojas.
 */
final class InformePDF
{
    /**
     * @param array<string,mixed> $res   resultado del generador: titulo, subtitulo?, orient ('v'|'h'), bloques[]
     * @param array<string,mixed> $meta  fecha, usuario, ref (INF-n)
     */
    public static function generar(array $res, PDO $pdo, int $colegioId, array $meta, bool $conQr = false): string
    {
        $titulo = (string) $res['titulo'];
        $horizontal = ($res['orient'] ?? 'v') === 'h';
        $pdf = DocumentoPdf::crear($pdo, $titulo, $titulo . ($conQr ? ' (' . (string) ($meta['ref'] ?? '') . ')' : ''), $conQr, $colegioId, $horizontal, ['ref' => (string) ($meta['ref'] ?? '')]);

        $pdf->titulo($titulo);
        if ((string) ($res['subtitulo'] ?? '') !== '') {
            $pdf->textoSuave((string) $res['subtitulo']);
            $pdf->espaciador(4);
        }
        foreach ($res['bloques'] as $b) {
            switch ($b['t'] ?? '') {
                case 'tabla':
                    self::tabla($pdf, $b);
                    break;
                case 'pares':
                    if (!empty($b['titulo'])) {
                        $pdf->subtitulo((string) $b['titulo']);
                    }
                    $pdf->pares(array_map(static fn (array $i): array => [(string) $i[0], (string) ($i[1] ?? '')], $b['items']));
                    break;
                case 'texto':
                    if (!empty($b['titulo'])) {
                        $pdf->subtitulo((string) $b['titulo']);
                    }
                    $pdf->texto((string) $b['texto']);
                    $pdf->espaciador(4);
                    break;
                case 'nota':
                    $pdf->textoSuave((string) $b['texto']);
                    $pdf->espaciador(4);
                    break;
                case 'banda':
                    $pdf->banda((string) $b['texto']);
                    break;
                case 'firmas':
                    $pdf->firmas($b['items']);
                    break;
                case 'salto':
                    $pdf->saltoDePagina();
                    break;
            }
        }
        return $pdf->contenido();
    }

    /** @param array<string,mixed> $b */
    private static function tabla(GeneradorPDF $pdf, array $b): void
    {
        $enc = array_map('strval', array_values($b['enc']));
        $n = count($enc);
        if (!empty($b['titulo'])) {
            $pdf->subtitulo((string) $b['titulo']);
        }
        if (empty($b['filas']) && empty($b['pie'])) {
            $pdf->textoSuave('Sin registros para este filtro.');
            $pdf->espaciador(6);
            return;
        }
        $mapa = ['l' => 'l', 'c' => 'c', 'r' => 'r'];
        $alin = [];
        $cadena = str_pad((string) ($b['alin'] ?? ''), $n, 'c');
        for ($i = 0; $i < $n; $i++) {
            $alin[$i] = $mapa[$cadena[$i]] ?? 'c';
        }
        $filas = [];
        foreach ($b['filas'] as $f) {
            $filas[] = array_pad(array_map(static fn ($v): string => (string) $v, array_values((array) $f)), $n, '');
        }
        $pesos = (!empty($b['anchos']) && count($b['anchos']) === $n && array_sum($b['anchos']) > 0) ? array_values($b['anchos']) : [];
        $pie = !empty($b['pie']) ? array_pad(array_map(static fn ($v): string => (string) $v, array_values((array) $b['pie'])), $n, '') : null;
        $pdf->tablaAjustada($enc, $filas, $pesos, $alin, $pie);
    }
}
