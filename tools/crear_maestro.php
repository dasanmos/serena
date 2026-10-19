<?php
declare(strict_types=1);

/**
 * Crea (o actualiza) la CUENTA MAESTRA de Serena: un superadmin que, al iniciar sesión, elige con qué
 * rol entrar y puede usar el modo demostración. Es el primer usuario de una instalación nueva.
 *
 * Uso (desde la carpeta del proyecto, con src/config/.env ya configurado):
 *   php tools/crear_maestro.php correo@dominio.com "Tu Nombre" "Contraseña#Segura1"
 *
 * Si el hosting no tiene consola (InfinityFree), ejecútalo en tu computador apuntando a la base remota
 * desde .env, o usa la alternativa en SQL que explica docs/INSTALACION.md.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/validaciones.php';

[$_, $correo, $nombre, $clave] = array_pad($argv, 4, '');
$correo = strtolower(trim($correo));
if ($correo === '' || $nombre === '' || $clave === '') {
    fwrite(STDERR, "Uso: php tools/crear_maestro.php correo \"Nombre\" \"Contraseña\"\n");
    exit(1);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El correo no es válido.\n");
    exit(1);
}
if (function_exists('validarPassword') && !validarPassword($clave)) {
    fwrite(STDERR, (function_exists('mensajeRequisitosPassword') ? mensajeRequisitosPassword() : 'La contraseña no cumple los requisitos.') . "\n");
    exit(1);
}

$rol = (int) $pdo->query("SELECT id FROM roles WHERE nombre = 'superadmin'")->fetchColumn();
if ($rol === 0) {
    fwrite(STDERR, "Falta el rol superadmin: importa primero sql/instalacion_completa.sql.\n");
    exit(1);
}
// Si existe la secretaría de Medellín (migración 031), la cuenta maestra queda asociada para poder
// entrar también con el rol de Secretaría de Educación.
$secretaria = $pdo->query("SELECT id FROM secretarias ORDER BY id LIMIT 1")->fetchColumn() ?: null;
$hash = password_hash($clave, PASSWORD_DEFAULT);

$q = $pdo->prepare('SELECT id FROM usuarios WHERE correo = ?');
$q->execute([$correo]);
$id = (int) $q->fetchColumn();
if ($id > 0) {
    $pdo->prepare('UPDATE usuarios SET nombre = ?, password_hash = ?, rol_id = ?, colegio_id = NULL, es_maestro = 1, activo = 1, secretaria_id = ? WHERE id = ?')
        ->execute([$nombre, $hash, $rol, $secretaria, $id]);
    echo "Cuenta maestra actualizada: {$correo}\n";
} else {
    $pdo->prepare('INSERT INTO usuarios (nombre, correo, password_hash, rol_id, colegio_id, consentimiento_datos, fecha_consentimiento, fecha_registro, es_maestro, activo, secretaria_id)
                   VALUES (?, ?, ?, ?, NULL, 1, NOW(), NOW(), 1, 1, ?)')
        ->execute([$nombre, $correo, $hash, $rol, $secretaria]);
    echo "Cuenta maestra creada: {$correo}\n";
}
echo "Entra por /public/login.php. Después, crea tu colegio en «Colegios» y su administrador en «Personal».\n";
