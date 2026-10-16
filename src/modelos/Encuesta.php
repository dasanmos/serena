<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/colegio.php';

class Encuesta
{
    public static function crear(PDO $pdo, int $creadorId, array $datos): int
    {
        // La encuesta nace en el colegio de quien la crea, y si apunta a un grupo ese grupo debe ser del mismo colegio.
        if (!empty($datos['grupo_id']) && !grupoEsDeMiColegio($pdo, (int) $datos['grupo_id'])) {
            throw new RuntimeException('El grupo no pertenece a tu colegio.');
        }
        $c = $pdo->prepare('INSERT INTO encuestas (colegio_id, creador_id, titulo, descripcion, grupo_id, fecha_cierre) VALUES (?,?,?,?,?,?)');
        $c->execute([colegioParaCrear(), $creadorId, $datos['titulo'], $datos['descripcion'] ?: null, $datos['grupo_id'] ?: null, $datos['fecha_cierre'] ?: null]);
        $eid = (int)$pdo->lastInsertId();
        foreach (($datos['preguntas'] ?? []) as $p) {
            $pdo->prepare('INSERT INTO preguntas_encuesta (encuesta_id, texto, tipo) VALUES (?,?,?)')->execute([$eid, $p['texto'], $p['tipo']]);
            $pid = (int)$pdo->lastInsertId();
            foreach (($p['opciones'] ?? []) as $o) {
                if (trim($o) === '') continue;
                $pdo->prepare('INSERT INTO opciones_pregunta (pregunta_id, texto) VALUES (?,?)')->execute([$pid, $o]);
            }
        }
        return $eid;
    }

    public static function listarParaRol(PDO $pdo, string $rol, ?int $grupoId = null): array
    {
        $params = [];
        if ($rol === 'psicologo') {
            $sql = 'SELECT e.*, u.nombre AS creador FROM encuestas e INNER JOIN usuarios u ON u.id=e.creador_id WHERE 1=1'
                 . condicionColegio('e.colegio_id', $params) . ' ORDER BY e.fecha_creacion DESC';
            $c = $pdo->prepare($sql);
            $c->execute($params);
            return $c->fetchAll();
        }
        // Paréntesis necesarios: el filtro de colegio debe aplicar a AMBAS ramas del OR.
        $sql = 'SELECT e.*, u.nombre AS creador FROM encuestas e INNER JOIN usuarios u ON u.id=e.creador_id WHERE (e.grupo_id IS NULL';
        if ($grupoId) { $sql .= ' OR e.grupo_id=:gid'; $params['gid'] = $grupoId; }
        $sql .= ')' . condicionColegio('e.colegio_id', $params);
        $sql .= ' ORDER BY e.fecha_creacion DESC';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function preguntas(PDO $pdo, int $encuestaId): array
    {
        $q = $pdo->prepare('SELECT * FROM preguntas_encuesta WHERE encuesta_id=:eid ORDER BY id');
        $q->execute(['eid' => $encuestaId]);
        $preg = $q->fetchAll();
        $qo = $pdo->prepare('SELECT * FROM opciones_pregunta WHERE pregunta_id=:pid ORDER BY id');
        foreach ($preg as &$p) {
            $qo->execute(['pid' => $p['id']]);
            $p['opciones'] = $qo->fetchAll();
        }
        return $preg;
    }

    public static function yaConcluida(PDO $pdo, int $encuestaId): bool
    {
        $c = $pdo->prepare('SELECT fecha_cierre FROM encuestas WHERE id=:id LIMIT 1');
        $c->execute(['id' => $encuestaId]);
        $e = $c->fetch();
        return $e && $e['fecha_cierre'] && strtotime($e['fecha_cierre']) < time();
    }

    public static function usuarioYaRespondio(PDO $pdo, int $encuestaId, int $usuarioId): bool
    {
        $c = $pdo->prepare('SELECT id FROM preguntas_encuesta WHERE encuesta_id=:eid LIMIT 1');
        $c->execute(['eid' => $encuestaId]);
        $pid = $c->fetchColumn();
        if (!$pid) return false;
        $c2 = $pdo->prepare('SELECT id FROM respuestas_encuesta WHERE pregunta_id=:pid AND usuario_id=:uid LIMIT 1');
        $c2->execute(['pid' => $pid, 'uid' => $usuarioId]);
        return (bool)$c2->fetch();
    }

    public static function guardarRespuestas(PDO $pdo, int $usuarioId, array $respuestas): void
    {
        foreach ($respuestas as $preguntaId => $resp) {
            $c = $pdo->prepare('INSERT IGNORE INTO respuestas_encuesta (pregunta_id, usuario_id, opcion_id, respuesta_texto) VALUES (?,?,?,?)');
            $c->execute([$preguntaId, $usuarioId, $resp['opcion_id'] ?? null, $resp['texto'] ?? null]);
        }
    }

    public static function buscarPorId(PDO $pdo, int $id): ?array
    {
        $params = ['id' => $id];
        $c = $pdo->prepare('SELECT * FROM encuestas WHERE id=:id' . condicionColegio('colegio_id', $params) . ' LIMIT 1');
        $c->execute($params);
        return $c->fetch() ?: null;
    }
}
