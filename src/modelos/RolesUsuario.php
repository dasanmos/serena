<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';

/**
 * Cuentas con varios roles (migración 044_multirol.sql).
 *
 * usuarios.rol_id es el rol PRINCIPAL; usuario_roles guarda los ADICIONALES. Quien tiene más de uno
 * elige con cuál entrar al iniciar sesión (public/ingresar_como.php) y puede cambiar después desde
 * Cuenta → «Cambiar de rol».
 *
 * Reglas:
 *   - Solo roles de un colegio: nunca estudiante (son menores y su cuenta es solo suya), ni
 *     superadmin ni secretaría de educación (cuentas de plataforma).
 *   - El rol principal es siempre el de personal con más prioridad: si una cuenta de acudiente recibe el
 *     rol docente, pasa a ser docente principal y «acudiente» queda como adicional. Así sigue apareciendo
 *     en las listas de docentes, grupos y asignaciones.
 *   - Sin la migración 044 todo sigue funcionando con un solo rol (nada se rompe).
 */
final class RolesUsuario
{
    /** Roles que una cuenta puede combinar. */
    public const COMBINABLES = ['administrador', 'rector', 'coordinador', 'secretaria_academica', 'psicologo', 'orientador', 'docente', 'acudiente'];

    /** Orden de prioridad para el rol principal (y para mostrarlos). */
    public const PRIORIDAD = ['administrador', 'rector', 'coordinador', 'secretaria_academica', 'psicologo', 'orientador', 'docente', 'acudiente'];

    private static ?bool $tabla = null;

    public static function disponible(PDO $pdo): bool
    {
        if (self::$tabla === null) {
            try {
                $pdo->query('SELECT 1 FROM usuario_roles LIMIT 1');
                self::$tabla = true;
            } catch (Throwable $e) {
                self::$tabla = false;
            }
        }
        return self::$tabla;
    }

    private static function rolId(PDO $pdo, string $nombre): int
    {
        $q = $pdo->prepare('SELECT id FROM roles WHERE nombre = ?');
        $q->execute([$nombre]);
        return (int) $q->fetchColumn();
    }

    /** Rol principal + adicionales, en orden de prioridad (sin repetidos). */
    public static function roles(PDO $pdo, int $usuarioId): array
    {
        $q = $pdo->prepare('SELECT r.nombre FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id = ?');
        $q->execute([$usuarioId]);
        $roles = array_filter([(string) $q->fetchColumn()]);
        if (self::disponible($pdo)) {
            $q = $pdo->prepare('SELECT r.nombre FROM usuario_roles ur INNER JOIN roles r ON r.id = ur.rol_id WHERE ur.usuario_id = ?');
            $q->execute([$usuarioId]);
            $roles = array_merge($roles, $q->fetchAll(PDO::FETCH_COLUMN));
        }
        $roles = array_values(array_unique($roles));
        usort($roles, static function (string $a, string $b): int {
            $pa = array_search($a, self::PRIORIDAD, true);
            $pb = array_search($b, self::PRIORIDAD, true);
            return ($pa === false ? 99 : $pa) <=> ($pb === false ? 99 : $pb);
        });
        return $roles;
    }

