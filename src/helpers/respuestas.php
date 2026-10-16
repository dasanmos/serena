<?php
declare(strict_types=1);

function jsonExito(mixed $datos = null, string $mensaje = 'OK'): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['exito' => true, 'mensaje' => $mensaje, 'datos' => $datos]);
    exit;
}

function jsonError(string $mensaje, int $codigo = 400): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['exito' => false, 'mensaje' => $mensaje]);
    exit;
}

// Igual que verificarTokenCSRF() de csrf.php, pero para endpoints JSON: en vez de
// morir con texto plano (die()), corta con una respuesta JSON coherente con el resto
// de la API. El token se espera en el propio cuerpo JSON, como cualquier otro campo.
function verificarTokenCSRF_json(?string $tokenRecibido): void
{
    $tokenSesion = $_SESSION['csrf_token'] ?? null;
    if (!$tokenSesion || !$tokenRecibido || !hash_equals($tokenSesion, $tokenRecibido)) {
        jsonError('Token CSRF inválido o expirado.', 403);
    }
}
