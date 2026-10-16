<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/helpers/auth.php';

cerrarSesionUsuario();

header('Location: login.php');
exit;
