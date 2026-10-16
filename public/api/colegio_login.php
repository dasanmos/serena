<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/validaciones.php';

// Pública (se usa en el login, antes de tener sesión). Devuelve SOLO el nombre del colegio
// de una cuenta activa (por correo o por documento) — nada más. Para no servir de "adivinador de correos":
//  - límite de consultas por sesión, y una pausa corta aleatoria en cada respuesta,
//  - cuentas desconocidas, inactivas, de colegios suspendidos y del superadmin responden igual (null).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$ahora = time();
$ventana = $_SESSION['cl_ventana'] ?? ['inicio' => $ahora, 'n' => 0];
if ($ahora - (int) $ventana['inicio'] > 600) { $ventana = ['inicio' => $ahora, 'n' => 0]; }
$ventana['n']++;
$_SESSION['cl_ventana'] = $ventana;
if ($ventana['n'] > 15) {
    http_response_code(429);
    echo json_encode(['colegio' => null]);
    exit;
}

usleep(random_int(120000, 300000));

$entrada = trim((string) ($_GET['q'] ?? $_GET['correo'] ?? ''));
$colegio = null;
if (strpos($entrada, '@') !== false) {
    if (validarCorreo($entrada)) {
        $c = $pdo->prepare(
            "SELECT col.nombre FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             INNER JOIN colegios col ON col.id = u.colegio_id
             WHERE u.correo = :c AND u.activo = 1 AND col.activo = 1 AND r.nombre <> 'superadmin' LIMIT 1"
        );
        $c->execute(['c' => $entrada]);
        $colegio = $c->fetchColumn() ?: null;
    }
} else {
    // Por documento: solo se muestra el colegio si el documento pertenece a UNA sola cuenta activa.
    $documento = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $entrada) ?? '');
    if (strlen($documento) >= 4 && strlen($documento) <= 20) {
        try {
            $c = $pdo->prepare(
                "SELECT col.nombre FROM usuarios u
                 INNER JOIN roles r ON r.id = u.rol_id
                 INNER JOIN colegios col ON col.id = u.colegio_id
                 WHERE u.documento = :d AND u.activo = 1 AND col.activo = 1 AND r.nombre <> 'superadmin' LIMIT 2"
            );
            $c->execute(['d' => $documento]);
            $nombres = $c->fetchAll(PDO::FETCH_COLUMN);
            $colegio = count($nombres) === 1 ? $nombres[0] : null;
        } catch (Throwable $e) {
            $colegio = null; // falta la migración 043
        }
    }
}
echo json_encode(['colegio' => $colegio]);
