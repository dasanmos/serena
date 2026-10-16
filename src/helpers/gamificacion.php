<?php
declare(strict_types=1);

// Se llama después de acciones que podrían desbloquear una insignia (marcar hábito,
// completar meta, o simplemente al cargar el dashboard para "primer_mes").
// Silenciosa por diseño: revisa, otorga si corresponde, notifica — nunca falla la página
// que la llama si algo sale mal aquí, porque otorgar una insignia nunca debe romper
// el flujo principal del usuario.
function evaluarInsignias(PDO $pdo, int $usuarioId): void
{
    try {
        // --- Racha de 7 días en cualquier hábito activo ---
        $habitos = Habito::listarDeUsuario($pdo, $usuarioId);
        foreach ($habitos as $h) {
            if (Habito::rachaActual($pdo, $h['id']) >= 7) {
                $otorgada = Insignia::otorgarPorCriterio($pdo, $usuarioId, 'racha_habito_7');
                if ($otorgada) notificarInsigniaObtenida($pdo, $usuarioId, $otorgada);
                break;
            }
        }

        // --- Primer mes: cuenta con 30+ días de antigüedad ---
        $usuario = Usuario::buscarPorId($pdo, $usuarioId);
        if ($usuario && !empty($usuario['fecha_registro'])) {
            $diasDesdeRegistro = (int) floor((time() - strtotime($usuario['fecha_registro'])) / 86400);
            if ($diasDesdeRegistro >= 30) {
                $otorgada = Insignia::otorgarPorCriterio($pdo, $usuarioId, 'primer_mes');
                if ($otorgada) notificarInsigniaObtenida($pdo, $usuarioId, $otorgada);
            }
        }

        // --- Primera meta completada ---
        $metasCompletadas = Meta::listarDeUsuario($pdo, $usuarioId, 'completada');
        if (!empty($metasCompletadas)) {
            $otorgada = Insignia::otorgarPorCriterio($pdo, $usuarioId, 'primera_meta_completada');
            if ($otorgada) notificarInsigniaObtenida($pdo, $usuarioId, $otorgada);
        }

        // --- Insignias por constancia (v2): se cuentan filas con una sola consulta cada una ---
        $otorgar = function (string $criterio) use ($pdo, $usuarioId): void {
            $otorgada = Insignia::otorgarPorCriterio($pdo, $usuarioId, $criterio);
            if ($otorgada) notificarInsigniaObtenida($pdo, $usuarioId, $otorgada);
        };
        $contar = function (string $sql) use ($pdo, $usuarioId): int {
            $c = $pdo->prepare($sql);
            $c->execute(['u' => $usuarioId]);
            return (int) $c->fetchColumn();
        };

        foreach ($habitos as $h) {
            if (Habito::rachaActual($pdo, $h['id']) >= 30) { $otorgar('racha_habito_30'); break; }
        }
        if ($contar('SELECT COUNT(*) FROM diario WHERE usuario_id = :u') >= 1) { $otorgar('primer_diario'); }
        if ($contar('SELECT COUNT(*) FROM estado_emocional WHERE usuario_id = :u') >= 10) { $otorgar('emociones_10'); }
        if ($contar('SELECT COUNT(*) FROM sueno WHERE usuario_id = :u') >= 7) { $otorgar('sueno_7_registros'); }
        if (count($metasCompletadas) >= 5) { $otorgar('metas_5'); }
        if (isset($diasDesdeRegistro) && $diasDesdeRegistro >= 90) { $otorgar('tres_meses'); }
    } catch (Throwable $e) {
        error_log('evaluarInsignias: ' . $e->getMessage());
    }
}

function notificarInsigniaObtenida(PDO $pdo, int $usuarioId, string $nombreInsignia): void
{
    Notificacion::crear($pdo, $usuarioId, 'logro', "¡Nueva insignia desbloqueada: {$nombreInsignia}! 🏅", '/estudiante/perfil.php');
}
