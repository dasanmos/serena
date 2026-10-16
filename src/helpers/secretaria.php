<?php
declare(strict_types=1);

require_once __DIR__ . '/colegio.php';

/**
 * Alcance de la Secretaría de Educación.
 *
 *  - superadmin: ve TODOS los colegios (alcance = null).
 *  - secretaria: ve solo los colegios con colegios.secretaria_id = su secretaria.
 *  - Falla cerrado: una secretaria sin secretaría asignada (o con la secretaría inactiva)
 *    no ve ningún colegio.
 * Toda consulta de las pantallas de plataforma pasa por sqlAlcanceColegios()/colegioEnAlcance().
 */

/** Secretaría del usuario en sesión (solo rol secretaria y solo si está activa); null si no aplica. */
function secretariaIdActual(PDO $pdo): ?int
{
    static $cache = [];
    if (!esSecretaria() || empty($_SESSION['usuario_id'])) {
        return null;
    }
    $uid = (int) $_SESSION['usuario_id'];
    if (!array_key_exists($uid, $cache)) {
        $c = $pdo->prepare('SELECT s.id FROM usuarios u INNER JOIN secretarias s ON s.id = u.secretaria_id
                            WHERE u.id = :id AND s.activa = 1');
        $c->execute(['id' => $uid]);
        $id = $c->fetchColumn();
        $cache[$uid] = $id !== false ? (int) $id : null;
    }
    return $cache[$uid];
}

/** Datos de la secretaría del usuario (para mostrar nombre y municipios). */
function secretariaActual(PDO $pdo): ?array
{
    $id = secretariaIdActual($pdo);
    if ($id === null) {
        return null;
    }
    $c = $pdo->prepare('SELECT * FROM secretarias WHERE id = :id');
    $c->execute(['id' => $id]);
    $s = $c->fetch();
    if (!$s) {
        return null;
    }
    $m = $pdo->prepare('SELECT municipio FROM secretaria_municipios WHERE secretaria_id = :id ORDER BY municipio');
    $m->execute(['id' => $id]);
    $s['municipios'] = $m->fetchAll(PDO::FETCH_COLUMN);
    return $s;
}

/** IDs de colegios visibles: null = todos (superadmin); array (posiblemente vacío) para secretaria. */
function idsColegiosEnAlcance(PDO $pdo): ?array
{
    if (!esSecretaria()) {
        return null;
    }
    static $cache = [];
    $sid = secretariaIdActual($pdo);
    if ($sid === null) {
        return [];
    }
    if (!isset($cache[$sid])) {
        $c = $pdo->prepare('SELECT id FROM colegios WHERE secretaria_id = :s');
        $c->execute(['s' => $sid]);
        $cache[$sid] = array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN));
    }
    return $cache[$sid];
}

/** Fragmento " AND columna IN (...)" según el alcance; "" para superadmin. $columna es una constante del código. */
function sqlAlcanceColegios(PDO $pdo, string $columna): string
{
    $ids = idsColegiosEnAlcance($pdo);
    if ($ids === null) {
        return '';
    }
    return $ids ? " AND {$columna} IN (" . implode(',', $ids) . ')' : ' AND 1 = 0';
}

function colegioEnAlcance(PDO $pdo, int $colegioId): bool
{
    $ids = idsColegiosEnAlcance($pdo);
    return $ids === null || in_array($colegioId, $ids, true);
}

/** Nombre de la carpeta pública del rol actual: "superadmin" o "secretaria". */
function carpetaPlataforma(): string
{
    return esSecretaria() ? 'secretaria' : 'superadmin';
}
