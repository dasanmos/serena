<?php
declare(strict_types=1);

// Endpoint de cron para el respaldo automático de la base de datos.
//
// Un servicio externo (ej. cron-job.org, gratuito) debe llamar a esta URL, por
// ejemplo una vez al día de madrugada:
//   https://serena.infinityfreeapp.com/public/tareas/respaldo_automatico.php?secreto=TU_SECRETO
//
// El secreto se compara contra RESPALDO_CRON_SECRETO en src/config/.env. Sin
// secreto configurado, o si no coincide, responde 403 y no hace nada — igual que
// public/tareas/recordatorios.php. No requiere sesión (lo llama un servicio
// externo), por eso no incluye auth.php.
//
// Usa la misma función que el botón manual (crearRespaldoSeguro): guarda la copia
// nueva primero, confirma que quedó completa, y solo entonces descarta las más
// antiguas más allá de RESPALDOS_A_CONSERVAR. Un fallo nunca borra las anteriores.

require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
require_once __DIR__ . '/../../src/modelos/Auditoria.php';

header('Content-Type: application/json; charset=utf-8');

$secretoEsperado = entornoServer('RESPALDO_CRON_SECRETO');
$secretoRecibido = $_GET['secreto'] ?? '';

if ($secretoEsperado === '' || !hash_equals($secretoEsperado, (string) $secretoRecibido)) {
    http_response_code(403);
    echo json_encode(['error' => 'Secreto inválido o no configurado.']);
    exit;
}

$resultado = crearRespaldoSeguro($pdo, 'respaldo automático');
if ($resultado['exito']) {
    // usuario_id NULL: lo hizo el sistema, no una persona con sesión.
    Auditoria::registrar($pdo, null, 'crear_respaldo_automatico', 'sistema', 0, [], []);
} else {
    http_response_code(500);
}
echo json_encode(['exito' => $resultado['exito'], 'mensaje' => $resultado['mensaje']]);
