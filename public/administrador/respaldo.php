<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
// Multi-colegio: el volcado contiene los datos de TODOS los colegios, por eso solo lo descarga el superadmin.
requiereRol('superadmin');

// Descarga directa (sin guardar copia en el servidor) — para cuando solo quieres
// el archivo ya mismo. La copia guardada con retención automática vive en
// administrador/configuracion.php.
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="serena-respaldo-' . date('Y-m-d_His') . '.sql"');

echo generarVolcadoSQL($pdo, $_SESSION['nombre']);

auditarAccion($pdo, 'generar_respaldo', 'sistema', 0);
