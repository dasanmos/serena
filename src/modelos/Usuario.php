<?php
declare(strict_types=1);

require_once __DIR__ . '/RolesUsuario.php';
require_once __DIR__ . '/../helpers/colegio.php';

class Usuario
{
    public static function buscarPorCorreo(PDO $pdo, string $correo): ?array
    {
        $consulta = $pdo->prepare(
            'SELECT u.*, r.nombre AS rol, c.activo AS colegio_activo, c.nombre AS colegio_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             LEFT JOIN colegios c ON c.id = u.colegio_id
             WHERE u.correo = :correo
             LIMIT 1'
        );
        $consulta->execute(['correo' => $correo]);
        $usuario = $consulta->fetch();
        return $usuario ?: null;
    }

    public static function buscarPorId(PDO $pdo, int $id): ?array
    {
        $consulta = $pdo->prepare(
            'SELECT u.*, r.nombre AS rol, c.activo AS colegio_activo, c.nombre AS colegio_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             LEFT JOIN colegios c ON c.id = u.colegio_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $consulta->execute(['id' => $id]);
        $usuario = $consulta->fetch();
        return $usuario ?: null;
    }

    public static function correoExiste(PDO $pdo, string $correo): bool
    {
        $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE correo = :correo LIMIT 1');
        $consulta->execute(['correo' => $correo]);
        return (bool) $consulta->fetch();
    }

    public static function listarPorRol(PDO $pdo, string $rol, ?int $colegioId = null): array
    {
        $params = ['rol' => $rol];
        $c = $pdo->prepare(
            "SELECT u.id, u.nombre, u.correo
             FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
             WHERE (r.nombre = :rol" . RolesUsuario::sqlAdicional($pdo, 'u', $rol) . ") AND u.activo = 1"
            . condicionColegio('u.colegio_id', $params, $colegioId) .
            " ORDER BY u.nombre"
        );
        $c->execute($params);
        return $c->fetchAll();
    }

    /**
     * Orientadores activos con el grupo indicado a su cargo (vía personal_grupos).
     * Usado para avisar por correo de alertas urgentes: un orientador solo debe
     * enterarse de las de sus propios grupos, a diferencia del psicólogo (alcance
     * institucional) — ver AlertaTemprana::avisarPorCorreoSiCritica().
     */
    public static function listarOrientadoresDeGrupo(PDO $pdo, int $grupoId): array
    {
        $c = $pdo->prepare(
            "SELECT DISTINCT u.correo
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             INNER JOIN personal_grupos pg ON pg.usuario_id = u.id
             WHERE (r.nombre = 'orientador'" . RolesUsuario::sqlAdicional($pdo, 'u', 'orientador') . ") AND u.activo = 1 AND pg.grupo_id = :gid"
        );
        $c->execute(['gid' => $grupoId]);
        return $c->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Búsqueda combinada por rol y texto libre (nombre o correo) — usada en varios paneles de administrador. */
    // $pagina es 1-based. Si $porPagina es null, devuelve todo sin paginar (uso
    // interno, ej. exportar CSV) — el listado en pantalla siempre pasa un valor.
    public static function buscarConFiltro(PDO $pdo, string $rol = '', string $busqueda = '', ?int $pagina = null, int $porPagina = 25): array
    {
        $sql = "SELECT u.id, u.nombre, u.correo, u.activo, u.grupo_id, r.nombre AS rol
                FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE 1=1";
        $params = [];
        $sql .= condicionColegio('u.colegio_id', $params);
        if ($rol !== '') {
            $sql .= ' AND r.nombre = :rol';
            $params['rol'] = $rol;
        }
        if ($busqueda !== '') {
            $sql .= ' AND (u.nombre LIKE :busqueda OR u.correo LIKE :busqueda2)';
            $params['busqueda'] = '%' . $busqueda . '%';
            $params['busqueda2'] = '%' . $busqueda . '%';
        }
        $sql .= ' ORDER BY u.nombre';
        if ($pagina !== null) {
            $sql .= ' LIMIT :limite OFFSET :offset';
        }
        $c = $pdo->prepare($sql);
        foreach ($params as $clave => $valor) {
            $c->bindValue($clave, $valor);
        }
        if ($pagina !== null) {
            $c->bindValue('limite', $porPagina, PDO::PARAM_INT);
            $c->bindValue('offset', max(0, $pagina - 1) * $porPagina, PDO::PARAM_INT);
        }
        $c->execute();
        return $c->fetchAll();
    }

    // Mismo filtro que buscarConFiltro() pero solo el conteo total — para calcular
    // el número de páginas sin traer todas las filas.
    public static function contarConFiltro(PDO $pdo, string $rol = '', string $busqueda = ''): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE 1=1";
        $params = [];
        $sql .= condicionColegio('u.colegio_id', $params);
        if ($rol !== '') {
            $sql .= ' AND r.nombre = :rol';
            $params['rol'] = $rol;
        }
        if ($busqueda !== '') {
            $sql .= ' AND (u.nombre LIKE :busqueda OR u.correo LIKE :busqueda2)';
            $params['busqueda'] = '%' . $busqueda . '%';
            $params['busqueda2'] = '%' . $busqueda . '%';
        }
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return (int) $c->fetchColumn();
    }

    public static function actualizar(PDO $pdo, int $id, string $nombre, string $correo, int $rolId, ?int $grupoId): void
    {
        $params = ['nombre' => $nombre, 'correo' => $correo, 'rol_id' => $rolId, 'grupo_id' => $grupoId, 'id' => $id];
        $c = $pdo->prepare(
            'UPDATE usuarios SET nombre = :nombre, correo = :correo, rol_id = :rol_id, grupo_id = :grupo_id WHERE id = :id'
            . condicionColegio('colegio_id', $params)
        );
        $c->execute($params);
    }

    public static function eliminar(PDO $pdo, int $id): void
    {
        $params = ['id' => $id];
        $pdo->prepare('DELETE FROM usuarios WHERE id = :id' . condicionColegio('colegio_id', $params))->execute($params);
    }

    public static function crearEstudiante(PDO $pdo, array $datos): int
    {
        $rolEstudiante = $pdo->query("SELECT id FROM roles WHERE nombre = 'estudiante'")->fetchColumn();

        $consulta = $pdo->prepare(
            'INSERT INTO usuarios
                (nombre, correo, password_hash, rol_id, colegio_id, grupo_id, fecha_nacimiento,
                 consentimiento_datos, fecha_consentimiento, fecha_registro)
             VALUES
                (:nombre, :correo, :password_hash, :rol_id, :colegio_id, :grupo_id, :fecha_nacimiento,
                 1, NOW(), NOW())'
        );
        $consulta->execute([
            'nombre'           => $datos['nombre'],
            'correo'           => $datos['correo'],
            'password_hash'    => password_hash($datos['password'], PASSWORD_DEFAULT),
            'rol_id'           => $rolEstudiante,
            'colegio_id'       => (int) $datos['colegio_id'],
            'grupo_id'         => $datos['grupo_id'],
            'fecha_nacimiento' => $datos['fecha_nacimiento'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizarUltimoAcceso(PDO $pdo, int $id): void
    {
        $consulta = $pdo->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id');
        $consulta->execute(['id' => $id]);
    }

    public static function actualizarPassword(PDO $pdo, int $id, string $passwordNueva): void
    {
        $consulta = $pdo->prepare('UPDATE usuarios SET password_hash = :password_hash WHERE id = :id');
        $consulta->execute([
            'password_hash' => password_hash($passwordNueva, PASSWORD_DEFAULT),
            'id'            => $id,
        ]);
    }

    public static function actualizarTemaPreferido(PDO $pdo, int $id, string $tema): void
    {
        $consulta = $pdo->prepare('UPDATE usuarios SET tema_preferido = :tema WHERE id = :id');
        $consulta->execute(['tema' => $tema, 'id' => $id]);
    }

    public static function actualizarResumenSemanalActivo(PDO $pdo, int $id, bool $activo): void
    {
        $consulta = $pdo->prepare('UPDATE usuarios SET resumen_semanal_activo = :activo WHERE id = :id');
        $consulta->execute(['activo' => $activo ? 1 : 0, 'id' => $id]);
    }

    /** @param string $tamano 'normal' | 'grande' | 'muy_grande' */
    public static function actualizarAccesibilidad(PDO $pdo, int $id, string $tamano, bool $altoContraste): void
    {
        $consulta = $pdo->prepare('UPDATE usuarios SET tamano_texto = :tamano, alto_contraste = :contraste WHERE id = :id');
        $consulta->execute(['tamano' => $tamano, 'contraste' => $altoContraste ? 1 : 0, 'id' => $id]);
    }

    // Query duplicada casi idéntica en 5 archivos distintos (administrador, coordinador,
    // docente, psicólogo, rector — auditoría Sección 4.1). Centralizada acá para que una
    // corrección futura (ej. excluir cuentas inactivas del conteo) se aplique una sola vez.
    // $grupoId opcional filtra solo el grupo del docente; sin él cuenta institucional completa.
    public static function contarEstudiantes(PDO $pdo, ?int $grupoId = null): int
    {
        $params = [];
        $sql = "SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id WHERE r.nombre='estudiante'"
             . condicionColegio('u.colegio_id', $params);
        if ($grupoId !== null) {
            $sql .= ' AND u.grupo_id=:gid';
            $params['gid'] = $grupoId;
        }
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return (int) $c->fetchColumn();
    }

    // Usuarios elegibles para la función "Ver como usuario" del administrador (ver
    // src/helpers/impersonacion.php). Filtro aplicado en la propia consulta como primera
    // capa de defensa: cualquier rol distinto de "estudiante" queda disponible, y de los
    // estudiantes SOLO el correo fijado en CORREO_ESTUDIANTE_PRUEBA. La segunda capa real
    // de seguridad está en puedeVerComo(), que se vuelve a evaluar en el servidor antes
    // de activar la sesión, sin confiar en que esta lista no fue manipulada en el POST.
    public static function listarParaVerComo(PDO $pdo, int $excluirId): array
    {
        $params = ['excluir_id' => $excluirId, 'correo_prueba' => CORREO_ESTUDIANTE_PRUEBA];
        $c = $pdo->prepare(
            "SELECT u.id, u.nombre, u.correo, u.activo, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.activo = 1
               AND u.id != :excluir_id
               AND r.nombre NOT IN ('superadmin','secretaria','administrador','psicologo','orientador')
               AND (r.nombre != 'estudiante' OR u.correo = :correo_prueba)"
            . condicionColegio('u.colegio_id', $params) .
            " ORDER BY r.nombre, u.nombre"
        );
        $c->execute($params);
        return $c->fetchAll();
    }

    // ------------------------------------------------------------------
    // Documento de identidad: permite iniciar sesión con el número de documento (migración 043)
    // ------------------------------------------------------------------

    /** Solo letras y números, en mayúscula y máximo 20 caracteres (sin puntos, espacios ni guiones). */
    public static function normalizarDocumento(string $documento): string
    {
        return substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($documento)) ?? '', 0, 20);
    }

    public static function documentoValido(string $normalizado): bool
    {
        return strlen($normalizado) >= 4 && strlen($normalizado) <= 20;
    }

    /**
     * Cuentas con ese documento (máx. 5; casi siempre una). Devuelve [] si el documento no es válido
     * o si la columna aún no existe (falta la migración 043).
     */
    public static function buscarPorDocumento(PDO $pdo, string $documento): array
    {
        $documento = self::normalizarDocumento($documento);
        if (!self::documentoValido($documento)) {
            return [];
        }
        try {
            $consulta = $pdo->prepare(
                'SELECT u.*, r.nombre AS rol, c.activo AS colegio_activo, c.nombre AS colegio_nombre
                 FROM usuarios u
                 INNER JOIN roles r ON r.id = u.rol_id
                 LEFT JOIN colegios c ON c.id = u.colegio_id
                 WHERE u.documento = :d
                 ORDER BY u.id
                 LIMIT 5'
            );
            $consulta->execute(['d' => $documento]);
            return $consulta->fetchAll();
        } catch (Throwable $e) {
            error_log('Usuario::buscarPorDocumento: ' . $e->getMessage() . ' (¿falta aplicar sql/migraciones/043_documento_login.sql?)');
            return [];
        }
    }

    /** ¿Otra cuenta del mismo colegio ya tiene este documento? */
    public static function documentoEnUso(PDO $pdo, string $documento, ?int $colegioId, int $exceptoId = 0): bool
    {
        try {
            $consulta = $pdo->prepare('SELECT id FROM usuarios WHERE documento = :d AND id <> :excepto AND colegio_id <=> :colegio LIMIT 1');
            $consulta->execute(['d' => $documento, 'excepto' => $exceptoId, 'colegio' => $colegioId]);
            return (bool) $consulta->fetch();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Guarda (o borra, con null o vacío) el documento ya normalizado. Nunca rompe a quien lo llama. */
    public static function guardarDocumento(PDO $pdo, int $id, ?string $documento): bool
    {
        $documento = $documento === null ? '' : self::normalizarDocumento($documento);
        try {
            $pdo->prepare('UPDATE usuarios SET documento = :d WHERE id = :id')
                ->execute(['d' => $documento !== '' ? $documento : null, 'id' => $id]);
            return true;
        } catch (Throwable $e) {
            error_log('Usuario::guardarDocumento: ' . $e->getMessage() . ' (¿falta aplicar sql/migraciones/043_documento_login.sql?)');
            return false;
        }
    }
}
