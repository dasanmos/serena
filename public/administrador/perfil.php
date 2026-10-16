<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/SolicitudCambio.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';
if (file_exists(__DIR__ . '/../../src/modelos/Insignia.php')) {
    require_once __DIR__ . '/../../src/modelos/Insignia.php';
}
requiereRol('administrador');
require __DIR__ . '/../parciales/perfil_comun.php';
