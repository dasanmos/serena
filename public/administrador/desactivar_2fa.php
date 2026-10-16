<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/VerificacionDosPasos.php';
require_once __DIR__ . '/../../src/modelos/VerificacionDosPasosCorreo.php';
require_once __DIR__ . '/../../src/modelos/VerificacionDosPasosWhatsapp.php';
require_once __DIR__ . '/../../src/modelos/VerificacionDosPasosRostro.php';

requiereRol('administrador');

// Salida de emergencia: la persona perdió su teléfono y sus códigos de recuperación,
// no le llegan los códigos, o la cámara no la reconoce. Desactiva el método de 2 pasos
// que tenga activo (app, correo, WhatsApp o rostro; el rostro además borra sus datos
// faciales); la contraseña no se toca. Queda en auditoría.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
verificarTokenCSRF($_POST['csrf_token'] ?? '');

$id = (int) ($_POST['id'] ?? 0);
$usuario = Usuario::buscarPorId($pdo, $id);
if (!$usuario) {
    http_response_code(404);
    exit('Usuario no encontrado.');
}

$desactivado = false;
if (VerificacionDosPasos::estaActivo($usuario)) {
    VerificacionDosPasos::desactivar($pdo, $id);
    $desactivado = true;
}
if (VerificacionDosPasosCorreo::estaActivo($usuario)) {
    VerificacionDosPasosCorreo::desactivar($pdo, $id);
    $desactivado = true;
}
if (VerificacionDosPasosWhatsapp::estaActivo($usuario)) {
    VerificacionDosPasosWhatsapp::desactivar($pdo, $id);
    $desactivado = true;
}
if (VerificacionDosPasosRostro::estaActivo($usuario)) {
    VerificacionDosPasosRostro::desactivar($pdo, $id);
    $desactivado = true;
}
if ($desactivado) {
    auditarAccion($pdo, 'desactivar_2fa_admin', 'usuarios', $id);
}

header('Location: usuario_editar.php?id=' . $id . '&dospasos=1');
exit;
