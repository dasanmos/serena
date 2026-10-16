<?php
declare(strict_types=1);

class Diario
{
    public static function crear(PDO $pdo, int $usuarioId, array $datos): int
    {
        $c = $pdo->prepare(
            'INSERT INTO diario (usuario_id, titulo, contenido, fecha)
             VALUES (:uid, :titulo, :contenido, NOW())'
        );
        $c->execute(['uid' => $usuarioId, 'titulo' => $datos['titulo'] ?: null, 'contenido' => $datos['contenido']]);
        $id = (int) $pdo->lastInsertId();
        if (!empty($datos['etiquetas'])) {
            foreach ($datos['etiquetas'] as $etq) {
                $etq = trim($etq);
                if ($etq === '') continue;
                $pdo->prepare('INSERT IGNORE INTO etiquetas (nombre) VALUES (:n)')->execute(['n' => $etq]);
                $eid = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre=:n LIMIT 1');
                $eid->execute(['n' => $etq]);
                $eid = $eid->fetchColumn();
                $pdo->prepare('INSERT IGNORE INTO diario_etiquetas (diario_id, etiqueta_id) VALUES (?,?)')->execute([$id, $eid]);
            }
        }
        return $id;
    }

    public static function listarDeUsuario(PDO $pdo, int $usuarioId, string $busqueda = '', string $etiqueta = ''): array
    {
        $sql = 'SELECT d.id, d.titulo, d.contenido, d.fecha, d.editado_en
                FROM diario d WHERE d.usuario_id=:uid';
        $params = ['uid' => $usuarioId];
        if ($busqueda) { $sql .= ' AND (d.titulo LIKE :b OR d.contenido LIKE :b)'; $params['b'] = "%$busqueda%"; }
        if ($etiqueta) {
            $sql .= ' AND EXISTS (SELECT 1 FROM diario_etiquetas de INNER JOIN etiquetas e ON e.id=de.etiqueta_id
                                  WHERE de.diario_id=d.id AND e.nombre=:etq)';
            $params['etq'] = $etiqueta;
        }
        $sql .= ' ORDER BY d.fecha DESC';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id, int $usuarioId): ?array
    {
        $c = $pdo->prepare('SELECT * FROM diario WHERE id=:id AND usuario_id=:uid LIMIT 1');
        $c->execute(['id' => $id, 'uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    public static function actualizar(PDO $pdo, int $id, array $datos): void
    {
        $c = $pdo->prepare('UPDATE diario SET titulo=:titulo, contenido=:contenido, editado_en=NOW() WHERE id=:id');
        $c->execute(['titulo' => $datos['titulo'] ?: null, 'contenido' => $datos['contenido'], 'id' => $id]);
    }

    public static function eliminar(PDO $pdo, int $id, int $usuarioId): void
    {
        $pdo->prepare('DELETE FROM diario WHERE id=:id AND usuario_id=:uid')->execute(['id' => $id, 'uid' => $usuarioId]);
    }

    public static function etiquetasDeEntrada(PDO $pdo, int $diarioId): array
    {
        $c = $pdo->prepare('SELECT e.nombre FROM etiquetas e INNER JOIN diario_etiquetas de ON de.etiqueta_id=e.id WHERE de.diario_id=:id');
        $c->execute(['id' => $diarioId]);
        return $c->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function etiquetasDeUsuario(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare('SELECT DISTINCT e.nombre FROM etiquetas e
                            INNER JOIN diario_etiquetas de ON de.etiqueta_id=e.id
                            INNER JOIN diario d ON d.id=de.diario_id
                            WHERE d.usuario_id=:uid ORDER BY e.nombre');
        $c->execute(['uid' => $usuarioId]);
        return $c->fetchAll(PDO::FETCH_COLUMN);
    }

    // --- Imágenes adjuntas ---

    public static function agregarImagen(PDO $pdo, int $diarioId, string $nombreArchivo): int
    {
        $c = $pdo->prepare('INSERT INTO diario_imagenes (diario_id, ruta_imagen) VALUES (:did, :ruta)');
        $c->execute(['did' => $diarioId, 'ruta' => $nombreArchivo]);
        return (int) $pdo->lastInsertId();
    }

    public static function imagenesDeEntrada(PDO $pdo, int $diarioId): array
    {
        $c = $pdo->prepare('SELECT id, ruta_imagen FROM diario_imagenes WHERE diario_id=:did ORDER BY id');
        $c->execute(['did' => $diarioId]);
        return $c->fetchAll();
    }

    public static function contarImagenes(PDO $pdo, int $diarioId): int
    {
        $c = $pdo->prepare('SELECT COUNT(*) FROM diario_imagenes WHERE diario_id=:did');
        $c->execute(['did' => $diarioId]);
        return (int) $c->fetchColumn();
    }

    // Solo devuelve la imagen si pertenece a una entrada del usuario dado — evita IDOR
    // tanto al servir el archivo (diario_imagen.php) como al eliminarlo.
    public static function buscarImagenDeUsuario(PDO $pdo, int $imagenId, int $usuarioId): ?array
    {
        $c = $pdo->prepare(
            'SELECT di.* FROM diario_imagenes di
             INNER JOIN diario d ON d.id = di.diario_id
             WHERE di.id = :iid AND d.usuario_id = :uid LIMIT 1'
        );
        $c->execute(['iid' => $imagenId, 'uid' => $usuarioId]);
        return $c->fetch() ?: null;
    }

    // Filtra por usuario_id directamente en el propio modelo (no solo en quien lo llama):
    // así la regla de propiedad se cumple aunque una vista futura olvide verificar antes.
    public static function eliminarImagen(PDO $pdo, int $imagenId, int $usuarioId): void
    {
        $pdo->prepare(
            'DELETE di FROM diario_imagenes di
             INNER JOIN diario d ON d.id = di.diario_id
             WHERE di.id = :id AND d.usuario_id = :uid'
        )->execute(['id' => $imagenId, 'uid' => $usuarioId]);
    }
}
