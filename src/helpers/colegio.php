<?php
declare(strict_types=1);

/**
 * Aislamiento multi-colegio (Fase 3).
 *
 * Regla de oro: un usuario solo ve y modifica datos de SU colegio
 * ($_SESSION["colegio_id"]). El rol "superadmin" no pertenece a ningún colegio y
 * solo puede operar dentro de uno si entra explícitamente a su contexto
 * ($_SESSION["colegio_contexto"]).
 *
 * Diseño "falla cerrado": si hay un usuario autenticado (no superadmin) y por
 * cualquier motivo la sesión no trae colegio, el filtro usa colegio 0, que no
 * existe -> las consultas devuelven vacío en vez de filtrar datos de otros.
 * Solo SIN sesión (cron, CLI, páginas públicas) el filtro se omite, y esas
 * rutas deben pasar el colegio de forma explícita cuando lo necesiten.
 */

function esSuperadmin(): bool
{
    return (($_SESSION["rol"] ?? "") === "superadmin");
}

/** Usuario de una Secretaría de Educación (ve solo los colegios de su secretaría; ver helpers/secretaria.php). */
function esSecretaria(): bool
{
    return (($_SESSION["rol"] ?? "") === "secretaria");
}

/** Colegio del usuario en sesión; null si es superadmin o no hay sesión. */
function colegioIdSesion(): ?int
{
    if (empty($_SESSION["usuario_id"]) || esSuperadmin() || esSecretaria()) {
        return null;
    }
    return (int) ($_SESSION["colegio_id"] ?? 0);
}

/**
 * Colegio con el que deben filtrarse las consultas ahora mismo.
 *  - $explicito !== null  -> ese (cron, registro público, superadmin eligiendo).
 *  - usuario normal       -> su colegio (0 si faltara: falla cerrado).
 *  - superadmin           -> el colegio de contexto, o null (vista global agregada).
 *  - sin sesión           -> null (sin filtro; el llamador debe ser consciente).
 */
function colegioParaConsulta(?int $explicito = null): ?int
{
    if ($explicito !== null) {
        return $explicito;
    }
    if (empty($_SESSION["usuario_id"])) {
        return null;
    }
    if (esSuperadmin() || esSecretaria()) {
        $ctx = (int) ($_SESSION["colegio_contexto"] ?? 0);
        return $ctx > 0 ? $ctx : null;
    }
    return (int) ($_SESSION["colegio_id"] ?? 0);
}

/**
 * Fragmento " AND columna = :param" listo para concatenar a un WHERE, y deja el
 * valor en $params. Devuelve "" si no hay que filtrar. $columna es siempre una
 * constante escrita en el código (nunca entrada de usuario).
 */
function condicionColegio(string $columna, array &$params, ?int $colegioId = null, string $param = "colegio_scope"): string
{
    $id = colegioParaConsulta($colegioId);
    if ($id === null) {
        return "";
    }
    $params[$param] = $id;
    return " AND {$columna} = :{$param}";
}

/** Colegio al que debe pertenecer cualquier registro NUEVO creado ahora (usuarios, grados...). */
function colegioParaCrear(): int
{
    $id = colegioParaConsulta();
    if ($id === null || $id <= 0) {
        // Superadmin/secretaría sin colegio en contexto: página con formato que los lleva a su panel
        // (en vez de una línea suelta sobre fondo blanco). Ver acceso_denegado.php.
        require_once __DIR__ . "/acceso_denegado.php";
        mostrarAccesoDenegado(
            "Este módulo trabaja dentro de un colegio. Elige uno en el selector 🏫 de la barra superior y vuelve a abrirlo.",
            "Primero elige un colegio"
        );
    }
    return $id;
}

function usuarioEsDeMiColegio(PDO $pdo, int $usuarioId): bool
{
    $colegio = colegioParaConsulta();
    if ($colegio === null) {
        return true; // superadmin sin contexto
    }
    $c = $pdo->prepare("SELECT 1 FROM usuarios WHERE id = :id AND colegio_id = :c LIMIT 1");
    $c->execute(["id" => $usuarioId, "c" => $colegio]);
    return (bool) $c->fetchColumn();
}

function grupoEsDeMiColegio(PDO $pdo, int $grupoId): bool
{
    $colegio = colegioParaConsulta();
    if ($colegio === null) {
        return true;
    }
    $c = $pdo->prepare(
        "SELECT 1 FROM grupos g INNER JOIN grados gr ON gr.id = g.grado_id
         WHERE g.id = :id AND gr.colegio_id = :c LIMIT 1"
    );
    $c->execute(["id" => $grupoId, "c" => $colegio]);
    return (bool) $c->fetchColumn();
}

function gradoEsDeMiColegio(PDO $pdo, int $gradoId): bool
{
    $colegio = colegioParaConsulta();
    if ($colegio === null) {
        return true;
    }
    $c = $pdo->prepare("SELECT 1 FROM grados WHERE id = :id AND colegio_id = :c LIMIT 1");
    $c->execute(["id" => $gradoId, "c" => $colegio]);
    return (bool) $c->fetchColumn();
}

/** Corta la petición con 404 si el usuario objetivo es de otro colegio (no revela que existe). */
function exigirUsuarioDeMiColegio(PDO $pdo, int $usuarioId): void
{
    if (!usuarioEsDeMiColegio($pdo, $usuarioId)) {
        http_response_code(404);
        exit("No encontrado.");
    }
}

function exigirGrupoDeMiColegio(PDO $pdo, int $grupoId): void
{
    if (!grupoEsDeMiColegio($pdo, $grupoId)) {
        http_response_code(404);
        exit("No encontrado.");
    }
}

/**
 * Igual que condicionColegio() pero con el id YA incrustado (siempre (int)), para
 * poder usarlo en consultas armadas con $pdo->query("...") sin parámetros. Es
 * seguro porque el valor sale de la sesión o de código propio, y se castea a int.
 */
function sqlColegio(string $columna, ?int $colegioId = null): string
{
    $id = colegioParaConsulta($colegioId);
    return $id === null ? "" : " AND {$columna} = " . (int) $id;
}

/**
 * Para tablas que solo guardan usuario_id (alertas, emociones, citas...):
 * " AND <columna> IN (SELECT id FROM usuarios WHERE colegio_id = N)". Vacío si no hay que filtrar.
 */
function sqlUsuarioDeColegio(string $columnaUsuario, ?int $colegioId = null): string
{
    $id = colegioParaConsulta($colegioId);
    return $id === null ? "" : " AND {$columnaUsuario} IN (SELECT id FROM usuarios WHERE colegio_id = " . (int) $id . ")";
}
