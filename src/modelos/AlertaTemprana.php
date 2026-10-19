<?php
declare(strict_types=1);

require_once __DIR__ . '/RolesUsuario.php';
require_once __DIR__ . '/../helpers/colegio.php';

class AlertaTemprana
{
    // Tipos que dispara el propio estudiante (botón "Necesito ayuda ahora"), a
    // diferencia de los automáticos (emociones_bajas, inactividad_prolongada) o
    // los que crea el personal (seguimiento). Sirve para dar un mensaje más
    // directo en el correo y una insignia distinta en las pantallas de alertas.
    public const TIPO_AYUDA_URGENTE = 'ayuda_urgente';

    public static function crear(PDO $pdo, int $usuarioId, string $tipo, string $descripcion, string $urgencia = 'medio'): int
    {
        $c = $pdo->prepare(
            'INSERT INTO alertas_tempranas (usuario_id, tipo, descripcion, nivel_urgencia) VALUES (?,?,?,?)'
        );
        $c->execute([$usuarioId, $tipo, $descripcion, $urgencia]);
        $id = (int) $pdo->lastInsertId();

        // Cubre tanto las alertas creadas a mano (psicólogo/orientador) como las
        // automáticas (ver notificador.php) — todas pasan por aquí, así que este
        // es el único lugar donde hace falta enganchar el aviso por correo.
        if ($urgencia === 'alto') {
            self::avisarPorCorreoSiCritica($pdo, $usuarioId, $descripcion, $tipo === self::TIPO_AYUDA_URGENTE);
        }

        return $id;
    }

    private static function avisarPorCorreoSiCritica(PDO $pdo, int $usuarioId, string $descripcion, bool $pedidaPorEstudiante = false): void
    {
        require_once __DIR__ . '/../helpers/correo.php';
        if (!smtpConfigurado()) {
            return; // sin Brevo configurado, no hay nada que enviar
        }

        $c = $pdo->prepare('SELECT nombre, grupo_id, colegio_id FROM usuarios WHERE id = :id');
        $c->execute(['id' => $usuarioId]);
        $estudiante = $c->fetch();
        $nombreEstudiante = $estudiante['nombre'] ?? 'un estudiante';

        // Psicología: alcance institucional, siempre se avisa. Orientación: solo
        // los orientadores que tienen a cargo el grupo de este estudiante — igual
        // que ya filtra psicologo/alertas.php vs orientador/alertas.php en pantalla.
        // Multi-colegio: SOLO psicólogos del colegio del estudiante (colegio explícito, no el de la sesión:
        // esto también corre desde cron). Sin colegio conocido no se avisa a nadie en vez de avisar a todos.
        $psicologos = [];
        if (!empty($estudiante['colegio_id'])) {
            $cp = $pdo->prepare(
                "SELECT correo FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
                 WHERE (r.nombre = 'psicologo'" . RolesUsuario::sqlAdicional($pdo, 'u', 'psicologo') . ") AND u.activo = 1 AND u.colegio_id = :col"
            );
            $cp->execute(['col' => (int) $estudiante['colegio_id']]);
            $psicologos = $cp->fetchAll(PDO::FETCH_COLUMN);
        }

        $orientadores = [];
        if (!empty($estudiante['grupo_id'])) {
            require_once __DIR__ . '/Usuario.php';
            $orientadores = Usuario::listarOrientadoresDeGrupo($pdo, (int) $estudiante['grupo_id']);
        }

        $destinatarios = array_unique(array_merge($psicologos, $orientadores));
        if (empty($destinatarios)) {
            return;
        }

        $urlAlertas = urlAbsoluta('/psicologo/alertas.php');

        $asunto = $pedidaPorEstudiante ? 'Un estudiante pidió ayuda urgente en Serena' : 'Alerta urgente en Serena';
        $introHtml = $pedidaPorEstudiante
            ? '<p><strong>' . htmlspecialchars($nombreEstudiante) . '</strong> usó el botón de ayuda urgente de Serena ahora mismo:</p>'
            : '<p>Se generó una alerta temprana de <strong>urgencia alta</strong> para ' . htmlspecialchars($nombreEstudiante) . ':</p>';
        $cuerpoHtml = $introHtml . '<p>' . htmlspecialchars($descripcion) . '</p>'
            . botonCorreoHtml('Ver alertas pendientes', $urlAlertas);
        $textoPlano = $pedidaPorEstudiante
            ? "{$nombreEstudiante} usó el botón de ayuda urgente en Serena. Entra a la plataforma para revisarlo cuanto antes."
            : "Nueva alerta de urgencia alta para {$nombreEstudiante}. Entra a la plataforma para revisarla.";

        foreach ($destinatarios as $correo) {
            enviarCorreo($correo, $asunto, $textoPlano, plantillaCorreoHtml('Alerta urgente', $cuerpoHtml));
        }
    }

    // $gruposIds: null = todas (psicólogo, alcance institucional); array vacío = ninguna
    // (orientador sin grupos asignados todavía); array con ids = solo esos grupos.
    public static function listarNoAtendidas(PDO $pdo, ?array $gruposIds = null): array
    {
        $sql = 'SELECT a.*, u.nombre AS estudiante, u.grupo_id FROM alertas_tempranas a
                INNER JOIN usuarios u ON u.id=a.usuario_id WHERE a.atendida=0';
        $params = [];
        $sql .= condicionColegio('u.colegio_id', $params);
        if ($gruposIds !== null) {
            if (empty($gruposIds)) {
                return [];
            }
            $marcadores = [];
            foreach ($gruposIds as $i => $gid) {
                $clave = "gid{$i}";
                $marcadores[] = ":{$clave}";
                $params[$clave] = (int) $gid;
            }
            $sql .= ' AND u.grupo_id IN (' . implode(',', $marcadores) . ')';
        }
        $sql .= ' ORDER BY a.nivel_urgencia DESC, a.fecha_generada DESC';
        $c = $pdo->prepare($sql);
        $c->execute($params);
        return $c->fetchAll();
    }

    public static function atender(PDO $pdo, int $id, int $atendidaPor): void
    {
        // Multi-colegio: solo se atienden alertas de estudiantes del propio colegio.
        $pdo->prepare('UPDATE alertas_tempranas SET atendida=1, atendida_por=:uid, fecha_atendida=NOW() WHERE id=:id'
                . sqlUsuarioDeColegio('usuario_id'))
            ->execute(['uid' => $atendidaPor, 'id' => $id]);
    }

    public static function existeReciente(PDO $pdo, int $usuarioId, string $tipo): bool
    {
        $c = $pdo->prepare('SELECT id FROM alertas_tempranas WHERE usuario_id=:uid AND tipo=:tipo AND fecha_generada >= NOW()-INTERVAL 3 DAY LIMIT 1');
        $c->execute(['uid' => $usuarioId, 'tipo' => $tipo]);
        return (bool)$c->fetch();
    }
}
