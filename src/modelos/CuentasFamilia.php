<?php
declare(strict_types=1);

require_once __DIR__ . '/Usuario.php';
require_once __DIR__ . '/Academico.php';
require_once __DIR__ . '/RolesUsuario.php';

/**
 * Cuentas de familia creadas desde la hoja de vida (pestaña Familia).
 *
 * Madre, padre y acudiente reciben cada uno una cuenta con rol «acudiente», vinculada al estudiante
 * con su parentesco («Madre», «Padre», «Acudiente»). Reglas:
 *   - Misma persona = mismo número de documento o mismo correo. Si el acudiente es la misma persona
 *     que la madre o el padre, NO se crea otra cuenta: la de la madre/padre queda como
 *     «Madre y acudiente» (o «Padre y acudiente»).
 *   - La madre y el padre no pueden ser la misma persona (se rechaza al guardar).
 *   - Si la persona ya tiene cuenta de acudiente en el colegio (por ejemplo, por un hermano),
 *     se reutiliza y solo se agrega el vínculo con este estudiante.
 *   - Si el correo o el documento pertenecen a una cuenta de otro rol (un docente que también es
 *     padre), no se toca esa cuenta: se avisa para usar otro correo.
 *   - No se crea cuenta para quien está registrado como fallecido («¿Vive? = No») ni sin
 *     correo ni documento (no habría con qué iniciar sesión).
 *   - Sin correo pero con documento: la cuenta usa un correo interno y se entra con el documento.
 */
final class CuentasFamilia
{
    public const ETIQUETAS = ['madre' => 'Madre', 'padre' => 'Padre', 'acudiente' => 'Acudiente'];

    /** Documento normalizado y correo en minúscula de un registro de `familiares`. */
    public static function identidad(array $f): array
    {
        $doc = Usuario::normalizarDocumento((string) ($f['numero_documento'] ?? ''));
        $correo = strtolower(trim((string) ($f['email'] ?? '')));
        $nombre = trim(preg_replace('/\s+/', ' ', ($f['nombre1'] ?? '') . ' ' . ($f['nombre2'] ?? '') . ' ' . ($f['apellido1'] ?? '') . ' ' . ($f['apellido2'] ?? '')) ?? '');
        return [
            'doc' => Usuario::documentoValido($doc) ? $doc : '',
            'correo' => filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '',
            'nombre' => $nombre,
        ];
    }

    public static function mismaPersona(array $a, array $b): bool
    {
        $ia = self::identidad($a);
        $ib = self::identidad($b);
        // Con documento en ambos lados, manda el documento: dos personas pueden compartir el correo de la casa.
        if ($ia['doc'] !== '' && $ib['doc'] !== '') {
            return $ia['doc'] === $ib['doc'];
        }
        return $ia['correo'] !== '' && $ia['correo'] === $ib['correo'];
    }

    /** «la madre», «el padre», «el acudiente» (o varios: «la madre y el acudiente»). */
    private static function conArticulo(array $grupo): string
    {
        $art = ['madre' => 'la madre', 'padre' => 'el padre', 'acudiente' => 'el acudiente'];
        $p = array_map(static fn (string $x): string => $art[$x], $grupo);
        return count($p) > 1 ? implode(', ', array_slice($p, 0, -1)) . ' y ' . end($p) : $p[0];
    }

    /**
     * Validación ANTES de guardar un familiar: madre y padre no pueden ser la misma persona.
     * Devuelve '' si está bien o el mensaje de error.
     */
    public static function validarAntesDeGuardar(PDO $pdo, int $estudianteId, string $parentesco, array $post): string
    {
        if (!in_array($parentesco, ['madre', 'padre'], true)) {
            return '';
        }
        $otro = $parentesco === 'madre' ? 'padre' : 'madre';
        $fams = HojaVida::familiares($pdo, $estudianteId);
        if (isset($fams[$otro]) && self::mismaPersona($post, $fams[$otro])) {
            return ($parentesco === 'madre' ? 'La madre' : 'El padre') . ' tiene el mismo documento o correo que '
                . ($otro === 'madre' ? 'la madre' : 'el padre') . '. Revisa los datos: no pueden ser la misma persona.';
        }
        return '';
    }

    /** Parentescos del estudiante que corresponden a la misma persona que $parentesco (incluido él). */
    private static function grupoDePersona(array $fams, string $parentesco): array
    {
        $grupo = [$parentesco];
        foreach (array_keys(self::ETIQUETAS) as $p) {
            if ($p !== $parentesco && isset($fams[$p]) && self::mismaPersona($fams[$parentesco], $fams[$p])) {
                $grupo[] = $p;
            }
        }
        // Orden fijo: Madre, Padre, Acudiente
        return array_values(array_filter(array_keys(self::ETIQUETAS), static fn (string $p): bool => in_array($p, $grupo, true)));
    }

