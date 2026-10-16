<?php
declare(strict_types=1);

/**
 * Utilidades para los mapas (Leaflet + OpenStreetMap; no requieren clave de API).
 * Las coordenadas llegan desde el navegador, así que SIEMPRE se validan aquí.
 */

/** Tipos de lugar de ayuda: clave guardada => texto visible. */
function lugaresApoyoTipos(): array
{
    return [
        'salud_mental'  => 'Salud mental',
        'hospital'      => 'Hospital / urgencias',
        'casa_justicia' => 'Casa de Justicia',
        'icbf'          => 'ICBF',
        'comisaria'     => 'Comisaría de familia',
        'otro'          => 'Otro',
    ];
}

/**
 * Convierte lo que llega por POST en [latitud, longitud] (floats) o null si falta o no es válido.
 * Rango: Colombia continental e insular (incluye San Andrés y Leticia).
 */
function coordenadasDesdePost(mixed $lat, mixed $lng): ?array
{
    $lat = is_string($lat) ? str_replace(',', '.', trim($lat)) : $lat;
    $lng = is_string($lng) ? str_replace(',', '.', trim($lng)) : $lng;
    if ($lat === '' || $lng === '' || $lat === null || $lng === null || !is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    $lat = round((float) $lat, 6);
    $lng = round((float) $lng, 6);
    if ($lat < -4.5 || $lat > 13.7 || $lng < -82.0 || $lng > -66.5) {
        return null;
    }
    return [$lat, $lng];
}

/** Recorta y limpia un texto libre (dirección, teléfono...) o devuelve null si queda vacío. */
function textoCortoONulo(mixed $valor, int $max): ?string
{
    $t = mb_substr(trim((string) $valor), 0, $max);
    return $t === '' ? null : $t;
}

/** JSON seguro para incrustar dentro de una etiqueta <script type="application/json">. */
function jsonParaHtml(mixed $datos): string
{
    return (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

/**
 * Selector de ubicación para un formulario. Debe ir DENTRO de un <form> que tenga los campos
 * name="direccion", name="municipio" (opcional), name="nombre" (opcional) y los ocultos
 * name="latitud" / name="longitud". mapas.js lo activa solo (data-mapa="picker").
 */
function mapaSelectorHtml(): string
{
    return '<div class="mapa-bloque mapa-picker" data-mapa="picker">'
        . '<div class="mapa-picker-barra">'
        . '<button type="button" class="boton-secundario" data-accion="ubicar">📍 Ubicar la dirección en el mapa</button>'
        . '<button type="button" class="boton-secundario" data-accion="quitar" hidden>Quitar ubicación</button>'
        . '</div>'
        . '<div class="mapa-lienzo" aria-label="Mapa para ubicar la dirección. También puedes hacer clic para colocar el pin."></div>'
        . '<p class="mapa-estado" role="status" aria-live="polite"></p>'
        . '<div class="mapa-alternativas"></div>'
        . '<p class="mapa-coords"></p>'
        . '</div>';
}

/**
 * Enlaces "Cómo llegar": cada uno abre una app o sitio de mapas con la ruta hacia el destino
 * (la app calcula el recorrido desde donde esté la persona). Son simples enlaces: no usan ninguna API
 * y no envían nada a Serena. Devuelve [etiqueta => url]; Apple Maps solo en dispositivos Apple y
 * el enlace geo: (app de mapas predeterminada) solo en Android.
 */
function enlacesComoLlegar(float $lat, float $lng, string $nombre = ''): array
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $ll = rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.') . ',' . rtrim(rtrim(number_format($lng, 6, '.', ''), '0'), '.');
    $enlaces = [];
    if (stripos($ua, 'Android') !== false) {
        $enlaces['Mi app de mapas'] = 'geo:' . $ll . '?q=' . $ll . ($nombre !== '' ? '(' . rawurlencode($nombre) . ')' : '');
    }
    $enlaces['Google Maps'] = 'https://www.google.com/maps/dir/?api=1&destination=' . $ll;
    $enlaces['Waze'] = 'https://waze.com/ul?ll=' . $ll . '&navigate=yes';
    if (preg_match('/iPhone|iPad|iPod|Macintosh/i', $ua)) {
        $enlaces['Apple Maps'] = 'https://maps.apple.com/?daddr=' . $ll . '&dirflg=d';
    }
    [$la, $lo] = explode(',', $ll);
    $enlaces['OpenStreetMap'] = 'https://www.openstreetmap.org/?mlat=' . $la . '&mlon=' . $lo . '#map=17/' . $la . '/' . $lo;
    return $enlaces;
}

/** Menú desplegable «Cómo llegar» (sin JavaScript: <details>). */
function mapaComoLlegarHtml(float $lat, float $lng, string $nombre = ''): string
{
    $h = '<details class="como-llegar"><summary>🧭 Cómo llegar</summary><div class="como-llegar-lista">';
    foreach (enlacesComoLlegar($lat, $lng, $nombre) as $etiqueta => $url) {
        $h .= '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($etiqueta) . '</a>';
    }
    return $h . '</div></details>';
}
