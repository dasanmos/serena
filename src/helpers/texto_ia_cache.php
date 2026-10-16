<?php
declare(strict_types=1);

require_once __DIR__ . '/ia.php';

// Versión genérica de obtenerAnalisisCacheado() (analisis_cache.php), pero
// indexada por una CLAVE de texto en vez de un usuario_id — útil para cachear
// cosas que no son "un texto por usuario por día", como el resumen de una
// pregunta de encuesta o un informe institucional filtrado por fechas/grupo.
//
// $calcularBase: función sin argumentos que devuelve el texto base (con datos
// reales, sin IA todavía). $mejorarConIA: callable opcional que recibe el
// resultado de $calcularBase (u otro dato que necesite vía closure) y devuelve
// el texto mejorado por IA, o null si prefiere no mejorar/falló. Si se omite,
// no se llama a la IA y se usa el texto base tal cual.
function obtenerTextoIACacheado(PDO $pdo, string $clave, callable $calcularBase, ?callable $mejorarConIA = null): string
{
    $clave = mb_substr($clave, 0, 191);
    $hoy = date('Y-m-d');

    $consulta = $pdo->prepare(
        'SELECT texto FROM texto_ia_cache WHERE clave = :clave AND fecha = :hoy LIMIT 1'
    );
    $consulta->execute(['clave' => $clave, 'hoy' => $hoy]);
    $cacheado = $consulta->fetchColumn();

    if ($cacheado !== false) {
        return $cacheado;
    }

    $textoBase = $calcularBase();

    $textoFinal = $textoBase;
    if ($mejorarConIA !== null) {
        $mejorado = $mejorarConIA($textoBase);
        if ($mejorado !== null) {
            $textoFinal = $mejorado;
        } elseif (iaDisponible()) {
            // La IA está configurada pero falló: se muestra el texto base sin
            // guardarlo en caché, para reintentar en la próxima carga.
            return $textoBase;
        }
    }

    $guardar = $pdo->prepare(
        'INSERT INTO texto_ia_cache (clave, fecha, texto) VALUES (:clave, :hoy, :texto)
         ON DUPLICATE KEY UPDATE texto = VALUES(texto)'
    );
    $guardar->execute(['clave' => $clave, 'hoy' => $hoy, 'texto' => $textoFinal]);

    return $textoFinal;
}
