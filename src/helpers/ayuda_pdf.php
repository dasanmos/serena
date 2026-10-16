<?php
declare(strict_types=1);

require_once __DIR__ . '/GeneradorPDF.php';
require_once __DIR__ . '/ayuda.php';

/**
 * Deja el texto listo para el PDF: quita emojis y símbolos que Helvetica/WinAnsi
 * no dibuja, y limpia los espacios que quedan (p. ej. «con la bandera ?» -> «con la bandera?»).
 */
function ayudaLimpiarParaPdf(string $t): string
{
    $t = preg_replace('/[^\x{0009}\x{0020}-\x{007E}\x{00A0}-\x{00FF}\x{2013}\x{2014}\x{2018}-\x{201D}\x{2022}\x{2026}]/u', '', $t) ?? $t;
    $t = preg_replace('/ {2,}/', ' ', $t) ?? $t;
    $t = preg_replace('/ +([,.:;?!)])/', '$1', $t) ?? $t;
    $t = preg_replace('/\( +/', '(', $t) ?? $t;
    return trim($t);
}

/**
 * Construye el PDF del libro. $paginasPrevias: mapa ancla => página, obtenido de una
 * primera pasada (para que el índice muestre los números correctos). $paginasOut
 * recibe el mapa de esta pasada.
 */
function ayudaConstruirPdf(array $libro, array $paginasPrevias, array &$paginasOut): GeneradorPDF
{
    $pdf = new GeneradorPDF();
    $paginasOut = [];
    $limpio = 'ayudaLimpiarParaPdf';

    $pie = 'Serena — ' . $libro['titulo'] . ($libro['rolNombre'] !== '' ? ' · ' . $libro['rolNombre'] : ' · General');
    $pdf->configurarPie($pie);

    // Portada (sin pie de página)
    $meta = [];
    if ($libro['version'] !== '') {
        $meta[] = 'Versión ' . $libro['version'];
    }
    if ($libro['actualizado'] !== '') {
        $meta[] = 'Contenido actualizado el ' . ayudaFechaLarga($libro['actualizado']);
    }
    $pdf->pieVisible(false);
    $pdf->portada(
        'Manual de usuario',
        $libro['subtitulo'],
        [implode(' · ', $meta), 'Generado el ' . date('d/m/Y H:i')]
    );
    $pdf->saltoDePagina();
    $pdf->pieVisible(true);

    // Índice
    $pdf->titulo('Contenido');
    $pdf->espaciador(6);
    foreach ($libro['partes'] as $i => $parte) {
        $pdf->lineaIndice('Parte ' . ($i + 1) . '. ' . $limpio($parte['titulo']), (int) ($paginasPrevias[$parte['id']] ?? 0), 0);
        foreach ($parte['capitulos'] as $cap) {
            $pdf->lineaIndice($cap['num'] . '. ' . $limpio($cap['titulo']), (int) ($paginasPrevias[$cap['ancla']] ?? 0), 1);
        }
    }

    // Cuerpo
    foreach ($libro['partes'] as $i => $parte) {
        $pdf->parte('Parte ' . ($i + 1), $limpio($parte['titulo']), $limpio((string) ($parte['intro'] ?? '')));
        $paginasOut[$parte['id']] = $pdf->paginaActual();

        foreach ($parte['capitulos'] as $cap) {
            $pdf->encabezadoCapitulo('Capítulo ' . $cap['num'] . (!empty($cap['grupo']) ? ' · ' . $limpio((string) $cap['grupo']) : ''), $limpio($cap['titulo']), $limpio((string) ($cap['resumen'] ?? '')));
            $paginasOut[$cap['ancla']] = $pdf->paginaActual();

            foreach ($cap['secciones'] as $sec) {
                $pdf->encabezadoSeccion($limpio($sec['titulo']));
                foreach (($sec['parrafos'] ?? []) as $p) {
                    $pdf->parrafo($limpio((string) $p));
                }
                if (!empty($sec['pasos'])) {
                    $pdf->lista(array_map($limpio, array_map('strval', $sec['pasos'])), true);
                }
                if (!empty($sec['puntos'])) {
                    $pdf->lista(array_map($limpio, array_map('strval', $sec['puntos'])), false);
                }
                foreach (ayudaImagenesSeccion($sec) as $img) {
                    $pdf->imagen($img['ruta'], $limpio($img['pie']));
                }
                if (!empty($sec['consejo'])) {
                    $pdf->cuadro('Consejo', $limpio((string) $sec['consejo']), 'consejo');
                }
                if (!empty($sec['aviso'])) {
                    $pdf->cuadro('Importante', $limpio((string) $sec['aviso']), 'aviso');
                }
            }
        }
    }
    return $pdf;
}

function ayudaEnviarPdf(array $libro, string $nombreBase): void
{
    // Pasada 1: solo para saber en qué página cae cada capítulo.
    $paginas = [];
    ayudaConstruirPdf($libro, [], $paginas);
    // Pasada 2: el PDF definitivo, con el índice ya numerado.
    $ignorado = [];
    $pdf = ayudaConstruirPdf($libro, $paginas, $ignorado);
    $pdf->salidaDescarga($nombreBase);
}
