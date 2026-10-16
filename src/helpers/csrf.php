<?php
declare(strict_types=1);

// Requiere que la sesión ya esté iniciada (auth.php debe incluirse antes que este archivo).

function tokenCSRF(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Imprime el campo oculto listo para pegar dentro de cualquier <form method="POST">.
function campoCSRF(): void
{
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(tokenCSRF()) . '">';
}

// Corta la ejecución con 403 si el token no coincide. Usar al inicio de cada bloque que procesa un POST.
function verificarTokenCSRF(?string $tokenRecibido): void
{
    $tokenSesion = $_SESSION['csrf_token'] ?? null;
    if (!$tokenSesion || !$tokenRecibido || !hash_equals($tokenSesion, $tokenRecibido)) {
        http_response_code(403);
        die('Solicitud inválida o expirada. Vuelve a intentarlo desde el formulario.');
    }
}
