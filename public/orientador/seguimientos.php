<?php
require_once __DIR__ . '/../../src/helpers/auth.php';
// Redirect to psicologo's view — same shared functionality
header("Location: " . BASE_URL . "/psicologo/seguimientos.php");
exit;
