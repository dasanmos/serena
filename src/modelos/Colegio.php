<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/secretaria.php';

class Colegio
{
    /** Longitud estándar del código DANE de un establecimiento educativo. */
    public const DANE_LONGITUD = 12;

    /** Deja solo dígitos (quita espacios, guiones, etc.). */
    public static function normalizarDane(string $dane): string
    {
        return preg_replace('/\D+/', '', $dane) ?? '';
    }

    public static function daneValido(string $dane): bool
    {
        return preg_match('/^\d{' . self::DANE_LONGITUD . '}$/', $dane) === 1;
    }

    public static function daneExiste(PDO $pdo, string $dane, ?int $excluirId = null): bool
    {
        $c = $pdo->prepare('SELECT 1 FROM colegios WHERE dane = :d AND id <> :id LIMIT 1');
        $c->execute(['d' => $dane, 'id' => $excluirId ?? 0]);
        return (bool) $c->fetchColumn();
    }

    /** ¿Ya se aplicó la migración 033 (núcleos y zonas)? Se consulta una vez por petición. */
    public static function tieneTerritorio(PDO $pdo): bool
    {
        static $tiene = null;
        if ($tiene === null) {
            $q = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'nucleo_id'");
            $tiene = (int) $q->fetchColumn() > 0;
        }
        return $tiene;
    }

    public static function listarTodos(PDO $pdo, bool $soloActivos = false): array
    {
        $territorio = self::tieneTerritorio($pdo)
            ? "c.nucleo_id, c.zona_id, c.municipio_id,
                       (SELECT n.nombre FROM nucleos_educativos n WHERE n.id = c.nucleo_id) AS nucleo_nombre,
                       (SELECT z.nombre FROM zonas_municipio z WHERE z.id = c.zona_id) AS zona_nombre,
                       (SELECT z.tipo FROM zonas_municipio z WHERE z.id = c.zona_id) AS zona_tipo,"
            : "NULL AS nucleo_id, NULL AS zona_id, NULL AS municipio_id, NULL AS nucleo_nombre, NULL AS zona_nombre, NULL AS zona_tipo,";
        $sql = "SELECT c.id, c.nombre, c.dane, c.municipio, c.direccion, c.latitud, c.longitud, c.activo, c.creado_en, c.secretaria_id, " . $territorio . "
                       (SELECT s.nombre FROM secretarias s WHERE s.id = c.secretaria_id) AS secretaria_nombre,
                       (SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                         WHERE u.colegio_id = c.id AND r.nombre = \"estudiante\" AND u.activo = 1) AS estudiantes_activos,
                       (SELECT COUNT(*) FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                         WHERE u.colegio_id = c.id AND r.nombre <> \"estudiante\" AND u.activo = 1) AS personal_activo
                FROM colegios c WHERE 1 = 1" . ($soloActivos ? " AND c.activo = 1" : "")
                . sqlAlcanceColegios($pdo, "c.id") . " ORDER BY c.nombre";
        return $pdo->query($sql)->fetchAll();
    }

    public static function buscarPorId(PDO $pdo, int $id): ?array
    {
        $c = $pdo->prepare("SELECT * FROM colegios WHERE id = :id LIMIT 1");
        $c->execute(["id" => $id]);
        return $c->fetch() ?: null;
    }

    /** Solo colegios ACTIVOS: el DANE es el código que usa el estudiante en el registro público. */
    public static function buscarPorDane(PDO $pdo, string $dane): ?array
    {
        $dane = self::normalizarDane($dane);
        if (!self::daneValido($dane)) {
            return null;
        }
        $c = $pdo->prepare("SELECT * FROM colegios WHERE dane = :d AND activo = 1 LIMIT 1");
        $c->execute(["d" => $dane]);
        return $c->fetch() ?: null;
    }

    /** Crea el colegio. Lanza InvalidArgumentException si el DANE no es válido o ya está en uso. */
    public static function crear(PDO $pdo, string $nombre, string $dane, ?string $municipio, ?int $secretariaId = null): int
    {
        $dane = self::normalizarDane($dane);
        self::exigirDaneUtilizable($pdo, $dane, null);
        $municipio = ($municipio ?? "") !== "" ? $municipio : null;
        // Sin secretaría explícita, hereda la del municipio (si el municipio está asignado a una).
        if ($secretariaId === null && $municipio !== null) {
            $s = $pdo->prepare("SELECT secretaria_id FROM secretaria_municipios WHERE municipio = :m LIMIT 1");
            $s->execute(["m" => $municipio]);
            $encontrada = $s->fetchColumn();
            $secretariaId = $encontrada !== false ? (int) $encontrada : null;
            // Si no está en secretaria_municipios, prueba con el catálogo de municipios (solo si el nombre es único).
            if ($secretariaId === null && self::tieneTerritorio($pdo)) {
                $m = $pdo->prepare("SELECT secretaria_id FROM municipios WHERE nombre = :m AND secretaria_id IS NOT NULL");
                $m->execute(["m" => $municipio]);
                $ids = $m->fetchAll(PDO::FETCH_COLUMN);
                $secretariaId = count($ids) === 1 ? (int) $ids[0] : null;
            }
        }
        $c = $pdo->prepare("INSERT INTO colegios (nombre, dane, municipio, secretaria_id) VALUES (:n, :d, :m, :s)");
        $c->execute(["n" => $nombre, "d" => $dane, "m" => $municipio, "s" => $secretariaId]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Guarda la dirección y las coordenadas del colegio (mapa). $latLng = [lat, lng] ya validado
     * con coordenadasDesdePost(), o null para dejar el colegio sin ubicar.
     */
    public static function guardarUbicacion(PDO $pdo, int $id, ?string $direccion, ?array $latLng): void
    {
        $pdo->prepare("UPDATE colegios SET direccion = :d, latitud = :la, longitud = :lo WHERE id = :id")->execute([
            "d" => $direccion, "la" => $latLng[0] ?? null, "lo" => $latLng[1] ?? null, "id" => $id,
        ]);
    }

    public static function asignarSecretaria(PDO $pdo, int $colegioId, ?int $secretariaId): void
    {
        $pdo->prepare("UPDATE colegios SET secretaria_id = :s WHERE id = :id")->execute(["s" => $secretariaId, "id" => $colegioId]);
    }

    public static function actualizar(PDO $pdo, int $id, string $nombre, string $dane, ?string $municipio, bool $activo): void
    {
        $dane = self::normalizarDane($dane);
        self::exigirDaneUtilizable($pdo, $dane, $id);
        $c = $pdo->prepare("UPDATE colegios SET nombre = :n, dane = :d, municipio = :m, activo = :a WHERE id = :id");
        $c->execute([
            "n" => $nombre, "d" => $dane, "m" => ($municipio ?? "") !== "" ? $municipio : null,
            "a" => $activo ? 1 : 0, "id" => $id,
        ]);
    }

    private static function exigirDaneUtilizable(PDO $pdo, string $dane, ?int $excluirId): void
    {
        if (!self::daneValido($dane)) {
            throw new InvalidArgumentException('El código DANE debe tener exactamente ' . self::DANE_LONGITUD . ' dígitos.');
        }
        if (self::daneExiste($pdo, $dane, $excluirId)) {
            throw new InvalidArgumentException('Ya existe un colegio con ese código DANE.');
        }
    }
}
