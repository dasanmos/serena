<?php
declare(strict_types=1);

require_once __DIR__ . '/GeneradorPDF.php';
require_once __DIR__ . '/QrCode.php';
require_once __DIR__ . '/configuracion.php';
require_once __DIR__ . '/colegio.php';
require_once __DIR__ . '/../modelos/Certificados.php';

/**
 * Formato institucional ÚNICO para todos los informes, reportes y estadísticas en PDF:
 * membrete con logo, nombre oficial, DANE/NIT, resolución y ubicación del colegio; título; pie con quién y cuándo lo generó,
 * «Página n de N» y, en la versión «con QR», un código QR que lleva a verificar.php (requiere la migración 039).
 *
 *   $pdf = DocumentoPdf::crear($pdo, 'Informe de asistencia', 'Asistencia — 8.º A', $conQr);
 *   $pdf->subtitulo('…'); $pdf->tablaAjustada($enc, $filas);
 *   $pdf->salidaDescarga('Asistencia-8A');
 */
final class DocumentoPdf
{
    /** ¿Se pidió la versión con QR? (?qr=1 | ?pdf=qr) */
    public static function pideQr(): bool
    {
        return (string) ($_GET['qr'] ?? '') === '1' || (string) ($_GET['pdf'] ?? '') === 'qr';
    }

    /**
     * Crea el PDF ya con membrete y pie institucionales.
     * @param string   $tipo      etiqueta corta del documento (sale arriba a la derecha): «Informe de asistencia»
     * @param string   $titulo    título del documento (metadatos del PDF y registro de verificación)
     * @param int|null $colegioId colegio del informe; null = el de la sesión (superadmin/secretaría sin contexto: membrete de la plataforma)
     * @param bool     $horizontal A4 horizontal (tablas anchas)
     * @param array<string,mixed> $extra datos extra del membrete (p. ej. 'ref' => 'INF-12' se imprime en el pie)
     */
    public static function crear(PDO $pdo, string $tipo, string $titulo, bool $conQr = false, ?int $colegioId = null, bool $horizontal = false, array $extra = []): GeneradorPDF
    {
        $colegioId ??= colegioParaConsulta();
        $m = self::membrete($pdo, $colegioId);
        $m['tipo'] = $tipo;
        $m['usuario'] = (string) ($_SESSION['nombre'] ?? 'Usuario Serena');
        $m['fecha'] = CertificadoPDF::fechaLarga(date('Y-m-d'));
        $m['fecha_hora'] = date('d/m/Y H:i');
        if ($conQr) {
            $codigo = self::registrar($pdo, $colegioId, $tipo, $titulo);
            if ($codigo === null) {
                // Nunca dejar un «PDF con QR» sin QR y sin explicación: queda dicho en el pie del documento.
                $m['aviso_qr'] = 'Versión con QR no disponible: no se pudo registrar el documento (' . (self::$ultimoError !== '' ? self::$ultimoError : 'revise la migración 039') . ').';
            } else {
                $url = Certificados::urlBase(self::perfil($pdo, $colegioId)) . '/verificar.php?c=' . $codigo;
                $m['codigo'] = Certificados::formatearCodigo($codigo);
                $m['url_corta'] = (string) preg_replace('#^https?://#i', '', $url);
                $m['qr'] = QrCode::matriz($url);
            }
        }
        $pdf = new GeneradorPDF($horizontal);
        $pdf->configurarMembrete($extra + $m);
        return $pdf;
    }

    /** @return array<string,mixed> perfil de la institución (o vacío para la plataforma) */
    private static function perfil(PDO $pdo, ?int $colegioId): array
    {
        if ($colegioId === null || $colegioId <= 0) {
            return [];
        }
        try {
            return Certificados::institucion($pdo, $colegioId);
        } catch (PDOException $e) {
            return [];
        }
    }

    /** @return array<string,mixed> */
    private static function membrete(PDO $pdo, ?int $colegioId): array
    {
        $ins = self::perfil($pdo, $colegioId);
        if (!$ins) {
            return [
                'nombre' => 'SERENA · Plataforma de Bienestar Emocional Institucional',
                'lineas' => ['Informe consolidado de la plataforma'],
                'color' => '#AD6B05',
            ];
        }
        $nombre = trim((string) ($ins['nombre_oficial'] ?? ''));
        if ($nombre === '') {
            $q = $pdo->prepare('SELECT nombre FROM colegios WHERE id = ?');
            $q->execute([$colegioId]);
            $nombre = (string) ($q->fetchColumn() ?: 'Institución educativa');
        }
        $ids = [];
        if (!empty($ins['codigo_dane'])) { $ids[] = 'Código DANE: ' . $ins['codigo_dane']; }
        if (!empty($ins['nit'])) { $ids[] = 'NIT: ' . $ins['nit']; }
        if (!empty($ins['naturaleza'])) { $ids[] = (string) $ins['naturaleza']; }
        $res = '';
        if (!empty($ins['resolucion_numero'])) {
            $res = 'Resolución N.º ' . $ins['resolucion_numero']
                . (!empty($ins['resolucion_fecha']) ? ' del ' . date('d/m/Y', (int) strtotime((string) $ins['resolucion_fecha'])) : '')
                . (!empty($ins['resolucion_entidad']) ? ' de ' . $ins['resolucion_entidad'] : '');
        }
        $lugar = implode(', ', array_filter([(string) ($ins['municipio'] ?? ''), (string) ($ins['departamento'] ?? '')]));
        $ub = implode(' · ', array_filter([(string) ($ins['direccion'] ?? ''), $lugar, (string) ($ins['telefono'] ?? ''), (string) ($ins['correo'] ?? '')]));
        $logo = !empty($ins['logo_b64']) ? (string) base64_decode((string) $ins['logo_b64'], true) : '';
        return [
            'nombre' => $nombre,
            'lineas' => array_values(array_filter([implode(' · ', $ids), $res, $ub], 'strlen')),
            'color' => (string) ($ins['color_primario'] ?? '#AD6B05'),
            'logo' => $logo,
        ];
    }

