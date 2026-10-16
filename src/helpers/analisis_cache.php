<?php
declare(strict_types=1);

require_once __DIR__ . '/ia.php';

// Envuelve cualquiera de las funciones analisisEstudiante()/analisisPsicologo()/etc.
// (que ya existían en analisis.php, calculando el texto con SQL real) con dos capas
// nuevas: caché de un día (para no llamar a la IA ni recalcular en cada carga de
// página) y reescritura opcional por IA (para que el párrafo suene más natural).
//
// $usuarioId identifica de quién es esta caché (el propio estudiante, o el
// profesional que está viendo su panel). $calcularBase es una función sin
// argumentos — normalmente un closure fn() => analisisTal($pdo, ...) — que
// devuelve el texto ya armado con los datos reales.
function obtenerAnalisisCacheado(PDO $pdo, int $usuarioId, callable $calcularBase): string
{
    $hoy = date('Y-m-d');

    $consulta = $pdo->prepare(
        'SELECT texto FROM analisis_cache WHERE usuario_id = :uid AND fecha = :hoy LIMIT 1'
    );
    $consulta->execute(['uid' => $usuarioId, 'hoy' => $hoy]);
    $cacheado = $consulta->fetchColumn();

    if ($cacheado !== false) {
        return $cacheado;
    }

    $textoBase = $calcularBase();

    // Si la IA no responde bien por cualquier motivo, reescribirConIA() devuelve
    // null y mostramos el texto de plantillas de siempre — el dashboard nunca se
    // rompe ni se queda sin análisis por esto. Si la IA SÍ está configurada pero
    // falló (límite de los modelos gratis, caída), NO se guarda en caché: así se
    // reintenta en la siguiente carga en vez de dejar el texto plano todo el día.
    $mejorado = reescribirConIA($textoBase);
    if ($mejorado === null && iaDisponible()) {
        return $textoBase;
    }
    $textoFinal = $mejorado ?? $textoBase;

    $guardar = $pdo->prepare(
        'INSERT INTO analisis_cache (usuario_id, fecha, texto) VALUES (:uid, :hoy, :texto)
         ON DUPLICATE KEY UPDATE texto = VALUES(texto)'
    );
    $guardar->execute(['uid' => $usuarioId, 'hoy' => $hoy, 'texto' => $textoFinal]);

    return $textoFinal;
}
