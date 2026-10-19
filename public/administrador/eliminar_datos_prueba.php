<?php
// Pantalla exclusiva del superadministrador: se movió a /superadmin/. Se deja esta
// redirección para no romper marcadores ni enlaces antiguos.
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ../superadmin/eliminar_datos_prueba.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