    /** «Madre», «Padre y acudiente»… (cabe en acudientes_estudiantes.parentesco, VARCHAR(30)). */
    private static function etiqueta(array $grupo): string
    {
        $partes = array_map(static fn (string $p): string => self::ETIQUETAS[$p], $grupo);
        $txt = count($partes) > 1 ? implode(', ', array_slice($partes, 0, -1)) . ' y ' . mb_strtolower(end($partes)) : $partes[0];
        return mb_substr($txt, 0, 30);
    }

    /**
     * Crea o vincula la cuenta del familiar recién guardado.
     *
     * @return array{mensaje: string, credenciales: ?array}
     */
    public static function sincronizar(PDO $pdo, int $colegioId, int $estudianteId, string $parentesco): array
    {
        $fams = HojaVida::familiares($pdo, $estudianteId);
        $f = $fams[$parentesco] ?? null;
        $etqSola = self::ETIQUETAS[$parentesco] ?? 'Familiar';
        if ($f === null) {
            return ['mensaje' => '', 'credenciales' => null];
        }
        if (($f['vive'] ?? '') === 'N') {
            return ['mensaje' => "No se crea cuenta para {$etqSola}: está registrado(a) como fallecido(a).", 'credenciales' => null];
        }
        $id = self::identidad($f);
        if ($id['nombre'] === '' || ($id['doc'] === '' && $id['correo'] === '')) {
            return ['mensaje' => "Para crear la cuenta de {$etqSola} faltan el nombre y un correo o número de documento.", 'credenciales' => null];
        }

        $grupo = self::grupoDePersona($fams, $parentesco);
        $etiqueta = self::etiqueta($grupo);
        $avisoMisma = '';
        if (count($grupo) > 1) {
            $otros = array_values(array_diff($grupo, [$parentesco]));
            $avisoMisma = ' Es la misma persona que ' . self::conArticulo($otros)
                . ': se usa una sola cuenta, marcada como «' . $etiqueta . '».';
        }

        // ¿Ya tiene cuenta EN ESTE COLEGIO? Primero por documento (identifica a la persona), luego por correo.
        // Si el correo es de una cuenta de OTRA institución, se crea la de aquí con un correo interno y la
        // persona entra con su documento: al iniciar sesión elige a qué institución entrar.
        $cuenta = null;
        $correoDeOtraInstitucion = false;
        $persona = RolesUsuario::cuentaDeLaPersona($pdo, $colegioId, $id['correo'], $id['doc']);
        if (!empty($persona['conflicto'])) {
            return ['mensaje' => "No se creó la cuenta de {$etqSola}: " . $persona['conflicto'], 'credenciales' => null];
        }
        if (!empty($persona['cuenta'])) {
            if ((int) $persona['cuenta']['colegio_id'] === $colegioId) {
                $cuenta = $persona['cuenta'];
            } else {
                $correoDeOtraInstitucion = true;
            }
        }
        // Una cuenta ya vinculada a este estudiante para la misma persona (otro parentesco del grupo).
        if ($cuenta === null) {
            foreach (self::cuentasDeEstudiante($pdo, $estudianteId) as $c) {
                if (self::cuentaEsDe($c, $id)) { $cuenta = Usuario::buscarPorCorreo($pdo, (string) $c['correo']) ?: null; break; }
            }
        }

        $qr = $pdo->prepare('SELECT id FROM roles WHERE nombre = ?');
        $qr->execute(['acudiente']);
        $rolAcud = (int) $qr->fetchColumn();
        if ($rolAcud === 0) {
            return ['mensaje' => 'Falta el rol «acudiente»: aplica la migración 034_academico_base.sql.', 'credenciales' => null];
        }

        $credenciales = null;
        if ($cuenta !== null) {
            $multirol = '';
            if ((int) $cuenta['rol_id'] !== $rolAcud) {
                // Es personal del colegio (p. ej. un docente que también es padre): se le suma el rol de
                // familia a SU MISMA cuenta. Al entrar elegirá con qué rol ingresar.
                $errRol = RolesUsuario::agregar($pdo, (int) $cuenta['id'], 'acudiente');
                if ($errRol !== '') {
                    return ['mensaje' => "No se pudo dar el rol de familia a {$cuenta['nombre']}: {$errRol}", 'credenciales' => null];
                }
                $multirol = " {$cuenta['nombre']} ya tenía cuenta en Serena: se le agregó el rol de familia y, al entrar, elegirá con qué rol ingresar.";
            }
            $docCuenta = Usuario::normalizarDocumento((string) ($cuenta['documento'] ?? ''));
            if ($id['doc'] !== '' && $docCuenta !== '' && $docCuenta !== $id['doc']) {
                return ['mensaje' => "No se creó la cuenta de {$etqSola}: su correo ya lo usa la cuenta de {$cuenta['nombre']}, que tiene otro documento. "
                    . 'Registra un correo propio para esta persona (o déjalo vacío y entrará con su documento).', 'credenciales' => null];
            }
            $cuentaId = (int) $cuenta['id'];
            $mensaje = "Cuenta de {$etqSola} vinculada (ya existía: {$cuenta['correo']})." . $multirol;
            if (count($grupo) > 1) {
                // El acudiente (o la madre/el padre) es alguien que ya tiene la cuenta: no se crea otra.
                $mensaje = '';
                $avisoMisma = "{$etqSola} es la misma persona que " . self::conArticulo(array_values(array_diff($grupo, [$parentesco])))
                    . ': no se crea otra cuenta; su cuenta queda como «' . $etiqueta . '».';
            }
        } else {
            if ($correoDeOtraInstitucion && $id['doc'] === '') {
                return ['mensaje' => "El correo de {$etqSola} ya lo usa una cuenta de otra institución. Registra su número de documento "
                    . '(entrará con él y elegirá la institución) o un correo distinto.', 'credenciales' => null];
            }
            $correo = ($id['correo'] !== '' && !$correoDeOtraInstitucion) ? $id['correo'] : 'acu.' . strtolower($id['doc']) . '.' . $colegioId . '@serena.local';
            if (Usuario::correoExiste($pdo, $correo)) {
                return ['mensaje' => "No se pudo crear la cuenta de {$etqSola}: el correo {$correo} ya está en uso.", 'credenciales' => null];
            }
            $clave = Academico::contrasenaTemporal();
            $pdo->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, telefono, consentimiento_datos, fecha_consentimiento, fecha_registro)
                           VALUES (?,?,?,?,?,?,1,NOW(),NOW())')
                ->execute([mb_substr($id['nombre'], 0, 100), $correo, password_hash($clave, PASSWORD_DEFAULT), $rolAcud, $colegioId,
                           mb_substr((string) (($f['celular'] ?? '') !== '' && $f['celular'] !== null ? $f['celular'] : ($f['telefono'] ?? '')), 0, 20) ?: null]);
            $cuentaId = (int) $pdo->lastInsertId();
            $docGuardado = '';
            if ($id['doc'] !== '' && !Usuario::documentoEnUso($pdo, $id['doc'], $colegioId, $cuentaId)) {
                Usuario::guardarDocumento($pdo, $cuentaId, $id['doc']);
                $docGuardado = $id['doc'];
            }
            $credenciales = ['etiqueta' => $etiqueta, 'nombre' => $id['nombre'], 'correo' => $correo, 'documento' => $docGuardado,
                             'password' => $clave, 'correo_interno' => str_ends_with($correo, '@serena.local')];
            $mensaje = "Cuenta de {$etiqueta} creada.";
        }

