<?php
declare(strict_types=1);

require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/analisis.php';
require_once __DIR__ . '/analisis_cache.php';

// Revisa si a este usuario le toca su resumen semanal (nunca se le ha enviado
// uno, o pasaron 7+ días desde el último) y, si toca, lo genera y lo envía por
// correo. Pensada para llamarse en cada login real (ver iniciarSesionUsuario()
// en auth.php) en vez de depender de una tarea programada — InfinityFree no
// ofrece cron confiable en el plan gratuito, así que "revisar al iniciar
// sesión" es el sustituto práctico (ya documentado así en la migración 012).
//
// $usuario debe traer al menos: id, correo, nombre, rol, grupo_id,
// ultimo_resumen_semanal, y grupos_ids (se agrega desde iniciarSesionUsuario()).
function revisarYEnviarResumenSemanal(PDO $pdo, array $usuario): void
{
    if (!smtpConfigurado()) {
        return; // sin Brevo configurado, no hay nada que enviar — no reintenta en cada login
    }
    if ((int) ($usuario['resumen_semanal_activo'] ?? 1) === 0) {
        return; // la persona lo desactivó desde Preferencias
    }

    $ultimoEnvio = $usuario['ultimo_resumen_semanal'] ?? null;
    $debeEnviar = $ultimoEnvio === null || strtotime((string) $ultimoEnvio) <= strtotime('-7 days');

    if (!$debeEnviar) {
        return;
    }

    $usuarioId = (int) $usuario['id'];
    $rol       = $usuario['rol'];
    $grupoId   = $usuario['grupo_id'] !== null ? (int) $usuario['grupo_id'] : null;
    $gruposIds = $usuario['grupos_ids'] ?? [];

    // Se reutilizan exactamente los mismos textos que ya se ven en la tarjeta de
    // "Análisis de datos" de cada dashboard (misma caché diaria, mismo texto),
    // en vez de calcular ni redactar nada distinto para el correo.
    $texto = match ($rol) {
        'estudiante'    => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisEstudiante($pdo, $usuarioId)),
        'psicologo'     => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisPsicologo($pdo)),
        'orientador'    => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisOrientador($pdo, $gruposIds)),
        'docente'       => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisDocente($pdo, $grupoId)),
        'coordinador'   => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisCoordinador($pdo)),
        'rector'        => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisRector($pdo)),
        'administrador' => obtenerAnalisisCacheado($pdo, $usuarioId, fn() => analisisAdministrador($pdo)),
        default         => null,
    };

    // La marca de "ya se revisó" se actualiza SIEMPRE que se llega aquí, haya o
    // no texto, se haya podido enviar el correo o no — así nunca se reintenta en
    // cada login de la misma semana; si algo falló, se reintenta la próxima.
    $marcarRevisado = function () use ($pdo, $usuarioId) {
        $upd = $pdo->prepare('UPDATE usuarios SET ultimo_resumen_semanal = NOW() WHERE id = :id');
        $upd->execute(['id' => $usuarioId]);
    };

    if ($texto === null) {
        $marcarRevisado();
        return;
    }

    $esquema  = (($_SERVER['HTTPS'] ?? '') === 'on') ? 'https://' : 'http://';
    $urlPanel = $esquema . ($_SERVER['HTTP_HOST'] ?? '') . BASE_URL . rutaDashboardPorRol($rol);

    $cuerpoHtml = '<p>' . nl2br(htmlspecialchars($texto)) . '</p>' . botonCorreoHtml('Ver mi panel', $urlPanel);

    enviarCorreo(
        $usuario['correo'],
        'Tu resumen semanal de Serena',
        $texto,
        plantillaCorreoHtml('Tu resumen de la semana', $cuerpoHtml)
    );

    $marcarRevisado();
}
