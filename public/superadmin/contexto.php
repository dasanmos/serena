<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/modelos/Colegio.php';
require_once __DIR__ . '/../../src/helpers/secretaria.php';
requiereRol('superadmin', 'secretaria');

// Entrar al contexto de un colegio (0 = volver a la vista global). Solo POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/' . carpetaPlataforma() . '/panel.php');
    exit;
}
verificarTokenCSRF($_POST['csrf_token'] ?? '');

$id = (int) ($_POST['colegio_id'] ?? 0);
if ($id > 0) {
    $colegio = Colegio::buscarPorId($pdo, $id);
    if (!$colegio || !colegioEnAlcance($pdo, $id)) {
        http_response_code(404);
        exit('Colegio no encontrado.');
    }
    $_SESSION['colegio_contexto'] = $id;
    auditarAccion($pdo, 'entrar_contexto_colegio', 'colegios', $id, [], ['nombre' => $colegio['nombre']]);
} else {
    unset($_SESSION['colegio_contexto']);
}

header('Location: ' . BASE_URL . '/' . carpetaPlataforma() . '/panel.php');
exit;
