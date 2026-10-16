<?php
declare(strict_types=1);

class Mensaje
{
    // Roles con los que un estudiante puede iniciar conversación EMOCIONAL. Solo
    // ellos ven contexto emocional, así que solo ellos chatean libremente con
    // cualquier estudiante — es la regla de privacidad central del proyecto.
    private const ROLES_QUE_HABLAN_CON_ESTUDIANTES = ['psicologo', 'orientador'];

    // Punto único de entrada para crear/obtener una conversación entre dos usuarios
    // cualesquiera. Aplica la regla de negocio antes de tocar la base de datos:
    // - Nunca estudiante-estudiante.
    // - Estudiante con psicólogo/orientador: siempre permitido (canal emocional).
    // - Estudiante con docente: solo si es EL docente de grupo asignado al estudiante
    //   (canal académico — permisos, tareas, avisos, nunca temas emocionales).
    // - Estudiante con coordinador/rector/administrador: no permitido.
    // - Personal con personal (ninguno es estudiante): siempre permitido.
    // Devuelve el id de conversación, o null si la combinación no está permitida.
    public static function iniciarConversacion(PDO $pdo, int $usuarioAId, int $usuarioBId): ?int
    {
        if ($usuarioAId === $usuarioBId) return null;

        $c = $pdo->prepare('SELECT u.id, r.nombre AS rol, u.grupo_id, u.colegio_id FROM usuarios u INNER JOIN roles r ON r.id=u.rol_id WHERE u.id IN (:a, :b)');
        $c->execute(['a' => $usuarioAId, 'b' => $usuarioBId]);
        $datos = [];
        foreach ($c->fetchAll() as $fila) $datos[(int)$fila['id']] = $fila;
        if (count($datos) !== 2) return null; // alguno de los dos IDs no existe

        $infoA = $datos[$usuarioAId];
        $infoB = $datos[$usuarioBId];
        // Multi-colegio: solo se puede conversar dentro del mismo colegio (el superadmin, sin colegio, no chatea).
        if ($infoA['colegio_id'] === null || $infoB['colegio_id'] === null
            || (int) $infoA['colegio_id'] !== (int) $infoB['colegio_id']) {
            return null;
        }
        $hayEstudiante = ($infoA['rol'] === 'estudiante' || $infoB['rol'] === 'estudiante');

        if ($hayEstudiante) {
            if ($infoA['rol'] === 'estudiante' && $infoB['rol'] === 'estudiante') return null;
            $estudiante = $infoA['rol'] === 'estudiante' ? $infoA : $infoB;
            $otro = $infoA['rol'] === 'estudiante' ? $infoB : $infoA;

            if (in_array($otro['rol'], self::ROLES_QUE_HABLAN_CON_ESTUDIANTES, true)) {
                // Canal emocional: cualquier psicólogo/orientador activo, sin restricción de grupo.
            } elseif ($otro['rol'] === 'docente') {
                // Canal académico: solo el docente asignado al grupo de ESE estudiante.
                if (!$estudiante['grupo_id']) return null;
                $g = $pdo->prepare('SELECT docente_id FROM grupos WHERE id=:gid LIMIT 1');
                $g->execute(['gid' => $estudiante['grupo_id']]);
                $docenteDelGrupo = $g->fetchColumn();
                if ((int)$docenteDelGrupo !== (int)$otro['id']) return null;
            } else {
                // coordinador, rector, administrador: nunca chat directo con estudiantes.
                return null;
            }
        }
        // Si ninguno es estudiante, es personal hablando con personal — siempre permitido.

        // Se guarda siempre en el mismo orden (menor id primero) para que la UNIQUE KEY
        // detecte la conversación sin importar quién la inició.
        [$menor, $mayor] = $usuarioAId < $usuarioBId ? [$usuarioAId, $usuarioBId] : [$usuarioBId, $usuarioAId];

        $existe = $pdo->prepare('SELECT id FROM conversaciones WHERE usuario_a_id=:a AND usuario_b_id=:b LIMIT 1');
        $existe->execute(['a' => $menor, 'b' => $mayor]);
        $conv = $existe->fetchColumn();
        if ($conv) return (int)$conv;

        $pdo->prepare('INSERT INTO conversaciones (usuario_a_id, usuario_b_id) VALUES (?,?)')->execute([$menor, $mayor]);
        return (int)$pdo->lastInsertId();
    }

    public static function perteneceAUsuario(PDO $pdo, int $conversacionId, int $usuarioId): bool
    {
        $c = $pdo->prepare('SELECT id FROM conversaciones WHERE id=:cid AND (usuario_a_id=:uid OR usuario_b_id=:uid2) LIMIT 1');
        $c->execute(['cid' => $conversacionId, 'uid' => $usuarioId, 'uid2' => $usuarioId]);
        return (bool) $c->fetchColumn();
    }

    public static function enviar(PDO $pdo, int $conversacionId, int $emisorId, string $contenido): int
    {
        $c = $pdo->prepare('INSERT INTO mensajes (conversacion_id, emisor_id, contenido) VALUES (?,?,?)');
        $c->execute([$conversacionId, $emisorId, $contenido]);
        return (int)$pdo->lastInsertId();
    }

