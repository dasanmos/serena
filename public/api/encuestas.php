<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/respuestas.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $encuestaId = (int)($_GET['id'] ?? 0);

    if ($encuestaId) {
        $encuesta = Encuesta::buscarPorId($pdo, $encuestaId);
        if (!$encuesta) jsonError('Encuesta no encontrada.', 404);
        jsonExito([
            'encuesta'      => $encuesta,
            'preguntas'     => Encuesta::preguntas($pdo, $encuestaId),
            'ya_respondio'  => Encuesta::usuarioYaRespondio($pdo, $encuestaId, $uid),
            'cerrada'       => Encuesta::yaConcluida($pdo, $encuestaId),
        ]);
    }

    jsonExito(Encuesta::listarParaRol($pdo, 'estudiante', $_SESSION['grupo_id'] ?? null));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents('php://input'), true) ?? [];
    verificarTokenCSRF_json($datos['csrf_token'] ?? null);

    $encuestaId = (int)($datos['encuesta_id'] ?? 0);
    $encuesta = $encuestaId ? Encuesta::buscarPorId($pdo, $encuestaId) : null;
    if (!$encuesta) jsonError('Encuesta no encontrada.', 404);
    if (Encuesta::yaConcluida($pdo, $encuestaId)) jsonError('Esta encuesta ya cerró.', 409);
    if (Encuesta::usuarioYaRespondio($pdo, $encuestaId, $uid)) jsonError('Ya respondiste esta encuesta.', 409);

    $respuestas = [];
    foreach ($datos['respuestas'] ?? [] as $preguntaId => $r) {
        $preguntaId = (int)$preguntaId;
        if (isset($r['opcion_id'])) {
            $respuestas[$preguntaId] = ['opcion_id' => (int)$r['opcion_id'], 'texto' => null];
        } else {
            $respuestas[$preguntaId] = ['opcion_id' => null, 'texto' => (string)($r['texto'] ?? '')];
        }
    }
    if (empty($respuestas)) jsonError('No se recibió ninguna respuesta.');

    Encuesta::guardarRespuestas($pdo, $uid, $respuestas);
    jsonExito(null, 'Respuestas enviadas. ¡Gracias!');
}

jsonError('Método no permitido', 405);
