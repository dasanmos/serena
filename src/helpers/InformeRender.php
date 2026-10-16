<?php
declare(strict_types=1);

/** Pinta la estructura estándar de un informe como HTML imprimible y como CSV. */
final class InformeRender
{
    private static function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }

    private static function logoUri(array $ins): string
    {
        $b = (string) ($ins['logo_b64'] ?? '');
        return ($b !== '' && preg_match('/^[A-Za-z0-9+\/=\s]+$/', $b) === 1) ? 'data:image/jpeg;base64,' . preg_replace('/\s+/', '', $b) : '';
    }

    /** @param array<string,string> $urls pdf, csv, volver */
    public static function html(array $res, array $ins, array $meta, array $urls, string $baseUrl): string
    {
        $e = [self::class, 'e'];
        $h = ($res['orient'] ?? 'v') === 'h';
        $color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($ins['color_primario'] ?? '')) === 1 ? (string) $ins['color_primario'] : '#AD6B05';
        $o = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        $o .= '<title>' . $e($res['titulo']) . '</title><link rel="stylesheet" href="' . $e($baseUrl) . '/assets/css/informes.css">';
        $o .= '<style>:root{--inf-color:' . $e($color) . '}@page{size:letter ' . ($h ? 'landscape' : 'portrait') . ';margin:12mm}</style></head><body class="informe">';
        $o .= '<div class="inf-barra no-print"><a href="' . $e($urls['volver']) . '">← Informes</a><span class="inf-barra-esp"></span>';
        $o .= '<button type="button" id="inf-imprimir">Imprimir</button><a class="inf-boton" href="' . $e($urls['pdf']) . '" target="_blank" rel="noopener">Descargar PDF</a>' . (!empty($urls['pdfqr']) ? '<a class="inf-boton" href="' . $e($urls['pdfqr']) . '" target="_blank" rel="noopener" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>' : '') . '<a class="inf-boton" href="' . $e($urls['csv']) . '">Descargar CSV</a></div>';
        $o .= '<main class="inf-hoja">';
        $logo = self::logoUri($ins);
        $o .= '<header class="inf-cab">' . ($logo !== '' ? '<img class="inf-logo" src="' . $e($logo) . '" alt="">' : '') . '<div class="inf-inst"><strong>' . $e(mb_strtoupper((string) ($ins['nombre_oficial'] ?? ''), 'UTF-8')) . '</strong>';
        $l = [];
        if (($ins['codigo_dane'] ?? '') !== '') { $l[] = 'Código DANE: ' . $ins['codigo_dane']; }
        if (($ins['nit'] ?? '') !== '') { $l[] = 'NIT: ' . $ins['nit']; }
        if ($l) { $o .= '<span>' . $e(implode(' · ', $l)) . '</span>'; }
        if (($ins['resolucion_numero'] ?? '') !== '') {
            $o .= '<span>Resolución ' . $e($ins['resolucion_numero']) . (($ins['resolucion_fecha'] ?? '') !== '' ? ' del ' . $e(Informes::fecha((string) $ins['resolucion_fecha'])) : '') . (($ins['resolucion_entidad'] ?? '') !== '' ? ' · ' . $e($ins['resolucion_entidad']) : '') . '</span>';
        }
        $ub = implode(' · ', array_filter([(string) ($ins['direccion'] ?? ''), (string) ($ins['municipio'] ?? ''), (string) ($ins['telefono'] ?? ''), (string) ($ins['correo'] ?? '')]));
        if ($ub !== '') { $o .= '<span>' . $e($ub) . '</span>'; }
        $o .= '</div></header>';
        $o .= '<h1 class="inf-titulo">' . $e($res['titulo']) . '</h1>';
        if ((string) ($res['subtitulo'] ?? '') !== '') {
            $o .= '<p class="inf-sub">' . $e($res['subtitulo']) . '</p>';
        }
        foreach ($res['bloques'] as $b) {
            $o .= self::bloque($b);
        }
        $o .= '<footer class="inf-pie">Generado el ' . $e($meta['fecha'] ?? '') . ' por ' . $e($meta['usuario'] ?? '') . ' · Serena' . (!empty($meta['ref']) ? ' · Ref. ' . $e($meta['ref']) : '') . '<br>Documento informativo; no reemplaza un certificado con código de verificación.</footer>';
        $o .= '</main><script src="' . $e($baseUrl) . '/assets/js/informes.js" defer></script></body></html>';
        return $o;
    }

    private static function bloque(array $b): string
    {
        $e = [self::class, 'e'];
        switch ($b['t'] ?? '') {
            case 'tabla':
                $n = count($b['enc']);
                $alin = str_pad((string) ($b['alin'] ?? ''), $n, 'c');
                $cls = static fn (int $j): string => ['l' => 'a-i', 'r' => 'a-d', 'c' => 'a-c'][$alin[$j]] ?? 'a-c';
                $o = '<section class="inf-bloque">' . ((string) ($b['titulo'] ?? '') !== '' ? '<h2>' . $e($b['titulo']) . '</h2>' : '') . '<div class="inf-scroll"><table class="inf-tabla"><thead><tr>';
                foreach ($b['enc'] as $h) {
                    $o .= '<th>' . $e($h) . '</th>';
                }
                $o .= '</tr></thead><tbody>';
                if (!$b['filas']) {
                    $o .= '<tr><td colspan="' . $n . '" class="inf-vacio">Sin registros para este filtro.</td></tr>';
                }
                foreach ($b['filas'] as $f) {
                    $o .= '<tr>';
                    for ($j = 0; $j < $n; $j++) {
                        $o .= '<td class="' . $cls($j) . '">' . $e($f[$j] ?? '') . '</td>';
                    }
                    $o .= '</tr>';
                }
                $o .= '</tbody>';
                if (!empty($b['pie'])) {
                    $o .= '<tfoot><tr>';
                    for ($j = 0; $j < $n; $j++) {
                        $o .= '<td class="' . $cls($j) . '">' . $e($b['pie'][$j] ?? '') . '</td>';
                    }
                    $o .= '</tr></tfoot>';
                }
                return $o . '</table></div></section>';
            case 'pares':
                $o = '<section class="inf-bloque">' . ((string) ($b['titulo'] ?? '') !== '' ? '<h2>' . $e($b['titulo']) . '</h2>' : '') . '<dl class="inf-pares">';
                foreach ($b['items'] as [$k, $v]) {
                    $o .= '<div><dt>' . $e($k) . '</dt><dd>' . $e($v) . '</dd></div>';
                }
                return $o . '</dl></section>';
            case 'texto':
                return '<section class="inf-bloque">' . ((string) ($b['titulo'] ?? '') !== '' ? '<h2>' . $e($b['titulo']) . '</h2>' : '') . '<p class="inf-texto">' . nl2br($e($b['texto'])) . '</p></section>';
            case 'nota':
                return '<p class="inf-nota">' . $e($b['texto']) . '</p>';
            case 'banda':
                return '<h2 class="inf-banda">' . $e($b['texto']) . '</h2>';
            case 'firmas':
                $o = '<div class="inf-firmas">';
                foreach ($b['items'] as [$n, $c]) {
                    $o .= '<div><span class="inf-linea"></span><strong>' . $e($n) . '</strong><small>' . $e($c) . '</small></div>';
                }
                return $o . '</div>';
            case 'salto':
                return '<div class="inf-salto"></div>';
        }
        return '';
    }

    private static function celda(mixed $v): string
    {
        $s = (string) $v;
        return ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true) && !is_numeric($s)) ? "'" . $s : $s;
    }

    /** Escribe el CSV en la salida (UTF-8 con BOM para Excel). */
    public static function csv(array $res, array $ins, array $meta): void
    {
        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF");
        $w = static function (array $f) use ($o): void {
            fputcsv($o, array_map([self::class, 'celda'], $f), ',', '"', '');
        };
        $w([(string) ($ins['nombre_oficial'] ?? '')]);
        $w([(string) $res['titulo']]);
        if ((string) ($res['subtitulo'] ?? '') !== '') {
            $w([(string) $res['subtitulo']]);
        }
        $w(['Generado el ' . ($meta['fecha'] ?? '') . ' por ' . ($meta['usuario'] ?? '')]);
        $w([]);
        foreach ($res['bloques'] as $b) {
            switch ($b['t'] ?? '') {
                case 'tabla':
                    if ((string) ($b['titulo'] ?? '') !== '') { $w([(string) $b['titulo']]); }
                    $w($b['enc']);
                    foreach ($b['filas'] as $f) { $w($f); }
                    if (!empty($b['pie'])) { $w($b['pie']); }
                    $w([]);
                    break;
                case 'pares':
                    if ((string) ($b['titulo'] ?? '') !== '') { $w([(string) $b['titulo']]); }
                    foreach ($b['items'] as $it) { $w([$it[0], $it[1]]); }
                    $w([]);
                    break;
                case 'texto':
                    $w([(string) ($b['titulo'] ?? ''), (string) $b['texto']]);
                    break;
                case 'nota':
                case 'banda':
                    $w([(string) $b['texto']]);
                    break;
                case 'firmas':
                    foreach ($b['items'] as $it) { $w([$it[1], $it[0]]); }
                    break;
                case 'salto':
                    $w([]);
                    break;
            }
        }
        fclose($o);
    }
}
