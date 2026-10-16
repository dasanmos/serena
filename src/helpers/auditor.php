<?php
declare(strict_types=1);

require_once __DIR__ . '/../modelos/Auditoria.php';

/**
 * Si la acción ocurre durante "Ver como usuario", queda a nombre del
 * administrador REAL (no de la identidad prestada) — si no, la bitácora se
 * atribuye mal justo en el caso que más importa: un admin actuando mientras
 * suplanta a otro admin. "Ver como" entre administradores sigue permitido;
 * esto solo corrige a quién se le atribuye la acción en la auditoría.
 */
function auditarAccion(PDO $pdo, string $accion, string $tabla, int $registroId, array $anterior = [], array $nuevo = []): void
{
    $original = function_exists('identidadAdminOriginal') ? identidadAdminOriginal() : null;
    if ($original) {
        $usuarioId = (int) $original['usuario_id'];
        $nuevo['actuando_como'] = [
            'id' => isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null,
            'nombre' => $_SESSION['nombre'] ?? null,
        ];
    } else {
        $usuarioId = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
    }
    Auditoria::registrar($pdo, $usuarioId, $accion, $tabla, $registroId, $anterior, $nuevo);
}