    private static function clave(PDO $pdo): string
    {
        $k = configuracionObtener($pdo, 'certificados_clave', '');
        if ($k === '') {
            $k = bin2hex(random_bytes(32));
            configuracionGuardar($pdo, 'certificados_clave', $k);
        }
        return $k;
    }

    private static function huella(PDO $pdo, string $codigo, ?int $colegioId, string $tipo, string $titulo, int $usuarioId, string $creado): string
    {
        return hash_hmac('sha256', json_encode([$codigo, $colegioId, $tipo, $titulo, $usuarioId, $creado]), self::clave($pdo));
    }

    private static string $ultimoError = '';

    /** Crea la tabla de documentos si falta (equivale a la migración 039; reejecutable). */
    private static function asegurarTabla(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS documentos_emitidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo CHAR(12) NOT NULL,
            colegio_id INT NULL,
            tipo VARCHAR(80) NOT NULL,
            titulo VARCHAR(200) NOT NULL,
            generado_por INT NULL,
            rol VARCHAR(40) NULL,
            huella CHAR(64) NOT NULL,
            creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verificaciones INT UNSIGNED NOT NULL DEFAULT 0,
            ultima_verificacion DATETIME NULL,
            anulado TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY uq_documentos_codigo (codigo),
            KEY ix_documentos_colegio (colegio_id, creado_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /** Registra el documento y devuelve su código público (12 caracteres). Si la tabla no existe la crea; null solo si no hay forma de registrar. */
    private static function registrar(PDO $pdo, ?int $colegioId, string $tipo, string $titulo): ?string
    {
        self::$ultimoError = '';
        try {
            return self::registrarIntento($pdo, $colegioId, $tipo, $titulo);
        } catch (PDOException $e) {
            try {
                self::asegurarTabla($pdo);
                return self::registrarIntento($pdo, $colegioId, $tipo, $titulo);
            } catch (PDOException $e2) {
                self::$ultimoError = 'error de base de datos';
                return null;
            }
        }
    }

    /** @throws PDOException */
    private static function registrarIntento(PDO $pdo, ?int $colegioId, string $tipo, string $titulo): ?string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
        $creado = date('Y-m-d H:i:s');
        {
            for ($intento = 0; $intento < 5; $intento++) {
                $codigo = '';
                for ($i = 0; $i < 12; $i++) {
                    $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $ins = $pdo->prepare('INSERT IGNORE INTO documentos_emitidos (codigo, colegio_id, tipo, titulo, generado_por, rol, huella, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $ins->execute([
                    $codigo, $colegioId, mb_substr($tipo, 0, 80), mb_substr($titulo, 0, 200), $usuarioId ?: null,
                    (string) ($_SESSION['rol'] ?? ''), self::huella($pdo, $codigo, $colegioId, mb_substr($tipo, 0, 80), mb_substr($titulo, 0, 200), $usuarioId, $creado), $creado,
                ]);
                if ($ins->rowCount() === 1) {
                    return $codigo;
                }
            }
        }
        self::$ultimoError = 'no se generó un código único';
        return null;
    }

    /** Documento por su código público, con la comprobación de integridad. @return array<string,mixed>|null */
    public static function buscar(PDO $pdo, string $codigo): ?array
    {
        $codigo = Certificados::normalizarCodigo($codigo);
        if (strlen($codigo) !== 12) {
            return null;
        }
        try {
            $q = $pdo->prepare('SELECT d.*, c.nombre AS colegio_nombre FROM documentos_emitidos d LEFT JOIN colegios c ON c.id = d.colegio_id WHERE d.codigo = ?');
            $q->execute([$codigo]);
            $d = $q->fetch();
        } catch (PDOException $e) {
            return null;
        }
        if (!$d) {
            return null;
        }
        $d['integro'] = hash_equals((string) $d['huella'], self::huella(
            $pdo, (string) $d['codigo'], $d['colegio_id'] !== null ? (int) $d['colegio_id'] : null, (string) $d['tipo'], (string) $d['titulo'],
            (int) ($d['generado_por'] ?? 0), (string) $d['creado_en']
        ));
        return $d;
    }

    public static function registrarVerificacion(PDO $pdo, int $id): void
    {
        $pdo->prepare('UPDATE documentos_emitidos SET verificaciones = verificaciones + 1, ultima_verificacion = NOW() WHERE id = ?')->execute([$id]);
    }
}
