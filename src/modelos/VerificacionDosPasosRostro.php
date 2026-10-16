<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/totp.php'; // cifrado AES-256-GCM (totpCifrarSecreto / totpDescifrarSecreto)
require_once __DIR__ . '/VerificacionDosPasos.php'; // generarLote() de códigos de recuperación

// Verificación en 2 pasos por reconocimiento facial.
//
// Cómo funciona:
//  - El NAVEGADOR calcula, con face-api.js, un "descriptor" del rostro: 128
//    números. La foto/video nunca sale del dispositivo ni se guarda.
//  - Al activar, se guardan 3 descriptores (3 capturas seguidas), cifrados con
//    AES-256-GCM en usuarios.rostro_2fa_plantillas.
//  - Al iniciar sesión, el navegador manda el descriptor del momento y ESTE
//    modelo lo compara con los guardados (distancia euclidiana). Si el más
//    parecido está por debajo de UMBRAL, pasa.
//  - Comparte el contador de intentos fallidos / bloqueo con los otros métodos
//    (intentos_2fa_fallidos, bloqueo_2fa_hasta), y un usuario solo tiene UN
//    método de 2FA activo a la vez.
//
// LÍMITES (importante): no hay detección de "vida" (liveness). Alguien con la
// contraseña de la víctima Y una foto nítida de su cara podría intentar
// engañarlo. Es un segundo factor razonable para una plataforma escolar, no
// una barrera bancaria. Los descriptores son datos biométricos, es decir datos
// sensibles (Ley 1581 de 2012): por eso se pide consentimiento explícito, se
// guardan cifrados y se borran al desactivar el método o al cambiar a otro.
class VerificacionDosPasosRostro
{
    public const DIMENSION = 128;   // tamaño del descriptor de face-api.js
    public const MUESTRAS = 3;      // capturas que se piden al activar
    // Distancia máxima para considerar "la misma cara". face-api.js usa 0.6 como
    // valor típico; 0.5 es más estricto (menos falsos positivos).
    public const UMBRAL = 0.5;

    private static function ahora(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    public static function estaActivo(array $usuario): bool
    {
        return (int) ($usuario['rostro_2fa_activo'] ?? 0) === 1;
    }

    // ---------- Utilidades de vectores ----------

    /** Decodifica y valida UN descriptor (array de 128 números finitos); null si no sirve. */
    private static function descriptorValido($valor): ?array
    {
        if (!is_array($valor) || count($valor) !== self::DIMENSION) {
            return null;
        }
        $vector = [];
        foreach ($valor as $n) {
            if (!is_int($n) && !is_float($n)) {
                return null;
            }
            $n = (float) $n;
            if (!is_finite($n) || abs($n) > 5.0) {
                return null;
            }
            $vector[] = $n;
        }
        return $vector;
    }

    /** Distancia euclidiana entre dos descriptores de la misma dimensión. */
    public static function distancia(array $a, array $b): float
    {
        $suma = 0.0;
        for ($i = 0; $i < self::DIMENSION; $i++) {
            $d = $a[$i] - $b[$i];
            $suma += $d * $d;
        }
        return sqrt($suma);
    }

    // ---------- Activación / desactivación ----------

    /**
     * Activa el reconocimiento facial con las MUESTRAS capturas que mandó el
     * navegador ($muestrasJson = "[[128 números], [128 números], [128 números]]").
     * Las capturas tienen que ser de la misma cara (distancias entre sí bajo el
     * umbral). Apaga cualquier otro método de 2FA y genera el primer lote de 10
     * códigos de recuperación (respaldo si la cámara falla o no reconoce a la
     * persona). Devuelve ['codigos' => [...], 'expira_en' => '...'], o null si
     * las capturas no sirvieron.
     */
    public static function activar(PDO $pdo, int $usuarioId, string $muestrasJson): ?array
    {
        $crudo = json_decode($muestrasJson, true);
        if (!is_array($crudo) || count($crudo) !== self::MUESTRAS) {
            return null;
        }
        $muestras = [];
        foreach ($crudo as $m) {
            $v = self::descriptorValido($m);
            if ($v === null) {
                return null;
            }
            $muestras[] = $v;
        }
        // Coherencia: las 3 capturas deben parecerse entre sí.
        for ($i = 0; $i < count($muestras); $i++) {
            for ($j = $i + 1; $j < count($muestras); $j++) {
                if (self::distancia($muestras[$i], $muestras[$j]) > self::UMBRAL) {
                    return null;
                }
            }
        }

        $cifrado = totpCifrarSecreto(json_encode($muestras));
        $ahora = self::ahora();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'UPDATE usuarios SET rostro_2fa_plantillas=:p, rostro_2fa_activo=1,
                        rostro_2fa_activado_en=:a, rostro_2fa_consentimiento_en=:c,
                        totp_secreto=NULL, totp_activo=0, totp_ultimo_paso=NULL, totp_activado_en=NULL,
                        correo_2fa_activo=0, correo_2fa_activado_en=NULL,
                        whatsapp_2fa_activo=0, whatsapp_2fa_activado_en=NULL,
                        intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
                 WHERE id=:id'
            )->execute(['p' => $cifrado, 'a' => $ahora, 'c' => $ahora, 'id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_correo WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->prepare('DELETE FROM codigos_2fa_whatsapp WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return VerificacionDosPasos::generarLote($pdo, $usuarioId);
    }

    /** Apaga el método, BORRA los descriptores guardados y los códigos de recuperación. */
    public static function desactivar(PDO $pdo, int $usuarioId): void
    {
        $pdo->prepare(
            'UPDATE usuarios SET rostro_2fa_plantillas=NULL, rostro_2fa_activo=0,
                    rostro_2fa_activado_en=NULL, rostro_2fa_consentimiento_en=NULL,
                    intentos_2fa_fallidos=0, bloqueo_2fa_hasta=NULL
             WHERE id=:id'
        )->execute(['id' => $usuarioId]);
        $pdo->prepare('DELETE FROM codigos_recuperacion_2fa WHERE usuario_id=:id')->execute(['id' => $usuarioId]);
    }

    // ---------- Verificación ----------

    /**
     * Compara el descriptor que mandó el navegador ($descriptorJson = "[128 números]")
     * con los guardados del usuario. $usuario debe venir recién leído de la base.
     */
    public static function verificar(array $usuario, string $descriptorJson): bool
    {
        if (!self::estaActivo($usuario) || empty($usuario['rostro_2fa_plantillas'])) {
            return false;
        }
        $actual = self::descriptorValido(json_decode($descriptorJson, true));
        if ($actual === null) {
            return false;
        }
        $claro = totpDescifrarSecreto((string) $usuario['rostro_2fa_plantillas']);
        if ($claro === null) {
            return false;
        }
        $guardadas = json_decode($claro, true);
        if (!is_array($guardadas)) {
            return false;
        }
        $mejor = null;
        foreach ($guardadas as $g) {
            $g = self::descriptorValido($g);
            if ($g === null) {
                continue;
            }
            $d = self::distancia($actual, $g);
            if ($mejor === null || $d < $mejor) {
                $mejor = $d;
            }
        }
        return $mejor !== null && $mejor <= self::UMBRAL;
    }
}