        $pdo->prepare('INSERT INTO acudientes_estudiantes (acudiente_id, estudiante_id, parentesco) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE parentesco = VALUES(parentesco)')
            ->execute([$cuentaId, $estudianteId, $etiqueta]);

        self::recalcularEtiquetas($pdo, $estudianteId);
        return ['mensaje' => trim($mensaje . ' ' . $avisoMisma), 'credenciales' => $credenciales];
    }

    /** ¿La cuenta corresponde a la persona con esa identidad (mismo documento o correo)? */
    private static function cuentaEsDe(array $cuenta, array $id): bool
    {
        $doc = Usuario::normalizarDocumento((string) ($cuenta['documento'] ?? ''));
        return ($id['doc'] !== '' && $doc === $id['doc'])
            || ($id['correo'] !== '' && strtolower((string) $cuenta['correo']) === $id['correo']);
    }

    /**
     * Vuelve a calcular el parentesco de cada cuenta vinculada según la hoja de vida actual: si el acudiente
     * deja de ser la misma persona que la madre, la cuenta de la madre pasa de «Madre y acudiente» a «Madre».
     * Las cuentas que ya no coinciden con ningún familiar (vinculadas por inscripción, por ejemplo) no se tocan.
     */
    public static function recalcularEtiquetas(PDO $pdo, int $estudianteId): void
    {
        $fams = HojaVida::familiares($pdo, $estudianteId);
        $upd = $pdo->prepare('UPDATE acudientes_estudiantes SET parentesco = ? WHERE acudiente_id = ? AND estudiante_id = ?');
        foreach (self::cuentasDeEstudiante($pdo, $estudianteId) as $c) {
            $grupo = [];
            foreach (array_keys(self::ETIQUETAS) as $p) {
                if (isset($fams[$p]) && self::cuentaEsDe($c, self::identidad($fams[$p]))) { $grupo[] = $p; }
            }
            if ($grupo) {
                $upd->execute([self::etiqueta($grupo), (int) $c['id'], $estudianteId]);
            }
        }
    }

    /** Cuentas de familia vinculadas al estudiante (para mostrarlas en la pestaña Familia). */
    public static function cuentasDeEstudiante(PDO $pdo, int $estudianteId): array
    {
        // u.* para no depender de la columna «documento» (migración 043): si falta, simplemente no viene.
        $q = $pdo->prepare('SELECT u.*, ae.parentesco
                              FROM acudientes_estudiantes ae INNER JOIN usuarios u ON u.id = ae.acudiente_id
                             WHERE ae.estudiante_id = ? ORDER BY ae.parentesco, u.nombre');
        $q->execute([$estudianteId]);
        return $q->fetchAll();
    }
}
