<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_nombre']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_usuario'], $config['db_password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Offset fijo (no nombre de zona: hostings compartidos como InfinityFree normalmente
    // no tienen cargadas las tablas mysql.time_zone_name) para que NOW()/CURDATE() y
    // demás funciones de fecha de MySQL coincidan con la hora de Bogotá/Medellín.
    $pdo->exec("SET time_zone = '-05:00'");
} catch (PDOException $e) {
    error_log('Error de conexión BD: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos.');
}
