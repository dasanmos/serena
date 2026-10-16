<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/colegio.php';

class Grupo
{
    /**
     * Todos los grupos con grado, docente y cuántos estudiantes ACTIVOS tiene cada
     * uno — para public/administrador/promocion.php (no tiene sentido promover un
     * grupo mostrando también a los ya desactivados de años anteriores).
     */
    public static function listarConGradoYConteo(PDO $pdo): array
    {
        $params = [];
        $c = $pdo->prepare(
            "SELECT g.id, g.nombre, g.grado_id, gr.nombre AS grado,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.grupo_id = g.id AND u.rol_id = (SELECT id FROM roles WHERE nombre = 'estudiante') AND u.activo = 1) AS total_estudiantes
             FROM grupos g
             INNER JOIN grados gr ON gr.id = g.grado_id
             WHERE 1=1" . condicionColegio('gr.colegio_id', $params) . "
             ORDER BY gr.nombre, g.nombre"
        );
        $c->execute($params);
        return $c->fetchAll();
    }

    /** Defensa en profundidad: aunque la página ya valide, el modelo nunca toca grupos de otro colegio. */
    private static function exigirGrupo(PDO $pdo, int $grupoId): void
    {
        if (!grupoEsDeMiColegio($pdo, $grupoId)) {
            throw new RuntimeException('El grupo no pertenece a tu colegio.');
        }
    }

    public static function cambiarGrado(PDO $pdo, int $grupoId, int $nuevoGradoId): void
    {
        self::exigirGrupo($pdo, $grupoId);
        if (!gradoEsDeMiColegio($pdo, $nuevoGradoId)) {
            throw new RuntimeException('El grado no pertenece a tu colegio.');
        }
        $pdo->prepare('UPDATE grupos SET grado_id = :grado WHERE id = :id')
            ->execute(['grado' => $nuevoGradoId, 'id' => $grupoId]);
    }

    /** Desactiva (egresa) a todos los estudiantes activos de un grupo. Devuelve cuántos. */
    public static function egresarEstudiantes(PDO $pdo, int $grupoId): int
    {
        self::exigirGrupo($pdo, $grupoId);
        $c = $pdo->prepare(
            "UPDATE usuarios SET activo = 0
             WHERE grupo_id = :grupo AND activo = 1
               AND rol_id = (SELECT id FROM roles WHERE nombre = 'estudiante')"
        );
        $c->execute(['grupo' => $grupoId]);
        return $c->rowCount();
    }

    public static function crear(PDO $pdo, int $gradoId, string $nombre): int
    {
        if (!gradoEsDeMiColegio($pdo, $gradoId)) {
            throw new RuntimeException('El grado no pertenece a tu colegio.');
        }
        $consulta = $pdo->prepare('INSERT INTO grupos (grado_id, nombre) VALUES (:grado_id, :nombre)');
        $consulta->execute(['grado_id' => $gradoId, 'nombre' => $nombre]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarConGrado(PDO $pdo): array
    {
        $params = [];
        $c = $pdo->prepare(
            "SELECT g.id, g.nombre, gr.nombre AS grado, g.docente_id, u.nombre AS docente
             FROM grupos g
             INNER JOIN grados gr ON gr.id = g.grado_id
             LEFT JOIN usuarios u ON u.id = g.docente_id
             WHERE 1=1" . condicionColegio('gr.colegio_id', $params) . "
             ORDER BY gr.nombre, g.nombre"
        );
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function asignarDocente(PDO $pdo, int $grupoId, ?int $docenteId): void
    {
        self::exigirGrupo($pdo, $grupoId);
        if ($docenteId !== null && !usuarioEsDeMiColegio($pdo, $docenteId)) {
            throw new RuntimeException('El docente no pertenece a tu colegio.');
        }
        $anterior = $pdo->prepare('SELECT docente_id FROM grupos WHERE id = :id');
        $anterior->execute(['id' => $grupoId]);
        $docenteAnteriorId = (int) ($anterior->fetchColumn() ?: 0);

        $pdo->prepare('UPDATE grupos SET docente_id = :docente_id WHERE id = :id')
            ->execute(['docente_id' => $docenteId, 'id' => $grupoId]);

        // Desvincula al docente anterior (si había uno distinto) para que ya no le
        // aparezca este grupo como "mi grupo" ni en sus grupos asignados.
        if ($docenteAnteriorId > 0 && $docenteAnteriorId !== $docenteId) {
            $pdo->prepare('UPDATE usuarios SET grupo_id = NULL WHERE id = :id AND grupo_id = :gid')
                ->execute(['id' => $docenteAnteriorId, 'gid' => $grupoId]);
            $pdo->prepare('DELETE FROM personal_grupos WHERE usuario_id = :uid AND grupo_id = :gid')
                ->execute(['uid' => $docenteAnteriorId, 'gid' => $grupoId]);
        }

        // El docente nuevo también necesita ver "su" grupo con la sesión normal
        // (usuarios.grupo_id), igual que antes de que existiera personal_grupos — así
        // no se le rompe nada de lo que ya usaba esa columna (docente/grupo.php sigue
        // leyendo $_SESSION['grupo_id']).
        if ($docenteId !== null) {
            $pdo->prepare('UPDATE usuarios SET grupo_id = :grupo_id WHERE id = :id')
                ->execute(['grupo_id' => $grupoId, 'id' => $docenteId]);
            self::asignarPersonal($pdo, $docenteId, [$grupoId]);
        }
    }

    /** Reemplaza por completo los grupos asignados a un miembro del personal (docente/orientador/etc). */
    public static function asignarPersonal(PDO $pdo, int $usuarioId, array $grupoIds): void
    {
        $grupoIds = array_values(array_unique(array_filter(array_map('intval', $grupoIds))));
        if (!usuarioEsDeMiColegio($pdo, $usuarioId)) {
            throw new RuntimeException('El usuario no pertenece a tu colegio.');
        }
        foreach ($grupoIds as $gid) {
            self::exigirGrupo($pdo, $gid);
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM personal_grupos WHERE usuario_id = :uid')->execute(['uid' => $usuarioId]);
            if (!empty($grupoIds)) {
                $insertar = $pdo->prepare('INSERT INTO personal_grupos (usuario_id, grupo_id) VALUES (:uid, :gid)');
                foreach ($grupoIds as $gid) {
                    $insertar->execute(['uid' => $usuarioId, 'gid' => $gid]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Grupos (con grado) asignados a un miembro del personal — puede ser ninguno, uno o varios. */
    public static function gruposDePersonal(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare(
            "SELECT g.id, g.nombre, gr.nombre AS grado
             FROM personal_grupos pg
             INNER JOIN grupos g ON g.id = pg.grupo_id
             INNER JOIN grados gr ON gr.id = g.grado_id
             WHERE pg.usuario_id = :uid
             ORDER BY gr.nombre, g.nombre"
        );
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll();
    }

    /** Solo los ids de grupo — lo que se guarda en la sesión para armar los WHERE ... IN (). */
    public static function idsGruposDePersonal(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare('SELECT grupo_id FROM personal_grupos WHERE usuario_id = :uid');
        $c->execute(['uid' => $usuarioId]);
        return array_map('intval', $c->fetchAll(PDO::FETCH_COLUMN));
    }
}
