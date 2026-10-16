<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/totp.php';

// Verificación en 2 pasos: TOTP (app autenticadora) + códigos de recuperación.
//
// Códigos de recuperación:
//  - Se crean en lotes de 10, con formato XXXX-XXXX.
//  - Cada código sirve UNA sola vez.
//  - El lote vence 30 días después de crearse.
//  - Cuando se gasta el último código o el lote vence, se crea un lote nuevo en el
//    siguiente inicio de sesión exitoso (renovarSiHaceFalta) y se muestra una vez.
//  - En la base solo se guarda el hash bcrypt; el texto en claro no se puede recuperar.
class VerificacionDosPasos
{
    public const CODIGOS_POR_LOTE = 10;
    public const DIAS_VIGENCIA = 30;
    private const ALFABETO_CODIGOS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O/1/I

    private static function ahora(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    // ---------- Activación / desactivación ----------

    public static function estaActivo(array $usuario): bool
    {
        return (int) ($usuario['totp_activo'] ?? 0) === 1;
    }

    /**
     * true si CUALQUIERA de los 4 métodos de 2 pasos (app, correo, WhatsApp o
     * rostro) está activo. Los códigos de recuperación son compartidos entre los
     * cuatro (solo uno está activo a la vez), así que casi toda la lógica de
     * "¿tiene 2FA?" en seguridad.php y verificacion_2pasos.php usa esto, no
     * estaActivo() a solas (que solo mira TOTP).
     */
    public static function cualquierMetodoActivo(array $usuario): bool
    {
        return self::estaActivo($usuario)
            || (int) ($usuario['correo_2fa_activo'] ?? 0) === 1
            || (int) ($usuario['whatsapp_2fa_activo'] ?? 0) === 1
            || (int) ($usuario['rostro_2fa_activo'] ?? 0) === 1;
    }

    /** Crea un secreto nuevo (pendiente de confirmar) y lo devuelve en base32. */
    public static function iniciarActivacion(PDO $pdo, int $usuarioId): string
    {
        $secreto = totpGenerarSecreto();
        $pdo->prepare(
            'UPDATE usuarios SET totp_secreto=:s, totp_activo=0, totp_ultimo_paso=NULL, totp_activado_en=NULL
             WHERE id=:id AND totp_activo=0'
        )->execute(['s' => totpCifrarSecreto($secreto), 'id' => $usuarioId]);
        return $secreto;
    }

    /** Secreto de una activación empezada y aún sin confirmar; null si no hay. */
    public static function secretoPendiente(PDO $pdo, int $usuarioId): ?string
    {
        $c = $pdo->prepare('SELECT totp_secreto FROM usuarios WHERE id=:id AND totp_activo=0 LIMIT 1');
        $c->execute(['id' => $usuarioId]);
        $almacenado = $c->fetchColumn();
        if (!$almacenado) {
            return null;
        }
        return totpDescifrarSecreto((string) $almacenado);
    }

    public static function cancelarActivacion(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare('UPDATE usuarios SET totp_secreto=NULL, totp_ultimo_paso=NULL WHERE id=:id AND totp_activo=0')
            ->execute(['id' => $usuarioId]);
    }

    /**
     * Confirma la activación con el primer código de la app. Si es correcto deja la
     * verificación activa, crea el primer lote de códigos de recuperación y lo
     * devuelve (['codigos' => [...], 'expira_en' => '...']). Si no, devuelve null.
     */
    public static function confirmarActivacion(PDO $pdo, int $usuarioId, string $codigo): ?array
    {
        $secreto = self::secretoPendiente($pdo, $usuarioId);
        if ($secreto === null) {
            return null;
        }
        $paso = totpVerificar($secreto, $codigo, null);
        if ($paso === null) {
            return null;
        }
        $c = $pdo->prepare(
            'UPDATE usuarios SET totp_activo=1, totp_ultimo_paso=:p, totp_activado_en=:ahora,
                    correo_2fa_activo=0, correo_2fa_activado_en=NULL,
                    whatsapp_2fa_activo=0, whatsapp_2fa_activado_en=NULL,
                    rostro_2fa_plantillas=NULL, rostro_2fa_activo=0, rostro_2fa_activado_en=NULL, rostro_2fa_consentimiento_en=NULL,
                    intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
             WHERE id=:id AND totp_activo=0'
        );
        $c->execute(['p' => $paso, 'ahora' => self::ahora(), 'id' => $usuarioId]);
        if ($c->rowCount() !== 1) {
            return null;
        }
        // Un usuario solo tiene un método de 2FA a la vez: si venía de correo,
        // WhatsApp o rostro, se limpian sus códigos vivos (ya sin uso, quedaron huérfanos).
        $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
        return self::generarLote($pdo, $usuarioId);
    }

    public static function desactivar(PDO $pdo, int $usuarioId): void
    {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE usuarios SET totp_secreto=NULL, totp_activo=0, totp_ultimo_paso=NULL,
                        totp_activado_en=NULL, intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
                 WHERE id=:id'
            )->execute(['id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_recuperacion_2fa WHERE usuario_id=:id')
                ->execute(['id' => $usuarioId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ---------- Verificación de códigos ----------

    /** Código de 6 dígitos de la app. $usuario debe venir recién leído de la base. */
    public static function verificarCodigoApp(PDO $pdo, array $usuario, string $codigo): bool
    {
        if (!self::estaActivo($usuario) || empty($usuario['totp_secreto'])) {
            return false;
        }
        $secreto = totpDescifrarSecreto((string) $usuario['totp_secreto']);
        if ($secreto === null) {
            return false;
        }
        $ultimo = $usuario['totp_ultimo_paso'] !== null ? (int) $usuario['totp_ultimo_paso'] : null;
        $paso = totpVerificar($secreto, $codigo, $ultimo);
        if ($paso === null) {
            return false;
        }
        // Se "gasta" el paso de forma atómica: si dos peticiones llegan a la vez con
        // el mismo código, solo una de las dos logra el UPDATE.
        $c = $pdo->prepare(
            'UPDATE usuarios SET totp_ultimo_paso=:p
             WHERE id=:id AND (totp_ultimo_paso IS NULL OR totp_ultimo_paso < :p2)'
        );
        $c->execute(['p' => $paso, 'p2' => $paso, 'id' => (int) $usuario['id']]);
        return $c->rowCount() === 1;
    }

    public static function normalizarCodigoRecuperacion(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }

    /** Verifica y GASTA un código de recuperación. true si era válido, no usado y no vencido. */
    public static function consumirCodigoRecuperacion(PDO $pdo, int $usuarioId, string $codigo): bool
    {
        $codigo = self::normalizarCodigoRecuperacion($codigo);
        if (strlen($codigo) !== 8) {
            return false;
        }
        $c = $pdo->prepare(
            'SELECT id, codigo_hash FROM codigos_recuperacion_2fa
             WHERE usuario_id=:u AND usado_en IS NULL AND expira_en > :ahora'
        );
        $c->execute(['u' => $usuarioId, 'ahora' => self::ahora()]);
        $encontrado = null;
        foreach ($c->fetchAll() as $fila) {
            if (password_verify($codigo, $fila['codigo_hash'])) {
                $encontrado = (int) $fila['id'];
            }
        }
        if ($encontrado === null) {
            return false;
        }
        $gastar = $pdo->prepare('UPDATE codigos_recuperacion_2fa SET usado_en=:ahora WHERE id=:id AND usado_en IS NULL');
        $gastar->execute(['ahora' => self::ahora(), 'id' => $encontrado]);
        return $gastar->rowCount() === 1;
    }

    /** Acepta indistintamente el código de la app (6 dígitos) o uno de recuperación. */
    public static function verificarCualquiera(PDO $pdo, array $usuario, string $codigo): bool
    {
        $soloDigitos = preg_replace('/\s+/', '', $codigo);
        if (preg_match('/^\d{6}$/', $soloDigitos)) {
            return self::verificarCodigoApp($pdo, $usuario, $soloDigitos);
        }
        return self::consumirCodigoRecuperacion($pdo, (int) $usuario['id'], $codigo);
    }

    // ---------- Códigos de recuperación: lotes ----------

    private static function generarCodigoEnClaro(): string
    {
        $limite = strlen(self::ALFABETO_CODIGOS) - 1;
        $texto = '';
        for ($i = 0; $i < 8; $i++) {
            $texto .= self::ALFABETO_CODIGOS[random_int(0, $limite)];
        }
        return substr($texto, 0, 4) . '-' . substr($texto, 4);
    }

    /**
     * Reemplaza TODOS los códigos de la persona por un lote nuevo de 10 que vence en
     * 30 días. Devuelve los códigos en claro: es la única vez que existen así.
     */
    public static function generarLote(PDO $pdo, int $usuarioId): array
    {
        $creado = new DateTimeImmutable('now');
        $expira = $creado->modify('+' . self::DIAS_VIGENCIA . ' days');

        $codigos = [];
        while (count($codigos) < self::CODIGOS_POR_LOTE) {
            $codigos[self::generarCodigoEnClaro()] = true; // clave = evita repetidos dentro del lote
        }
        $codigos = array_keys($codigos);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM codigos_recuperacion_2fa WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $insertar = $pdo->prepare(
                'INSERT INTO codigos_recuperacion_2fa (usuario_id, codigo_hash, creado_en, expira_en)
                 VALUES (:u, :h, :c, :e)'
            );
            foreach ($codigos as $codigo) {
                $insertar->execute([
                    'u' => $usuarioId,
                    'h' => password_hash(self::normalizarCodigoRecuperacion($codigo), PASSWORD_DEFAULT),
                    'c' => $creado->format('Y-m-d H:i:s'),
                    'e' => $expira->format('Y-m-d H:i:s'),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['codigos' => $codigos, 'expira_en' => $expira->format('Y-m-d H:i:s')];
    }

    /**
     * Situación de los códigos: disponibles, usados, vencimiento y días restantes.
     * 'hay_lote' es false si la persona no tiene ningún código guardado.
     */
    public static function estadoCodigos(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare('SELECT usado_en, expira_en FROM codigos_recuperacion_2fa WHERE usuario_id=:u');
        $c->execute(['u' => $usuarioId]);
        $filas = $c->fetchAll();

        $ahora = time();
        $estado = [
            'hay_lote' => count($filas) > 0,
            'total' => count($filas),
            'disponibles' => 0,
            'usados' => 0,
            'vencido' => false,
            'expira_en' => null,
            'dias_restantes' => 0,
        ];
        $expiraMax = 0;
        foreach ($filas as $fila) {
            $expira = strtotime($fila['expira_en']);
            $expiraMax = max($expiraMax, $expira);
            if ($fila['usado_en'] !== null) {
                $estado['usados']++;
            } elseif ($expira > $ahora) {
                $estado['disponibles']++;
            }
        }
        if ($expiraMax > 0) {
            $estado['expira_en'] = date('Y-m-d H:i:s', $expiraMax);
            $estado['vencido'] = $expiraMax <= $ahora;
            $estado['dias_restantes'] = $estado['vencido'] ? 0 : (int) ceil(($expiraMax - $ahora) / 86400);
        }
        return $estado;
    }

    /**
     * Si ya no queda ningún código utilizable (se gastaron todos o el lote venció),
     * crea un lote nuevo y lo devuelve junto con el motivo: 'agotados', 'vencidos' o
     * 'nuevos' (no había ninguno). Si todavía quedan códigos válidos, devuelve null.
     */
    public static function renovarSiHaceFalta(PDO $pdo, int $usuarioId): ?array
    {
        $estado = self::estadoCodigos($pdo, $usuarioId);
        if ($estado['disponibles'] > 0) {
            return null;
        }
        if (!$estado['hay_lote']) {
            $motivo = 'nuevos';
        } elseif ($estado['vencido'] && $estado['usados'] < $estado['total']) {
            $motivo = 'vencidos';
        } else {
            $motivo = 'agotados';
        }
        $lote = self::generarLote($pdo, $usuarioId);
        $lote['motivo'] = $motivo;
        return $lote;
    }
}
