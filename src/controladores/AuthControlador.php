<?php
declare(strict_types=1);
class AuthControlador
{
    public static function login(PDO $pdo, string $identificador, string $password): array
    {
        // El campo acepta correo o número de documento. Mismo mensaje para todo fallo, exista o no la cuenta.
        $identificador = trim($identificador);
        $mensajeError = 'Correo, documento o contraseña incorrectos.';
        if (strpos($identificador, '@') !== false) {
            $usuario = Usuario::buscarPorCorreo($pdo, $identificador);
        } else {
            $usuario = null;
            $candidatos = Usuario::buscarPorDocumento($pdo, $identificador);
            if (count($candidatos) === 1) {
                $usuario = $candidatos[0];
            } elseif (count($candidatos) > 1) {
                // El mismo documento en más de un colegio: la contraseña decide cuál es, sin revelar que hay varias.
                foreach ($candidatos as $c) {
                    $restante = segundosBloqueoLogin($pdo, (int) $c['id']);
                    if ($restante > 0) {
                        $minutos = (int) ceil($restante / 60);
                        return ['exito' => false, 'mensaje' => "Demasiados intentos fallidos. Intenta de nuevo en {$minutos} minuto(s)."];
                    }
                }
                $coinciden = array_values(array_filter($candidatos, static fn (array $c): bool => password_verify($password, (string) $c['password_hash'])));
                if (!$coinciden) {
                    foreach ($candidatos as $c) {
                        registrarIntentoLoginFallido($pdo, (int) $c['id']);
                    }
                    return ['exito' => false, 'mensaje' => $mensajeError];
                }
                // El documento está en varias instituciones: SIEMPRE se pregunta a cuál entrar.
                //  - Las cuentas cuya contraseña coincidió entran con un toque.
                //  - Las demás (otra contraseña en esa institución) piden su propia contraseña en elegir_colegio.php.
                // Solo se llega aquí si la contraseña coincidió en al menos una: quien no la sabe no ve nada.
                foreach ($coinciden as $c) {
                    limpiarIntentosLogin($pdo, (int) $c['id']);
                }
                $idsOk = array_map(static fn (array $c): int => (int) $c['id'], $coinciden);
                $otros = array_values(array_map(static fn (array $c): int => (int) $c['id'], array_filter($candidatos,
                    static fn (array $c): bool => !in_array((int) $c['id'], $idsOk, true) && (int) $c['activo'] === 1)));
                session_regenerate_id(true);
                $_SESSION['login_elegir_colegio'] = ['ids' => $idsOk, 'otros' => $otros, 'expira' => time() + 300];
                return ['exito' => true, 'elegir_colegio' => true];
                $usuario = $coinciden[0];
            }
        }
        if ($usuario) {
            $restante = segundosBloqueoLogin($pdo, (int) $usuario['id']);
            if ($restante > 0) {
                $minutos = (int) ceil($restante / 60);
                return ['exito' => false, 'mensaje' => "Demasiados intentos fallidos. Intenta de nuevo en {$minutos} minuto(s)."];
            }
        }
        if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
            if ($usuario) registrarIntentoLoginFallido($pdo, (int) $usuario['id']);
            return ['exito' => false, 'mensaje' => $mensajeError];
        }
        if ((int) $usuario['activo'] === 0) {
            return ['exito' => false, 'mensaje' => 'Esta cuenta está desactivada. Contacta al administrador.'];
        }
        // Multi-colegio: un colegio suspendido no puede iniciar sesión (el superadmin no tiene colegio).
        if ($usuario['colegio_id'] !== null && (int) ($usuario['colegio_activo'] ?? 0) === 0) {
            return ['exito' => false, 'mensaje' => 'El acceso de tu colegio está suspendido. Contacta al administrador de tu colegio.'];
        }
        // Secretaría de Educación: sin secretaría asignada o con la secretaría suspendida no hay acceso.
        if ($usuario['rol'] === 'secretaria') {
            $sec = $pdo->prepare('SELECT activa FROM secretarias WHERE id = :id');
            $sec->execute(['id' => (int) ($usuario['secretaria_id'] ?? 0)]);
            if ((int) $sec->fetchColumn() !== 1) {
                return ['exito' => false, 'mensaje' => 'Tu secretaría no está activa en Serena. Contacta al equipo de la plataforma.'];
            }
        }
        limpiarIntentosLogin($pdo, (int) $usuario['id']);
        return self::completarLogin($pdo, $usuario);
    }

    /**
     * Pasos que siguen a una contraseña correcta: cuenta activa, colegio activo, secretaría activa,
     * verificación en 2 pasos e inicio de sesión. También lo usa elegir_colegio.php cuando la persona
     * eligió en qué institución entrar.
     */
    public static function completarLogin(PDO $pdo, array $usuario): array
    {
        if ((int) $usuario['activo'] === 0) {
            return ['exito' => false, 'mensaje' => 'Esta cuenta está desactivada. Contacta al administrador.'];
        }
        if ($usuario['colegio_id'] !== null && (int) ($usuario['colegio_activo'] ?? 0) === 0) {
            return ['exito' => false, 'mensaje' => 'El acceso de tu colegio está suspendido. Contacta al administrador de tu colegio.'];
        }
        if ($usuario['rol'] === 'secretaria') {
            $sec = $pdo->prepare('SELECT activa FROM secretarias WHERE id = :id');
            $sec->execute(['id' => (int) ($usuario['secretaria_id'] ?? 0)]);
            if ((int) $sec->fetchColumn() !== 1) {
                return ['exito' => false, 'mensaje' => 'Tu secretaría no está activa en Serena. Contacta al equipo de la plataforma.'];
            }
        }
        // Con verificación en 2 pasos activa (TOTP, correo, WhatsApp o rostro), la
        // contraseña sola NO abre la sesión: se deja un desafío pendiente y
        // verificacion_2pasos.php termina el inicio de sesión. Un usuario solo
        // tiene un método activo a la vez.
        if ((int) ($usuario['totp_activo'] ?? 0) === 1) {
            iniciarDesafio2FA((int) $usuario['id']);
            return ['exito' => true, 'requiere_2fa' => true, 'usuario' => $usuario];
        }
        if ((int) ($usuario['correo_2fa_activo'] ?? 0) === 1) {
            require_once __DIR__ . '/../modelos/VerificacionDosPasosCorreo.php';
            iniciarDesafio2FA((int) $usuario['id']);
            VerificacionDosPasosCorreo::generarYEnviar($pdo, (int) $usuario['id'], $usuario['correo']);
            return ['exito' => true, 'requiere_2fa' => true, 'usuario' => $usuario];
        }
        if ((int) ($usuario['whatsapp_2fa_activo'] ?? 0) === 1) {
            require_once __DIR__ . '/../modelos/VerificacionDosPasosWhatsapp.php';
            iniciarDesafio2FA((int) $usuario['id']);
            VerificacionDosPasosWhatsapp::generarYEnviar($pdo, $usuario);
            return ['exito' => true, 'requiere_2fa' => true, 'usuario' => $usuario];
        }
        if ((int) ($usuario['rostro_2fa_activo'] ?? 0) === 1) {
            // Sin código que enviar: la verificación ocurre con la cámara en verificacion_2pasos.php.
            iniciarDesafio2FA((int) $usuario['id']);
            return ['exito' => true, 'requiere_2fa' => true, 'usuario' => $usuario];
        }
        iniciarSesionUsuario($usuario, $pdo);
        Usuario::actualizarUltimoAcceso($pdo, (int) $usuario['id']);
        return ['exito' => true, 'usuario' => $usuario];
    }
    public static function registrarEstudiante(PDO $pdo, array $datos): array
    {
        if (!validarCorreo($datos['correo'])) {
            return ['exito' => false, 'mensaje' => 'El correo no es válido.'];
        }
        if (!validarPassword($datos['password'])) {
            return ['exito' => false, 'mensaje' => mensajeRequisitosPassword()];
        }
        if ($datos['password'] !== $datos['confirmar_password']) {
            return ['exito' => false, 'mensaje' => 'Las contraseñas no coinciden.'];
        }
        if (!validarEdadMinima($datos['fecha_nacimiento'])) {
            return ['exito' => false, 'mensaje' => 'Serena está disponible desde grado sexto en adelante.'];
        }
        if (empty($datos['colegio_id'])) {
            return ['exito' => false, 'mensaje' => 'Ingresa el código de tu colegio.'];
        }
        if (empty($datos['grupo_id'])) {
            return ['exito' => false, 'mensaje' => 'Selecciona tu grupo.'];
        }
        // El grupo elegido debe pertenecer al colegio del código (no basta con confiar en el formulario).
        $grupoValido = $pdo->prepare(
            'SELECT 1 FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id
             WHERE g.id = :g AND gr.colegio_id = :c LIMIT 1'
        );
        $grupoValido->execute(['g' => (int) $datos['grupo_id'], 'c' => (int) $datos['colegio_id']]);
        if (!$grupoValido->fetchColumn()) {
            return ['exito' => false, 'mensaje' => 'El grupo seleccionado no corresponde a tu colegio.'];
        }
        if (!$datos['consentimiento']) {
            return ['exito' => false, 'mensaje' => 'Debes aceptar el tratamiento de datos personales para registrarte.'];
        }
        if (Usuario::correoExiste($pdo, $datos['correo'])) {
            return ['exito' => false, 'mensaje' => 'Ya existe una cuenta con ese correo.'];
        }
        $id = Usuario::crearEstudiante($pdo, $datos);
        return ['exito' => true, 'usuario_id' => $id];
    }
    public static function solicitarRecuperacion(PDO $pdo, string $identificador): array
    {
        // Acepta correo o documento. Se responde igual exista o no la cuenta, para no revelar quién está registrado.
        // Si un documento está en más de un colegio, cada cuenta recibe el enlace en SU propio correo.
        $identificador = trim($identificador);
        $destinatarios = [];
        if (strpos($identificador, '@') !== false) {
            $u = Usuario::buscarPorCorreo($pdo, $identificador);
            if ($u) {
                $destinatarios[] = $u;
            }
        } else {
            $destinatarios = array_slice(Usuario::buscarPorDocumento($pdo, $identificador), 0, 3);
        }
        foreach ($destinatarios as $usuario) {
            $token = bin2hex(random_bytes(32));
            $expira = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $consulta = $pdo->prepare(
                'INSERT INTO tokens_recuperacion (usuario_id, token, expira_en) VALUES (:usuario_id, :token, :expira_en)'
            );
            $consulta->execute([
                'usuario_id' => $usuario['id'],
                // Solo se guarda el hash: quien lea la tabla (respaldo, phpMyAdmin) no puede usar el enlace.
                'token'      => hash('sha256', $token),
                'expira_en'  => $expira,
            ]);
            enviarCorreoRecuperacion($usuario['correo'], $token);
        }
        return ['exito' => true];
    }
    public static function restablecerPassword(PDO $pdo, string $token, string $passwordNueva): array
    {
        $consulta = $pdo->prepare(
            'SELECT * FROM tokens_recuperacion WHERE token = :token AND usado = 0 LIMIT 1'
        );
        $consulta->execute(['token' => hash('sha256', $token)]);
        $registro = $consulta->fetch();
        if (!$registro || strtotime($registro['expira_en']) < time()) {
            return ['exito' => false, 'mensaje' => 'El enlace no es válido o ya expiró.'];
        }
        if (!validarPassword($passwordNueva)) {
            return ['exito' => false, 'mensaje' => mensajeRequisitosPassword()];
        }
        Usuario::actualizarPassword($pdo, (int) $registro['usuario_id'], $passwordNueva);
        $marcarUsado = $pdo->prepare('UPDATE tokens_recuperacion SET usado = 1 WHERE id = :id');
        $marcarUsado->execute(['id' => $registro['id']]);
        // Un reset invalida cualquier otro enlace pendiente de la misma persona.
        $pdo->prepare('UPDATE tokens_recuperacion SET usado = 1 WHERE usuario_id = :u AND usado = 0')
            ->execute(['u' => (int) $registro['usuario_id']]);
        return ['exito' => true];
    }
}
function enviarCorreoRecuperacion(string $correo, string $token): void
{
    $enlace = urlAbsoluta('/restablecer_password.php?token=' . $token);

    $textoPlano = "Hola,\n\n"
        . "Recibimos una solicitud para restablecer tu contraseña en Serena.\n"
        . "Si fuiste tú, entra a este enlace (válido por 1 hora):\n\n{$enlace}\n\n"
        . "Si no pediste esto, puedes ignorar el mensaje — tu contraseña sigue igual.\n\n"
        . "— Serena";

    $cuerpoHtml = "<p>Recibimos una solicitud para restablecer tu contraseña en Serena.</p>"
        . "<p>Si fuiste tú, haz clic en el botón de abajo. El enlace es válido por <strong>1 hora</strong>.</p>"
        . botonCorreoHtml('Restablecer mi contraseña', $enlace)
        . "<p style='font-size:13px;color:#6B6B6B'>Si no pediste esto, puedes ignorar este correo — tu contraseña sigue igual.</p>";
    $htmlCompleto = plantillaCorreoHtml('Recupera tu contraseña', $cuerpoHtml);

    $enviado = smtpConfigurado() ? enviarCorreo($correo, 'Restablecer tu contraseña de Serena', $textoPlano, $htmlCompleto) : false;
    if (!$enviado) {
        // Sin SMTP configurado (desarrollo local) o si el envío falló: el enlace queda
        // en el log del servidor para que el administrador pueda dárselo a la persona
        // por otro medio mientras tanto. Ver INSTALACION.md / CAMBIOS_FASE5.md.
        error_log("Enlace de recuperación para {$correo}: {$enlace}");
    }
}