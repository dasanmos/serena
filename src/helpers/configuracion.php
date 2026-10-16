<?php
declare(strict_types=1);

require_once __DIR__ . '/colegio.php';

/**
 * Ajustes clave/valor editables desde la plataforma (tabla `configuracion`),
 * ahora POR COLEGIO (clave primaria colegio_id + clave; colegio 0 = sin colegio).
 * Nunca lanza excepciones hacia afuera: si la tabla todavía no existe o la base
 * falla, devuelve el valor por defecto — un ajuste cosmético jamás debe romper
 * el envío de un correo ni cargar una página.
 * $colegioId explícito sirve para cron/correos fuera de sesión; sin él se usa el
 * colegio de la sesión (o 0 si no hay).
 */
function configuracionColegioEfectivo(?int $colegioId): int
{
    $c = colegioParaConsulta($colegioId);
    return ($c !== null && $c > 0) ? $c : 0;
}

function configuracionObtener(?PDO $pdo, string $clave, string $porDefecto = '', ?int $colegioId = null): string
{
    static $cache = [];
    $colegio = configuracionColegioEfectivo($colegioId);
    $k = $colegio . ':' . $clave;
    if (array_key_exists($k, $cache)) {
        return $cache[$k] !== '' ? $cache[$k] : $porDefecto;
    }
    $valor = '';
    if ($pdo !== null) {
        try {
            $c = $pdo->prepare('SELECT valor FROM configuracion WHERE colegio_id = :col AND clave = :c');
            $c->execute(['col' => $colegio, 'c' => $clave]);
            $valor = (string) ($c->fetchColumn() ?: '');
        } catch (Throwable $e) {
            error_log('configuracionObtener: ' . $e->getMessage());
        }
    }
    $cache[$k] = $valor;
    return $valor !== '' ? $valor : $porDefecto;
}

/** Guarda el valor; un valor vacío borra el ajuste (vuelve al texto original). */
function configuracionGuardar(PDO $pdo, string $clave, string $valor, ?int $colegioId = null): void
{
    $colegio = configuracionColegioEfectivo($colegioId);
    if ($valor === '') {
        $pdo->prepare('DELETE FROM configuracion WHERE colegio_id = :col AND clave = :c')
            ->execute(['col' => $colegio, 'c' => $clave]);
        return;
    }
    $pdo->prepare(
        'INSERT INTO configuracion (colegio_id, clave, valor) VALUES (:col, :c, :v) ON DUPLICATE KEY UPDATE valor = :v2'
    )->execute(['col' => $colegio, 'c' => $clave, 'v' => $valor, 'v2' => $valor]);
}
