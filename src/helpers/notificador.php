<?php
declare(strict_types=1);

function notificar(PDO $pdo, int $usuarioId, string $tipo, string $mensaje, string $url = ''): void
{
    Notificacion::crear($pdo, $usuarioId, $tipo, $mensaje, $url);
}

function verificarAlertasEmocionales(PDO $pdo, int $usuarioId): void
{
    $diasBajos = EstadoEmocional::diasConsecutivosBajos($pdo, $usuarioId);
    if ($diasBajos >= 3 && !AlertaTemprana::existeReciente($pdo, $usuarioId, 'emociones_bajas')) {
        AlertaTemprana::crear($pdo, $usuarioId, 'emociones_bajas',
            "El estudiante lleva {$diasBajos} días consecutivos con emociones de nivel bajo.", 'alto');
        notificar($pdo, $usuarioId, 'bienestar',
            'Hemos notado que llevas varios días sintiéndote mal. Recuerda que puedes hablar con el psicólogo.',
            '/estudiante/recursos_apoyo.php');
    }
}

// A diferencia de verificarAlertasEmocionales() (que se dispara cuando EL PROPIO
// estudiante registra una emoción), esta regla necesita revisar a estudiantes que
// justamente ya no están entrando — por eso no puede engancharse a su login. Se
// engancha al login de cualquier miembro del personal (psicólogo, orientador,
// docente, coordinador, rector, administrador) en vez de depender de un cron que
// InfinityFree no ofrece confiable en el plan gratuito. Throttle de un día para
// no repetir el barrido completo en cada login del mismo día.
function revisarInactividadProlongada(PDO $pdo): void
{
    $hoy = date('Y-m-d');
    $clave = 'revision_inactividad_prolongada';

    $c = $pdo->prepare('SELECT id FROM texto_ia_cache WHERE clave = :clave AND fecha = :hoy LIMIT 1');
    $c->execute(['clave' => $clave, 'hoy' => $hoy]);
    if ($c->fetch()) {
        return;
    }

    $inactivos = $pdo->query(
        "SELECT u.id FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
         WHERE r.nombre = 'estudiante' AND u.activo = 1
           AND u.fecha_registro < NOW() - INTERVAL 14 DAY
           AND (u.ultimo_acceso IS NULL OR u.ultimo_acceso < NOW() - INTERVAL 14 DAY)"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($inactivos as $usuarioId) {
        $usuarioId = (int) $usuarioId;
        if (!AlertaTemprana::existeReciente($pdo, $usuarioId, 'inactividad_prolongada')) {
            AlertaTemprana::crear(
                $pdo, $usuarioId, 'inactividad_prolongada',
                'No ha iniciado sesión en más de 14 días.',
                'medio'
            );
        }
    }

    $marcar = $pdo->prepare(
        'INSERT INTO texto_ia_cache (clave, fecha, texto) VALUES (:clave, :hoy, :texto)
         ON DUPLICATE KEY UPDATE texto = VALUES(texto)'
    );
    $marcar->execute(['clave' => $clave, 'hoy' => $hoy, 'texto' => 'revisado']);
}
