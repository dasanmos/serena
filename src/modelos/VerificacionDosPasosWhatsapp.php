<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/whatsapp.php';
require_once __DIR__ . '/../helpers/totp.php'; // cifrado de la apikey (totpCifrarSecreto)
require_once __DIR__ . '/VerificacionDosPasos.php'; // generarLote() de códigos de recuperación

// Verificación en 2 pasos por WhatsApp: código de 6 dígitos enviado vía la API
// gratuita de CallMeBot. Alternativa a TOTP y al 2FA por correo — comparten el
// mismo contador de intentos fallidos/bloqueo (intentos_2fa_fallidos,
// bloqueo_2fa_hasta en usuarios), pero cada usuario solo tiene UN método de 2FA
// activo a la vez.
//
// A diferencia de TOTP y correo, este método necesita una configuración previa
// por usuario (su número + apikey personal de CallMeBot, ver seguridad.php → WhatsApp) antes
// de poder activarse — sin eso no hay a dónde enviar el código.
class VerificacionDosPasosWhatsapp
{
    // CallMeBot es un servicio gratuito sin SLA: los mensajes pueden tardar
    // hasta 1 minuto en llegar, así que el código vive más que el de correo.
    public const VIGENCIA_MINUTOS = 15;
    public const ESPERA_REENVIO_SEGUNDOS = 90;

    private static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    public static function estaActivo(array $usuario): bool
    {
        return (int) ($usuario['whatsapp_2fa_activo'] ?? 0) === 1;
    }

    /** true si el usuario ya guardó número + apikey de CallMeBot en su perfil. */
    public static function tieneCredencialesGuardadas(array $usuario): bool
    {
        return !empty($usuario['whatsapp_2fa_numero']) && !empty($usuario['whatsapp_2fa_apikey']);
    }

    // ---------- Credenciales personales (seguridad.php → WhatsApp) ----------

    /**
     * Guarda (o reemplaza) el número y la apikey personal de CallMeBot del usuario.
     * El número se normaliza ("+57 300 123 4567" → "+573001234567") y la apikey se
     * guarda CIFRADA: con ella cualquiera podría mandar mensajes a ese WhatsApp.
     */
    public static function guardarCredenciales(PDO $pdo, int $usuarioId, string $numero, string $apikey): bool
    {
        $numero = whatsappNormalizarNumero($numero);
        $apikey = trim($apikey);
        if (!whatsappNumeroValido($numero) || !whatsappApikeyValida($apikey)) {
            return false;
        }
        $pdo->prepare(
            'UPDATE usuarios SET whatsapp_2fa_numero=:n, whatsapp_2fa_apikey=:a WHERE id=:id'
        )->execute(['n' => $numero, 'a' => totpCifrarSecreto($apikey), 'id' => $usuarioId]);
        return true;
    }

    /** Apikey en claro (descifrada) del usuario, o null si no hay o no se puede leer. */
    private static function apikeyDe(array $usuario): ?string
    {
        if (empty($usuario['whatsapp_2fa_apikey'])) {
            return null;
        }
        return totpDescifrarSecreto((string) $usuario['whatsapp_2fa_apikey']);
    }

    public static function borrarCredenciales(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare(
            'UPDATE usuarios SET whatsapp_2fa_numero=NULL, whatsapp_2fa_apikey=NULL WHERE id=:id'
        )->execute(['id' => $usuarioId]);
    }

    // ---------- Generar y enviar ----------