    public static function listarMensajes(PDO $pdo, int $conversacionId): array
    {
        $c = $pdo->prepare(
            'SELECT m.*, u.nombre AS nombre_emisor FROM mensajes m
             INNER JOIN usuarios u ON u.id=m.emisor_id
             WHERE m.conversacion_id=:cid ORDER BY m.fecha_envio ASC'
        );
        $c->execute(['cid' => $conversacionId]);
        return $c->fetchAll();
    }

    public static function marcarLeidos(PDO $pdo, int $conversacionId, int $receptorId): void
    {
        $pdo->prepare('UPDATE mensajes SET leido=1 WHERE conversacion_id=:cid AND emisor_id!=:rid AND leido=0')
            ->execute(['cid' => $conversacionId, 'rid' => $receptorId]);
    }

    // Todas las conversaciones de un usuario, sea cual sea su rol — el nombre que se
    // muestra es siempre el del OTRO participante, sin importar de qué lado está.
    public static function conversacionesDeUsuario(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare(
            "SELECT c.id,
                    IF(c.usuario_a_id=:uid1, c.usuario_b_id, c.usuario_a_id) AS otro_id,
                    u.nombre AS otro_nombre, r.nombre AS otro_rol,
                    (SELECT contenido FROM mensajes WHERE conversacion_id=c.id ORDER BY fecha_envio DESC LIMIT 1) AS ultimo_msg,
                    (SELECT COUNT(*) FROM mensajes WHERE conversacion_id=c.id AND leido=0 AND emisor_id!=:uid2) AS no_leidos
             FROM conversaciones c
             INNER JOIN usuarios u ON u.id = IF(c.usuario_a_id=:uid3, c.usuario_b_id, c.usuario_a_id)
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE c.usuario_a_id=:uid4 OR c.usuario_b_id=:uid5
             ORDER BY c.fecha_creacion DESC"
        );
        $c->execute(['uid1'=>$usuarioId,'uid2'=>$usuarioId,'uid3'=>$usuarioId,'uid4'=>$usuarioId,'uid5'=>$usuarioId]);
        return $c->fetchAll();
    }

    // Lista de personal (todos los roles menos estudiante) con quien un usuario de
    // personal puede iniciar una conversación nueva — para el selector de personas.
    // Excluye al propio usuario y a quienes ya tiene conversación abierta (esos ya
    // aparecen en la lista de conversaciones existentes).
    public static function personalDisponibleParaChat(PDO $pdo, int $usuarioId): array
    {
        $c = $pdo->prepare(
            "SELECT u.id, u.nombre, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre != 'estudiante' AND r.nombre != 'superadmin' AND u.id != :uid AND u.activo = 1
               AND u.colegio_id = (SELECT colegio_id FROM usuarios WHERE id = :uidcol)
               AND u.id NOT IN (
                   SELECT IF(usuario_a_id=:uid2, usuario_b_id, usuario_a_id) FROM conversaciones
                   WHERE usuario_a_id=:uid3 OR usuario_b_id=:uid4
               )
             ORDER BY r.nombre, u.nombre"
        );
        $c->execute(['uid'=>$usuarioId,'uidcol'=>$usuarioId,'uid2'=>$usuarioId,'uid3'=>$usuarioId,'uid4'=>$usuarioId]);
        return $c->fetchAll();
    }

    // Lista de psicólogo/orientador disponibles para que un ESTUDIANTE inicie chat nuevo.
    public static function profesionalesDisponiblesParaEstudiante(PDO $pdo, int $estudianteId): array
    {
        $c = $pdo->prepare(
            "SELECT u.id, u.nombre, r.nombre AS rol
             FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id
             WHERE r.nombre IN ('psicologo','orientador') AND u.activo = 1
               AND u.colegio_id = (SELECT colegio_id FROM usuarios WHERE id = :uidcol)
               AND u.id NOT IN (
                   SELECT IF(usuario_a_id=:uid1, usuario_b_id, usuario_a_id) FROM conversaciones
                   WHERE usuario_a_id=:uid2 OR usuario_b_id=:uid3
               )
             ORDER BY r.nombre, u.nombre"
        );
        $c->execute(['uid1'=>$estudianteId,'uidcol'=>$estudianteId,'uid2'=>$estudianteId,'uid3'=>$estudianteId]);
        return $c->fetchAll();
    }

    // El docente de grupo asignado a este estudiante (canal académico), o null si el
    // estudiante no tiene grupo o el grupo no tiene docente asignado todavía.
    public static function docenteDeGrupoDeEstudiante(PDO $pdo, int $estudianteId): ?array
    {
        $c = $pdo->prepare(
            "SELECT du.id, du.nombre FROM usuarios est
             INNER JOIN grupos g ON g.id = est.grupo_id
             INNER JOIN usuarios du ON du.id = g.docente_id
             WHERE est.id = :eid AND du.activo = 1 LIMIT 1"
        );
        $c->execute(['eid' => $estudianteId]);
        return $c->fetch() ?: null;
    }
}
