<?php
declare(strict_types=1);

/** Utilidades comunes de las pantallas de captura del módulo de informes (refuerzos, restaurante, cartera, caracterización). */

function capFlash(string $clave): ?array
{
    $f = $_SESSION[$clave] ?? null;
    unset($_SESSION[$clave]);
    return is_array($f) ? $f : null;
}

/** Guarda el mensaje y vuelve a la misma pantalla (patrón POST → redirect → GET). */
function capVolver(string $clave, string $url, string $exito, string $error = ''): never
{
    $_SESSION[$clave] = ['exito' => $error === '' ? $exito : '', 'error' => $error];
    header('Location: ' . $url);
    exit;
}

function capFecha(mixed $v): ?string
{
    $v = trim((string) $v);
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);
    return ($d !== false && $d->format('Y-m-d') === $v) ? $v : null;
}

function capNumero(mixed $v): ?float
{
    $v = str_replace([' ', '$'], '', trim((string) $v));
    if ($v === '') {
        return null;
    }
    // «1.250.000,50» o «1250000.50»
    if (preg_match('/,\d{1,2}$/', $v) === 1) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        $v = str_replace(',', '', $v);
    }
    return is_numeric($v) ? (float) $v : null;
}

function capTexto(mixed $v, int $max): ?string
{
    $v = trim((string) $v);
    return $v === '' ? null : mb_substr($v, 0, $max, 'UTF-8');
}