    private static function generarCodigoEnClaro(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Crea (o reemplaza) el código vivo del usuario y lo envía por WhatsApp con
     * sus credenciales personales de CallMeBot. Devuelve el momento en que se
     * podrá reenviar, o null si el envío falló.
     */
    public static function generarYEnviar(PDO $pdo, array $usuario): ?DateTimeImmutable
    {
        if (!self::tieneCredencialesGuardadas($usuario)) {
            return null;
        }
        $apikey = self::apikeyDe($usuario);
        if ($apikey === null) {
            error_log('VerificacionDosPasosWhatsapp: no se pudo leer la apikey guardada (usuario ' . (int) $usuario['id'] . ').');
            return null;
        }
        $usuarioId = (int) $usuario['id'];
        $codigo = self::generarCodigoEnClaro();
        $creado = self::ahora();
        $expira = $creado->modify('+' . self::VIGENCIA_MINUTOS . ' minutes');

        $pdo->prepare(
            'INSERT INTO codigos_2fa_whatsapp (usuario_id, codigo_hash, creado_en, expira_en, usado_en)
             VALUES (:u, :h, :c, :e, NULL)
             ON DUPLICATE KEY UPDATE codigo_hash=:h2, creado_en=:c2, expira_en=:e2, usado_en=NULL'
        )->execute([
            'u' => $usuarioId,
            'h' => password_hash($codigo, PASSWORD_DEFAULT),
            'c' => $creado->format('Y-m-d H:i:s'),
            'e' => $expira->format('Y-m-d H:i:s'),
            'h2' => password_hash($codigo, PASSWORD_DEFAULT),
            'c2' => $creado->format('Y-m-d H:i:s'),
            'e2' => $expira->format('Y-m-d H:i:s'),
        ]);

        $texto = "*Serena*\nTu código de verificación es:\n\n*{$codigo}*\n\n"
            . 'Vence en ' . self::VIGENCIA_MINUTOS . " minutos y sirve una sola vez.\n"
            . 'Si no fuiste tú quien lo pidió, ignora este mensaje.';

        $enviado = enviarWhatsappCallMeBot((string) $usuario['whatsapp_2fa_numero'], $apikey, $texto);
        if (!$enviado) {
            error_log("VerificacionDosPasosWhatsapp: no se pudo enviar el código 2FA (usuario {$usuarioId}).");
            return null;
        }
        return $creado->modify('+' . self::ESPERA_REENVIO_SEGUNDOS . ' seconds');
    }

    /** Segundos que faltan para poder pedir un reenvío; 0 si ya se puede. */
    public static function segundosParaReenvio(PDO $pdo, int $usuarioId): int
    {
        $c = $pdo->prepare('SELECT creado_en FROM codigos_2fa_whatsapp WHERE usuario_id=:u LIMIT 1');
        $c->execute(['u' => $usuarioId]);
        $creadoEn = $c->fetchColumn();
        if (!$creadoEn) return 0;
        $puedeDesde = strtotime($creadoEn) + self::ESPERA_REENVIO_SEGUNDOS;
        $restante = $puedeDesde - time();
        return $restante > 0 ? $restante : 0;
    }

    // ---------- Verificación ----------

    /** Verifica y GASTA el código vivo del usuario. true si era válido, no usado y no vencido. */
    public static function verificarCodigo(PDO $pdo, int $usuarioId, string $codigo): bool
    {
        $codigo = preg_replace('/\s+/', '', $codigo);
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }
        $c = $pdo->prepare(
            'SELECT id, codigo_hash FROM codigos_2fa_whatsapp
             WHERE usuario_id=:u AND usado_en IS NULL AND expira_en > :ahora LIMIT 1'
        );
        $c->execute(['u' => $usuarioId, 'ahora' => self::ahora()->format('Y-m-d H:i:s')]);
        $fila = $c->fetch();
        if (!$fila || !password_verify($codigo, $fila['codigo_hash'])) {
            return false;
        }
        $gastar = $pdo->prepare('UPDATE codigos_2fa_whatsapp SET usado_en=:ahora WHERE id=:id AND usado_en IS NULL');
        $gastar->execute(['ahora' => self::ahora()->format('Y-m-d H:i:s'), 'id' => $fila['id']]);
        return $gastar->rowCount() === 1;
    }

    // ---------- Activación / desactivación ----------

    /**
     * Activa la verificación por WhatsApp (y desactiva TOTP/correo/rostro si estaban
     * activos — un usuario solo tiene un método de 2FA a la vez), limpia el
     * código vivo usado para confirmar, y genera el primer lote de 10 códigos de
     * recuperación (respaldo si CallMeBot falla, dado que es un servicio sin SLA).
     * Devuelve ['codigos' => [...], 'expira_en' => '...'].
     */
    public static function activar(PDO $pdo, int $usuarioId): ?array
    {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE usuarios SET whatsapp_2fa_activo=1, whatsapp_2fa_activado_en=:ahora,
                        totp_secreto=NULL, totp_activo=0, totp_ultimo_paso=NULL, totp_activado_en=NULL,
                        correo_2fa_activo=0, correo_2fa_activado_en=NULL,
                        rostro_2fa_plantillas=NULL, rostro_2fa_activo=0, rostro_2fa_activado_en=NULL, rostro_2fa_consentimiento_en=NULL,
                        intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
                 WHERE id=:id'
            )->execute(['ahora' => self::ahora()->format('Y-m-d H:i:s'), 'id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return VerificacionDosPasos::generarLote($pdo, $usuarioId);
    }

    /** Cancela una activación a medias: borra solo el código pendiente. */
    public static function cancelarActivacion(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
    }

    public static function desactivar(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare(
            'UPDATE usuarios SET whatsapp_2fa_activo=0, whatsapp_2fa_activado_en=NULL,
                    intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
             WHERE id=:id'
        )->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_recuperacion_2fa WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
    }
}
