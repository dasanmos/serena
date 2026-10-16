<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/correo.php';
require_once __DIR__ . '/VerificacionDosPasos.php'; // generarLote() de códigos de recuperación

// Verificación en 2 pasos por correo: código de 6 dígitos enviado al correo de
// la cuenta, vigente unos minutos. Alternativa a VerificacionDosPasos (TOTP con
// app autenticadora) — comparten el mismo contador de intentos fallidos/bloqueo
// (intentos_2fa_fallidos, bloqueo_2fa_hasta en usuarios), pero cada usuario solo
// tiene UN método de 2FA activo a la vez.
class VerificacionDosPasosCorreo
{
    public const VIGENCIA_MINUTOS = 10;
    // Antes de reenviar un código nuevo hay que esperar esto — evita que alguien
    // sature el correo de la persona a punta de clics en "Reenviar código".
    public const ESPERA_REENVIO_SEGUNDOS = 60;

    private static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }

    public static function estaActivo(array $usuario): bool
    {
        return (int) ($usuario['correo_2fa_activo'] ?? 0) === 1;
    }

    // ---------- Generar y enviar ----------

    private static function generarCodigoEnClaro(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Crea (o reemplaza) el código vivo del usuario y lo envía por correo.
     * Devuelve el momento en que se podrá reenviar, o null si el envío falló
     * (por ejemplo, Brevo no está configurado o el correo rebotó).
     */
    public static function generarYEnviar(PDO $pdo, int $usuarioId, string $correoDestino, string $asunto = 'Tu código de verificación de Serena'): ?DateTimeImmutable
    {
        $codigo = self::generarCodigoEnClaro();
        $creado = self::ahora();
        $expira = $creado->modify('+' . self::VIGENCIA_MINUTOS . ' minutes');

        $pdo->prepare(
            'INSERT INTO codigos_2fa_correo (usuario_id, codigo_hash, creado_en, expira_en, usado_en)
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

        $textoPlano = "Hola,\n\n"
            . "Tu código de verificación de Serena es:\n\n{$codigo}\n\n"
            . 'Vence en ' . self::VIGENCIA_MINUTOS . " minutos y sirve una sola vez.\n"
            . "Si no fuiste tú quien lo pidió, puedes ignorar este correo.\n\n"
            . '— Serena';

        $cuerpoHtml = '<p>Usa este código para completar el inicio de sesión en Serena:</p>'
            . '<p style="text-align:center;margin:28px 0;">'
            . '<span style="display:inline-block;font-family:Georgia, \'Times New Roman\', serif;font-size:32px;'
            . 'letter-spacing:8px;font-weight:bold;color:#2B2B2B;background:#F5F3EC;padding:14px 24px;border-radius:10px;">'
            . htmlspecialchars($codigo) . '</span></p>'
            . '<p style="font-size:13px;color:#6B6B6B">Vence en ' . self::VIGENCIA_MINUTOS . ' minutos y sirve una sola vez. '
            . 'Si no fuiste tú quien lo pidió, puedes ignorar este correo — tu cuenta sigue segura.</p>';
        $htmlCompleto = plantillaCorreoHtml('Tu código de verificación', $cuerpoHtml);

        $enviado = smtpConfigurado() ? enviarCorreo($correoDestino, $asunto, $textoPlano, $htmlCompleto) : false;
        if (!$enviado) {
            error_log("VerificacionDosPasosCorreo: no se pudo enviar el código 2FA a {$correoDestino} (usuario {$usuarioId}).");
            return null;
        }
        return $creado->modify('+' . self::ESPERA_REENVIO_SEGUNDOS . ' seconds');
    }

    /** Segundos que faltan para poder pedir un reenvío; 0 si ya se puede. */
    public static function segundosParaReenvio(PDO $pdo, int $usuarioId): int
    {
        $c = $pdo->prepare('SELECT creado_en FROM codigos_2fa_correo WHERE usuario_id=:u LIMIT 1');
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
            'SELECT id, codigo_hash FROM codigos_2fa_correo
             WHERE usuario_id=:u AND usado_en IS NULL AND expira_en > :ahora LIMIT 1'
        );
        $c->execute(['u' => $usuarioId, 'ahora' => self::ahora()->format('Y-m-d H:i:s')]);
        $fila = $c->fetch();
        if (!$fila || !password_verify($codigo, $fila['codigo_hash'])) {
            return false;
        }
        $gastar = $pdo->prepare('UPDATE codigos_2fa_correo SET usado_en=:ahora WHERE id=:id AND usado_en IS NULL');
        $gastar->execute(['ahora' => self::ahora()->format('Y-m-d H:i:s'), 'id' => $fila['id']]);
        return $gastar->rowCount() === 1;
    }

    // ---------- Activación / desactivación ----------

    /**
     * Activa la verificación por correo (y desactiva TOTP/WhatsApp/rostro si
     * estaban activos — un usuario solo tiene un método de 2FA a la vez), limpia
     * el código vivo usado para confirmar, y genera el primer lote de 10 códigos
     * de recuperación (igual que TOTP: son el respaldo si el correo falla).
     * Devuelve ['codigos' => [...], 'expira_en' => '...'], o null si la
     * activación en sí falló.
     */
    public static function activar(PDO $pdo, int $usuarioId): ?array
    {
        $pdo->beginTransaction();
        try {
            $c = $pdo->prepare(
                'UPDATE usuarios SET correo_2fa_activo=1, correo_2fa_activado_en=:ahora,
                        totp_secreto=NULL, totp_activo=0, totp_ultimo_paso=NULL, totp_activado_en=NULL,
                        whatsapp_2fa_activo=0, whatsapp_2fa_activado_en=NULL,
                        rostro_2fa_plantillas=NULL, rostro_2fa_activo=0, rostro_2fa_activado_en=NULL, rostro_2fa_consentimiento_en=NULL,
                        intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
                 WHERE id=:id'
            );
            $c->execute(['ahora' => self::ahora()->format('Y-m-d H:i:s'), 'id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        // generarLote() maneja su propia transacción (borra y crea el lote nuevo).
        return VerificacionDosPasos::generarLote($pdo, $usuarioId);
    }

    /** Cancela una activación a medias: borra solo el código pendiente. */
    public static function cancelarActivacion(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
    }

    public static function desactivar(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare(
            'UPDATE usuarios SET correo_2fa_activo=0, correo_2fa_activado_en=NULL,
                    intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
             WHERE id=:id'
        )->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_recuperacion_2fa WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
    }
}
