<?php
declare(strict_types=1);

class Meta
{
    public static function crear(PDO $pdo, int $usuarioId, array $datos): int
    {
        $c = $pdo->prepare(
            'INSERT INTO metas (usuario_id, habito_id, titulo, descripcion, tipo, fecha_limite)
             VALUES (:uid, :hid, :titulo, :desc, :tipo, :limite)'
        );
        $c->execute([
            'uid'    => $usuarioId,
            'hid'    => $datos['habito_id'] ?: null,
            'titulo' => $datos['titulo'],
            'desc'   => $datos['descripcion'] ?: null,
            'tipo'   => $datos['tipo'],
            'limite' => $datos['fecha_limite'] ?: null,
        ]);
        $id = (int) $pdo->lastInsertId();
        if (!empty($datos['subtareas'])) {
            foreach ($datos['subtareas'] as $st) {
                $st = trim($st);
                if ($st === '') continue;
                $pdo->prepare('INSERT INTO subtareas (meta_id, descripcion) VALUES (?,?)')->execute([$id, $st]);
            }
        }
        return $id;
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, string $estado = ''): array
    {
        $sql = 'SELECT * FROM metas WHERE usuario_id=:uid';
        $params = ['uid' => $usuarioId];
        if ($estado) { $sql .= ' AND estado=:estado'; $params['estado'] = $estado; }
        $sql .= ' ORDER BY fecha_creacion DESC';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id, int $usuarioId): ?array
    {
        $c = $pdo->prepare('SELECT * FROM metas WHERE id=:id AND usuario_id=:uid LIMIT 1');
        $c->execute(['id' => $id, 'uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    public static function actualizar(PDO $pdo, int $id, array $datos): void
    {
        $c = $pdo->prepare(
            'UPDATE metas SET titulo=:titulo, descripcion=:desc, tipo=:tipo,
             fecha_limite=:limite, estado=:estado, porcentaje_avance=:pct WHERE id=:id'
        );
        $c->execute([
            'titulo' => $datos['titulo'], 'desc' => $datos['descripcion'] ?: null,
            'tipo'   => $datos['tipo'],   'limite' => $datos['fecha_limite'] ?: null,
            'estado' => $datos['estado'], 'pct' => (int)$datos['porcentaje_avance'], 'id' => $id
        ]);
    }

    public static function recalcularAvance(PDO $pdo, int $metaId): void
    {
        $t = $pdo->prepare('SELECT COUNT(*) FROM subtareas WHERE meta_id=:id');
        $t->execute(['id' => $metaId]);
        $total = (int)$t->fetchColumn();
        if ($total === 0) return;
        $c2 = $pdo->prepare('SELECT COUNT(*) FROM subtareas WHERE meta_id=:id AND completada=1');
        $c2->execute(['id' => $metaId]);
        $completadas = (int)$c2->fetchColumn();
        $pct = (int)round(($completadas / $total) * 100);
        $estado = $pct >= 100 ? 'completada' : 'activa';
        $pdo->prepare('UPDATE metas SET porcentaje_avance=:pct, estado=:estado WHERE id=:id')
            ->execute(['pct' => $pct, 'estado' => $estado, 'id' => $metaId]);
    }

    public static function subtareas(PDO $pdo, int $metaId): array
    {
        $c = $pdo->prepare('SELECT * FROM subtareas WHERE meta_id=:id ORDER BY id');
        $c->execute(['id' => $metaId]);
        return $c->fetchAll();
    }

    // Solo permite togglear una subtarea si la meta a la que pertenece es del usuarioId dado.
    public static function toggleSubtarea(PDO $pdo, int $subtareaId, int $usuarioId): bool
    {
        $c = $pdo->prepare(
            'UPDATE subtareas st
             INNER JOIN metas m ON m.id = st.meta_id
             SET st.completada = 1 - st.completada
             WHERE st.id = :sid AND m.usuario_id = :uid'
        );
        $c->execute(['sid' => $subtareaId, 'uid' => $usuarioId]);
        return $c->rowCount() > 0;
    }

    // Devuelve el meta_id de una subtarea solo si pertenece a una meta del usuario dado.
    public static function metaIdDeSubtarea(PDO $pdo, int $subtareaId, int $usuarioId): ?int
    {
        $c = $pdo->prepare(
            'SELECT st.meta_id FROM subtareas st INNER JOIN metas m ON m.id = st.meta_id
             WHERE st.id = :sid AND m.usuario_id = :uid LIMIT 1'
        );
        $c->execute(['sid' => $subtareaId, 'uid' => $usuarioId]);
        $r = $c->fetchColumn();
        return $r !== false ? (int)$r : null;
    }

    public static function eliminar(PDO $pdo, int $id): void
    {
        $pdo->prepare('DELETE FROM metas WHERE id=:id')->execute(['id' => $id]);
    }

    // Marca como 'vencida' cualquier meta activa de este usuario cuya fecha_limite ya pasó.
    // Se llama al cargar la página de metas (no hay cron en el proyecto), así que la detección
    // ocurre la primera vez que el estudiante visita la página después de la fecha límite.
    public static function marcarVencidas(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare(
            "UPDATE metas SET estado='vencida'
             WHERE usuario_id=:uid AND estado='activa' AND fecha_limite IS NOT NULL AND fecha_limite < CURDATE()"
        )->execute(['uid' => $usuarioId]);
    }

    // El usuario decide seguir con la meta vencida: vuelve a 'activa' con una fecha límite nueva,
    // sin tocar el progreso ya hecho.
    public static function continuar(PDO $pdo, int $id, int $usuarioId, ?string $nuevaFechaLimite): bool
    {
        $c = $pdo->prepare(
            "UPDATE metas SET estado='activa', fecha_limite=:limite WHERE id=:id AND usuario_id=:uid"
        );
        $c->execute(['limite' => $nuevaFechaLimite ?: null, 'id' => $id, 'uid' => $usuarioId]);
        return $c->rowCount() > 0;
    }

    // El usuario decide empezar de cero: vuelve a 'activa', progreso a 0%, subtareas desmarcadas.
    public static function reiniciar(PDO $pdo, int $id, int $usuarioId, ?string $nuevaFechaLimite): bool
    {
        $c = $pdo->prepare(
            "UPDATE metas SET estado='activa', porcentaje_avance=0, fecha_limite=:limite
             WHERE id=:id AND usuario_id=:uid"
        );
        $c->execute(['limite' => $nuevaFechaLimite ?: null, 'id' => $id, 'uid' => $usuarioId]);
        if ($c->rowCount() > 0) {
            $pdo->prepare('UPDATE subtareas SET completada=0 WHERE meta_id=:id')->execute(['id' => $id]);
        }
        return $c->rowCount() > 0;
    }
}
