<?php
declare(strict_types=1);

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/impersonacion.php';

// A propósito NO se usa requiereRol('administrador') aquí: mientras se está viendo el
// sistema como otro usuario, $_SESSION['rol'] es el de ESE usuario (estudiante, docente,
// etc.), no 'administrador'. El único requisito real para volver es que exista una
// identidad de administrador guardada (admin_original) — eso es lo que valida volverAAdmin().
requiereSesion();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

verificarTokenCSRF($_POST['csrf_token'] ?? null);

$resultado = volverAAdmin();

if ($resultado['exito']) {
    // Al llegar aquí la sesión YA volvió a ser la del administrador, así que
    // auditarAccion() (que lee $_SESSION actual) atribuye correctamente la acción.
    auditarAccion($pdo, 'volver_de_ver_como_usuario', 'usuarios', (int) $_SESSION['usuario_id']);
    header('Location: ' . rutaDashboardPorRol($_SESSION['rol']));
    exit;
}

header('Location: ' . BASE_URL . '/login.php');
exit;