    /** Solo los adicionales (para la ficha del usuario). */
    public static function adicionales(PDO $pdo, int $usuarioId): array
    {
        if (!self::disponible($pdo)) {
            return [];
        }
        $q = $pdo->prepare('SELECT r.nombre FROM usuario_roles ur INNER JOIN roles r ON r.id = ur.rol_id WHERE ur.usuario_id = ? ORDER BY r.nombre');
        $q->execute([$usuarioId]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }

    /** ¿Se le puede sumar un rol a esta cuenta? Devuelve '' si sí, o el motivo. */
    public static function puedeCombinar(string $rolPrincipal, string $rolNuevo): string
    {
        if (!in_array($rolPrincipal, self::COMBINABLES, true)) {
            return 'Las cuentas de ' . ($rolPrincipal === 'estudiante' ? 'estudiante' : 'plataforma') . ' no pueden tener otro rol.';
        }
        if (!in_array($rolNuevo, self::COMBINABLES, true)) {
            return 'Ese rol no se puede combinar con otro.';
        }
        return '';
    }

    /**
     * Agrega un rol a la cuenta (en su mismo colegio). Si el rol nuevo tiene más prioridad que el
     * principal, se intercambian. Devuelve '' si quedó bien o el mensaje de error.
     */
    public static function agregar(PDO $pdo, int $usuarioId, string $rolNuevo, ?int $porId = null): string
    {
        if (!self::disponible($pdo)) {
            return 'Para dar varios roles a una cuenta falta aplicar la migración sql/migraciones/044_multirol.sql.';
        }
        $q = $pdo->prepare('SELECT u.id, u.rol_id, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id = ?');
        $q->execute([$usuarioId]);
        $u = $q->fetch();
        if (!$u) {
            return 'La cuenta no existe.';
        }
        $err = self::puedeCombinar((string) $u['rol'], $rolNuevo);
        if ($err !== '') {
            return $err;
        }
        if ($u['rol'] === $rolNuevo || in_array($rolNuevo, self::adicionales($pdo, $usuarioId), true)) {
            return ''; // ya lo tiene
        }
        $idNuevo = self::rolId($pdo, $rolNuevo);
        if ($idNuevo === 0) {
            return 'El rol «' . $rolNuevo . '» no existe.';
        }
        $prioNuevo = array_search($rolNuevo, self::PRIORIDAD, true);
        $prioActual = array_search((string) $u['rol'], self::PRIORIDAD, true);
        if ($prioNuevo < $prioActual) {
            // El nuevo pasa a principal y el actual queda como adicional.
            $pdo->prepare('UPDATE usuarios SET rol_id = ? WHERE id = ?')->execute([$idNuevo, $usuarioId]);
            $pdo->prepare('INSERT IGNORE INTO usuario_roles (usuario_id, rol_id, creado_por) VALUES (?,?,?)')
                ->execute([$usuarioId, (int) $u['rol_id'], $porId]);
        } else {
            $pdo->prepare('INSERT IGNORE INTO usuario_roles (usuario_id, rol_id, creado_por) VALUES (?,?,?)')
                ->execute([$usuarioId, $idNuevo, $porId]);
        }
        return '';
    }

    /** Quita un rol. Si era el principal, el adicional de más prioridad pasa a principal. */
    public static function quitar(PDO $pdo, int $usuarioId, string $rol): string
    {
        if (!self::disponible($pdo)) {
            return '';
        }
        $roles = self::roles($pdo, $usuarioId);
        if (!in_array($rol, $roles, true)) {
            return '';
        }
        if (count($roles) === 1) {
            return 'Es el único rol de la cuenta: para quitarlo, desactiva la cuenta.';
        }
        $idRol = self::rolId($pdo, $rol);
        $q = $pdo->prepare('SELECT rol_id FROM usuarios WHERE id = ?');
        $q->execute([$usuarioId]);
        if ((int) $q->fetchColumn() === $idRol) {
            $siguiente = array_values(array_diff($roles, [$rol]))[0];
            $idSig = self::rolId($pdo, $siguiente);
            $pdo->prepare('UPDATE usuarios SET rol_id = ? WHERE id = ?')->execute([$idSig, $usuarioId]);
            $pdo->prepare('DELETE FROM usuario_roles WHERE usuario_id = ? AND rol_id = ?')->execute([$usuarioId, $idSig]);
        } else {
            $pdo->prepare('DELETE FROM usuario_roles WHERE usuario_id = ? AND rol_id = ?')->execute([$usuarioId, $idRol]);
        }
        return '';
    }

    /**
     * Fragmento SQL « OR EXISTS(...) » para que las listas por rol incluyan a quien tiene ese rol como
     * adicional. $alias es el alias de la tabla usuarios en la consulta; $rol es SIEMPRE una constante del
     * código (nunca texto del usuario). Sin la migración devuelve '' (solo cuenta el rol principal).
     */
    public static function sqlAdicional(PDO $pdo, string $alias, string $rol): string
    {
        if (!self::disponible($pdo) || !preg_match('/^[a-z_]+$/', $rol) || !preg_match('/^[a-z_]+$/', $alias)) {
            return '';
        }
        return " OR EXISTS (SELECT 1 FROM usuario_roles urx INNER JOIN roles rx ON rx.id = urx.rol_id WHERE urx.usuario_id = {$alias}.id AND rx.nombre = '{$rol}')";
    }

    /**
     * ¿Esta persona ya tiene cuenta? Se busca primero por DOCUMENTO en el colegio (es lo que identifica a la
     * persona) y luego por correo. Devuelve:
     *   ['cuenta' => fila, 'por' => 'documento'|'correo']   si existe,
     *   ['conflicto' => mensaje]                             si el documento y el correo son de cuentas distintas,
     *   []                                                   si es alguien nuevo.
     */
    public static function cuentaDeLaPersona(PDO $pdo, int $colegioId, string $correo, string $documento): array
    {
        $porDoc = null;
        if ($documento !== '' && Usuario::documentoValido($documento)) {
            foreach (Usuario::buscarPorDocumento($pdo, $documento) as $c) {
                if ((int) $c['colegio_id'] === $colegioId) { $porDoc = $c; break; }
            }
        }
        $porCorreo = $correo !== '' ? (Usuario::buscarPorCorreo($pdo, $correo) ?: null) : null;
        if ($porDoc && $porCorreo && (int) $porDoc['id'] !== (int) $porCorreo['id']) {
            return ['conflicto' => "El documento ya es de la cuenta de {$porDoc['nombre']} y el correo es de la cuenta de {$porCorreo['nombre']}. Revisa los datos: deberían ser de la misma persona."];
        }
        if ($porDoc) {
            return ['cuenta' => $porDoc, 'por' => 'documento'];
        }
        if ($porCorreo) {
            // Mismo correo pero otro documento: no se asume que sea la misma persona.
            $docCorreo = Usuario::normalizarDocumento((string) ($porCorreo['documento'] ?? ''));
            if ($documento !== '' && $docCorreo !== '' && $docCorreo !== $documento) {
                return ['conflicto' => "El correo ya es de la cuenta de {$porCorreo['nombre']}, que tiene otro número de documento. Usa un correo propio para esta persona."];
            }
            return ['cuenta' => $porCorreo, 'por' => 'correo'];
        }
        return [];
    }

    /**
     * Une dos cuentas de la MISMA persona en el mismo colegio (por ejemplo, una de familia y otra de docente
     * creadas antes de existir el multirol). Se conserva $conservarId (su correo y su contraseña); todo lo de
     * $eliminarId pasa a ella: roles, estudiantes vinculados, grupos, mensajes, registros y autorías.
     * Recorre las llaves foráneas que apuntan a usuarios y las columnas de autoría sin llave foránea.
     * Devuelve '' si quedó bien o el motivo por el que no se hizo.
     */
    public static function unirCuentas(PDO $pdo, int $conservarId, int $eliminarId, ?int $porId = null): string
    {
        if ($conservarId === $eliminarId) {
            return 'Es la misma cuenta.';
        }
        if (!self::disponible($pdo)) {
            return 'Para unir cuentas falta aplicar la migración sql/migraciones/044_multirol.sql.';
        }
        $q = $pdo->prepare('SELECT u.id, u.colegio_id, u.documento, u.telefono, r.nombre AS rol FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id WHERE u.id IN (?, ?)');
        $q->execute([$conservarId, $eliminarId]);
        $filas = [];
        foreach ($q->fetchAll() as $f) { $filas[(int) $f['id']] = $f; }
        if (count($filas) !== 2) {
            return 'Alguna de las dos cuentas ya no existe.';
        }
        [$a, $b] = [$filas[$conservarId], $filas[$eliminarId]];
        if ((int) $a['colegio_id'] !== (int) $b['colegio_id'] || $a['colegio_id'] === null) {
            return 'Solo se pueden unir cuentas del mismo colegio.';
        }
        foreach ([$a, $b] as $f) {
            if (!in_array($f['rol'], self::COMBINABLES, true)) {
                return 'Las cuentas de estudiante y de plataforma no se pueden unir.';
            }
        }
        $rolesB = self::roles($pdo, $eliminarId);

        // Columnas que apuntan a usuarios.id: las de llave foránea + las de autoría sin llave foránea.
        $cols = $pdo->query("SELECT TABLE_NAME AS t, COLUMN_NAME AS c FROM information_schema.KEY_COLUMN_USAGE
                              WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'usuarios' AND REFERENCED_COLUMN_NAME = 'id'
                             UNION
                             SELECT c.TABLE_NAME, c.COLUMN_NAME FROM information_schema.COLUMNS c
                              WHERE c.TABLE_SCHEMA = DATABASE() AND c.TABLE_NAME <> 'usuarios' AND c.DATA_TYPE IN ('int','bigint')
                                AND c.COLUMN_NAME REGEXP '(_por|usuario_id|responsable_id|autor_id|creador_id)$'")->fetchAll();

        $pdo->beginTransaction();
        try {
            foreach ($cols as $col) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $col['t'] . $col['c']) || $col['t'] === 'usuarios') {
                    continue;
                }
                $t = '`' . $col['t'] . '`';
                $c = '`' . $col['c'] . '`';
                // IGNORE: si la cuenta que queda ya tenía ese mismo vínculo (llave única), se descarta el repetido.
                $pdo->prepare("UPDATE IGNORE {$t} SET {$c} = ? WHERE {$c} = ?")->execute([$conservarId, $eliminarId]);
                $pdo->prepare("DELETE FROM {$t} WHERE {$c} = ?")->execute([$eliminarId]);
            }
            foreach ($rolesB as $rol) {
                $err = self::agregar($pdo, $conservarId, $rol, $porId);
                if ($err !== '') {
                    throw new RuntimeException($err);
                }
            }
            if (($a['documento'] ?? '') === '' && ($b['documento'] ?? '') !== '') {
                $pdo->prepare('UPDATE usuarios SET documento = ? WHERE id = ?')->execute([$b['documento'], $conservarId]);
            }
            if (($a['telefono'] ?? '') === '' && ($b['telefono'] ?? '') !== '') {
                $pdo->prepare('UPDATE usuarios SET telefono = ? WHERE id = ?')->execute([$b['telefono'], $conservarId]);
            }
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$eliminarId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('unirCuentas: ' . $e->getMessage());
            return 'No se pudieron unir las cuentas: ' . $e->getMessage();
        }
        return '';
    }

    /** Nombres para mostrar. */
    public const NOMBRES = [
        'administrador' => ['⚙️', 'Administrador', 'Gestión del colegio'],
        'rector' => ['🏛️', 'Rector', 'Vista institucional'],
        'coordinador' => ['📋', 'Coordinador', 'Convivencia, asistencia y horarios'],
        'secretaria_academica' => ['🗂️', 'Secretaría académica', 'Inscripciones, matrículas, boletines y SIMAT'],
        'psicologo' => ['🧠', 'Psicólogo/a', 'Atención y seguimiento'],
        'orientador' => ['🧭', 'Orientador/a', 'Alertas y grupos'],
        'docente' => ['📚', 'Docente', 'Clase, notas y pulso del grupo'],
        'acudiente' => ['🏡', 'Familia', 'Boletines, asistencia y horario de tus hijos'],
    ];
}
