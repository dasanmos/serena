<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/modelos/Expediente.php';
require_once __DIR__ . '/../src/modelos/HojaVida.php';

// Sirve una imagen de evidencia SOLO si esta persona puede abrir al estudiante y ver ese tipo de registro.
// Los archivos viven en src/uploads/evidencias (no son públicos) y nunca se sirven por su nombre.
requiereRol('estudiante', 'acudiente', 'docente', 'coordinador', 'rector', 'administrador', 'secretaria_academica', 'psicologo', 'orientador', 'secretaria', 'superadmin');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaConsulta();
$noEncontrada = static function (): never {
    http_response_code(404);
    exit('Evidencia no encontrada.');
};
if ($colegioId === null) { $noEncontrada(); }

$ev = HojaVida::evidencia($pdo, (int) ($_GET['id'] ?? 0));
if (!$ev || (int) $ev['colegio_id'] !== $colegioId) { $noEncontrada(); }
$estId = (int) $ev['estudiante_id'];
if (!Expediente::puedeAccederEstudiante($pdo, $rol, $usuarioId, $estId, $colegioId)) { $noEncontrada(); }

$cap = Expediente::capacidades($rol);
if ($ev['origen'] === 'proceso') {
    $ok = $cap['ver_convivencia'] || ($rol === 'acudiente' && HojaVida::ACUDIENTE_VE['procesos']);
} else {
    $q = $pdo->prepare('SELECT tipo, visible_familia FROM observador WHERE id = ? AND estudiante_id = ?');
    $q->execute([(int) $ev['origen_id'], $estId]);
    $o = $q->fetch();
    $ok = $o && (in_array($rol, ['acudiente', 'estudiante'], true)
        ? (int) $o['visible_familia'] === 1 && $o['tipo'] !== 'seguimiento'
        : $cap['ver_observador'] && ($o['tipo'] !== 'seguimiento' || $cap['ver_obs_reservado']));
}
if (!$ok) { $noEncontrada(); }

$ruta = HojaVida::rutaEvidencia((string) $ev['archivo']);
if (!is_file($ruta)) { $noEncontrada(); }
$mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
header('Content-Type: ' . ($mimes[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
