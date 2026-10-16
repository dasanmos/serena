<?php
require_once __DIR__ . '/../../src/helpers/auth.php';
// Redirect to psicologo's view — same shared functionality
$id = (int)($_GET['id'] ?? 0);
header("Location: " . BASE_URL . "/psicologo/estudiante_detalle.php?id=$id");
exit;
