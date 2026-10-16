<?php
declare(strict_types=1);

/**
 * Página de "sin permiso" con el formato de Serena (menú, colores, modo oscuro) que,
 * pasados unos segundos, lleva a la persona a SU panel principal (dashboard).
 *
 * Reemplaza el viejo `exit('No tienes permiso para ver esta página.')`, que dejaba una
 * página en blanco con una sola línea de texto. Pasa cuando alguien escribe a mano la
 * dirección de un módulo que no es de su rol (o abre un enlace guardado).
 *
 * Uso:
 *   mostrarAccesoDenegado();                                   // mensaje por defecto
 *   mostrarAccesoDenegado('No tienes acceso a esta conversación.');
 *   mostrarAccesoDenegado('Elige un colegio…', 'Primero elige un colegio');
 *
 * Siempre corta la ejecución (exit). Requiere sesión iniciada; si no la hay, manda al login.
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

/** Nombre legible del rol para mostrarlo en la página. */
function etiquetaRolAcceso(string $rol): string
{
    $etiquetas = [
        'estudiante'           => 'Estudiante',
        'acudiente'            => 'Acudiente',
        'docente'              => 'Docente',
        'psicologo'            => 'Psicología',
        'orientador'           => 'Orientación',
        'coordinador'          => 'Coordinación',
        'rector'               => 'Rectoría',
        'secretaria_academica' => 'Secretaría académica',
        'administrador'        => 'Administración',
        'secretaria'           => 'Secretaría de Educación',
        'superadmin'           => 'Superadministración',
    ];
    return $etiquetas[$rol] ?? 'tu rol';
}

/** ¿La petición viene de JavaScript (fetch/XHR) y no de alguien navegando? Ahí no se dibuja HTML. */
function peticionEsAsincrona(): bool
{
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        return true;
    }
    $acepta = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    return $acepta !== '' && str_starts_with($acepta, 'application/json');
}

function mostrarAccesoDenegado(
    string $mensaje = 'Este módulo no es de tu rol, así que no puedes abrirlo.',
    string $titulo = 'No tienes permiso para este módulo',
    int $segundos = 6
) {
    global $pdo; // encabezado.php lo usa (notificaciones, colegio en la barra)

    if (!headers_sent()) {
        http_response_code(403);
        header('Cache-Control: no-store');
    }

    // Sin sesión no hay panel al que volver: login.
    if (empty($_SESSION['usuario_id']) || empty($_SESSION['rol']) || empty($_SESSION['nombre'])) {
        if (!headers_sent()) {
            header('Location: ' . BASE_URL . '/login.php');
        }
        exit;
    }

    // fetch()/XHR: respuesta corta de texto, sin página.
    if (peticionEsAsincrona()) {
        header('Content-Type: text/plain; charset=UTF-8');
        exit($mensaje);
    }

    require_once __DIR__ . '/auth.php'; // rutaDashboardPorRol()

    $rol = (string) $_SESSION['rol'];
    $destino = rutaDashboardPorRol($rol);
    $segundos = max(3, min(15, $segundos));

    // Si lo que se negó ES el propio panel (caso raro), no se redirige: sería un bucle infinito.
    $sufijoPanel = substr($destino, strlen(BASE_URL));
    $esElMismoPanel = $sufijoPanel !== '' && str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), $sufijoPanel);
    $redirigir = !$esElMismoPanel;

    $tituloPagina = 'Sin permiso';
    $cssExtra = ['/assets/css/acceso_denegado.css'];
    $jsExtra = $redirigir ? ['/assets/js/acceso_denegado.js'] : [];
    $e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

    require __DIR__ . '/../../public/parciales/encabezado.php';
    ?>
<section class="acceso-denegado" aria-labelledby="acceso-denegado-titulo">
    <div class="acceso-denegado-tarjeta">
        <div class="acceso-denegado-icono" aria-hidden="true">🔒</div>
        <h1 id="acceso-denegado-titulo"><?= $e($titulo) ?></h1>
        <p class="acceso-denegado-mensaje" role="alert"><?= $e($mensaje) ?></p>
        <p class="texto-suave acceso-denegado-rol">
            Tu cuenta es de <strong><?= $e(etiquetaRolAcceso($rol)) ?></strong>. Cada rol ve solo los módulos que le corresponden;
            los tuyos están en el menú de arriba.
        </p>

        <div class="acceso-denegado-acciones">
            <a class="acceso-denegado-boton" href="<?= $e($destino) ?>" id="acceso-denegado-ir">Ir a mi panel ahora</a>
            <?php if ($redirigir): ?>
            <button type="button" class="acceso-denegado-secundario" id="acceso-denegado-cancelar" hidden>Quedarme aquí</button>
            <?php endif; ?>
        </div>

        <?php if ($redirigir): ?>
        <div class="acceso-denegado-redireccion" id="acceso-denegado-redireccion"
             data-destino="<?= $e($destino) ?>" data-segundos="<?= (int) $segundos ?>">
            <p class="acceso-denegado-cuenta" aria-live="off">
                Te llevamos a tu panel en <strong id="acceso-denegado-segundos"><?= (int) $segundos ?></strong> segundos.
            </p>
            <div class="acceso-denegado-barra" aria-hidden="true"><span id="acceso-denegado-progreso"></span></div>
        </div>
        <noscript><p class="texto-suave">Pulsa el botón para volver a tu panel.</p></noscript>
        <?php endif; ?>
    </div>
</section>
<?php
    require __DIR__ . '/../../public/parciales/pie.php';
    exit;
}
