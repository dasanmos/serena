<?php
declare(strict_types=1);

function enlacesNavegacionPorRol(string $rol): array
{
    $enlaces = [
        'estudiante' => [
            ['texto' => 'Inicio',         'url' => BASE_URL . '/estudiante/dashboard.php',      'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Mis notas', 'url' => BASE_URL . '/boletin.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiante_ficha.php', 'icono' => 'icono_perfil.png', 'categoria' => 'vista'],
            ['texto' => 'Ayuda urgente',  'url' => BASE_URL . '/estudiante/ayuda_urgente.php',  'icono' => 'icono_urgente.png', 'categoria' => 'vista'],
            ['texto' => 'Estadísticas',   'url' => BASE_URL . '/estudiante/estadisticas.php',   'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Calendario',     'url' => BASE_URL . '/estudiante/calendario.php',     'icono' => 'icono_calendario.png', 'categoria' => 'vista'],
            ['texto' => 'Notificaciones', 'url' => BASE_URL . '/estudiante/notificaciones.php', 'icono' => 'icono_notificaciones.png', 'categoria' => 'vista'],
            ['texto' => 'Emociones',      'url' => BASE_URL . '/estudiante/emociones.php',      'icono' => 'icono_emociones.png', 'categoria' => 'modulo'],
            ['texto' => 'Sueño',          'url' => BASE_URL . '/estudiante/sueno.php',          'icono' => 'icono_sueno.png', 'categoria' => 'modulo'],
            ['texto' => 'Diario',         'url' => BASE_URL . '/estudiante/diario.php',         'icono' => 'icono_diario.png', 'categoria' => 'modulo'],
            ['texto' => 'Hábitos',        'url' => BASE_URL . '/estudiante/habitos.php',        'icono' => 'icono_habitos.png', 'categoria' => 'modulo'],
            ['texto' => 'Metas',          'url' => BASE_URL . '/estudiante/metas.php',          'icono' => 'icono_metas.png', 'categoria' => 'modulo'],
            ['texto' => 'Recordatorios',  'url' => BASE_URL . '/estudiante/recordatorios.php',  'icono' => 'icono_recordatorios.png', 'categoria' => 'modulo'],
            ['texto' => 'Citas',          'url' => BASE_URL . '/estudiante/citas.php',          'icono' => 'icono_citas.png', 'categoria' => 'modulo'],
            ['texto' => 'Encuestas',      'url' => BASE_URL . '/estudiante/encuestas.php',      'icono' => 'icono_encuestas.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/estudiante/mensajes.php',           'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Recursos',       'url' => BASE_URL . '/estudiante/recursos_apoyo.php', 'icono' => 'icono_recursos.png', 'categoria' => 'modulo'],
            ['texto' => 'Respiración',    'url' => BASE_URL . '/estudiante/respiracion.php',    'icono' => 'icono_respiracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/estudiante/perfil.php',         'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi asistencia', 'url' => BASE_URL . '/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'vista'],
            ['texto' => 'Mi horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'vista'],
        ],
        'psicologo' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/psicologo/panel.php',      'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Casos', 'url' => BASE_URL . '/casos.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Reportes',       'url' => BASE_URL . '/psicologo/reportes.php',   'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Estudiantes',    'url' => BASE_URL . '/psicologo/estudiantes.php', 'icono' => 'icono_estudiantes.png', 'categoria' => 'modulo'],
            ['texto' => 'Agenda',         'url' => BASE_URL . '/psicologo/agenda.php',      'icono' => 'icono_agenda.png', 'categoria' => 'modulo'],
            ['texto' => 'Alertas',        'url' => BASE_URL . '/psicologo/alertas.php',     'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimientos',   'url' => BASE_URL . '/psicologo/seguimientos.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Encuestas',      'url' => BASE_URL . '/psicologo/encuestas.php',   'icono' => 'icono_encuestas.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/psicologo/mensajes.php',       'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/psicologo/perfil.php',      'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
        ],
        'orientador' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/orientador/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Casos', 'url' => BASE_URL . '/casos.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi grupo',       'url' => BASE_URL . '/orientador/grupo.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Agenda',         'url' => BASE_URL . '/psicologo/agenda.php', 'icono' => 'icono_agenda.png', 'categoria' => 'modulo'],
            ['texto' => 'Alertas',        'url' => BASE_URL . '/orientador/alertas.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimientos',   'url' => BASE_URL . '/orientador/seguimientos.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/orientador/mensajes.php',  'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/orientador/perfil.php',    'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
        ],
        'docente' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/docente/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Mi grupo',       'url' => BASE_URL . '/docente/grupo.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimiento', 'url' => BASE_URL . '/docente/seguimiento.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Asistencia', 'url' => BASE_URL . '/docente/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Planeador', 'url' => BASE_URL . '/docente/planeador.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Pulso de grupo', 'url' => BASE_URL . '/docente/pulso.php', 'icono' => 'icono_pulso.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/docente/mensajes.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/docente/perfil.php',   'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Reporte de asistencia', 'url' => BASE_URL . '/asistencia_reporte.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
        ],
        'coordinador' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/coordinador/panel.php',    'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Reportes',       'url' => BASE_URL . '/coordinador/reportes.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Boletines', 'url' => BASE_URL . '/secretaria_academica/boletines.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Casos', 'url' => BASE_URL . '/casos.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/coordinador/mensajes.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/coordinador/perfil.php',   'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Horarios', 'url' => BASE_URL . '/horarios_admin.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Reporte de asistencia', 'url' => BASE_URL . '/asistencia_reporte.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
        ],
        'secretaria_academica' => [
            ['texto' => 'Panel', 'url' => BASE_URL . '/secretaria_academica/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Inscripciones', 'url' => BASE_URL . '/secretaria_academica/inscripciones.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Matrículas', 'url' => BASE_URL . '/secretaria_academica/matriculas.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Años lectivos', 'url' => BASE_URL . '/administrador/anios_lectivos.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Plan académico', 'url' => BASE_URL . '/administrador/plan_academico.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Evaluación', 'url' => BASE_URL . '/administrador/evaluacion_config.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimiento', 'url' => BASE_URL . '/docente/seguimiento.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Boletines', 'url' => BASE_URL . '/secretaria_academica/boletines.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Certificados', 'url' => BASE_URL . '/secretaria_academica/certificados.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Horarios', 'url' => BASE_URL . '/horarios_admin.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Asistencia', 'url' => BASE_URL . '/docente/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Reporte de asistencia', 'url' => BASE_URL . '/asistencia_reporte.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
        ],
        'acudiente' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/acudiente/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Boletines', 'url' => BASE_URL . '/boletin.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiante_ficha.php', 'icono' => 'icono_perfil.png', 'categoria' => 'vista'],
            ['texto' => 'Asistencia', 'url' => BASE_URL . '/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'vista'],
            ['texto' => 'Horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'vista'],
        ],
        'rector' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/rector/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Años lectivos',  'url' => BASE_URL . '/administrador/anios_lectivos.php',       'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Inscripciones',  'url' => BASE_URL . '/secretaria_academica/inscripciones.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Matrículas',     'url' => BASE_URL . '/secretaria_academica/matriculas.php',    'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Plan académico', 'url' => BASE_URL . '/administrador/plan_academico.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Evaluación', 'url' => BASE_URL . '/administrador/evaluacion_config.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimiento', 'url' => BASE_URL . '/docente/seguimiento.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Boletines', 'url' => BASE_URL . '/secretaria_academica/boletines.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Institución', 'url' => BASE_URL . '/administrador/institucion.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Certificados', 'url' => BASE_URL . '/secretaria_academica/certificados.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Casos', 'url' => BASE_URL . '/casos.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/rector/mensajes.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/rector/perfil.php',   'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Horarios', 'url' => BASE_URL . '/horarios_admin.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Asistencia', 'url' => BASE_URL . '/docente/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Reporte de asistencia', 'url' => BASE_URL . '/asistencia_reporte.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Plantillas de certificados', 'url' => BASE_URL . '/administrador/certificados_plantillas.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
        ],
        'secretaria' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/secretaria/panel.php',    'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Comparativo',    'url' => BASE_URL . '/secretaria/comparativo.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Reportes',       'url' => BASE_URL . '/secretaria/reportes.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'vista'],
            ['texto' => 'Colegios',       'url' => BASE_URL . '/secretaria/colegios.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Personal',       'url' => BASE_URL . '/secretaria/personal.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Trasladar estudiante', 'url' => BASE_URL . '/secretaria/trasladar_estudiante.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Comunicados',    'url' => BASE_URL . '/secretaria/comunicados.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Importar colegios', 'url' => BASE_URL . '/secretaria/colegios_importar.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Territorio', 'url' => BASE_URL . '/secretaria/territorio.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/secretaria/perfil.php',   'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
        ],
        'superadmin' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/superadmin/panel.php',    'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Comparativo',    'url' => BASE_URL . '/superadmin/comparativo.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'vista'],
            ['texto' => 'Reportes',       'url' => BASE_URL . '/superadmin/reportes.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'vista'],
            ['texto' => 'Auditoría',      'url' => BASE_URL . '/administrador/auditoria.php', 'icono' => 'icono_auditoria.png', 'categoria' => 'vista'],
            ['texto' => 'Colegios',       'url' => BASE_URL . '/superadmin/colegios.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Personal',       'url' => BASE_URL . '/superadmin/personal.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Trasladar estudiante', 'url' => BASE_URL . '/superadmin/trasladar_estudiante.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Comunicados',    'url' => BASE_URL . '/superadmin/comunicados.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Secretarías',    'url' => BASE_URL . '/superadmin/secretarias.php', 'icono' => 'icono_aprobaciones.png', 'categoria' => 'modulo'],
            ['texto' => 'Importar colegios', 'url' => BASE_URL . '/superadmin/colegios_importar.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Territorio', 'url' => BASE_URL . '/superadmin/territorio.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Lugares de ayuda', 'url' => BASE_URL . '/administrador/lugares_apoyo.php', 'icono' => 'icono_recursos.png', 'categoria' => 'modulo'],
            ['texto' => 'Salud del sistema', 'url' => BASE_URL . '/administrador/salud.php', 'icono' => 'icono_salud.png', 'categoria' => 'modulo'],
            ['texto' => 'Respaldos y ajustes', 'url' => BASE_URL . '/administrador/configuracion.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Datos de prueba', 'url' => BASE_URL . '/administrador/generar_datos_prueba.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/superadmin/perfil.php',   'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Diagnóstico de IA', 'url' => BASE_URL . '/administrador/prueba_ia.php', 'icono' => 'icono_salud.png', 'categoria' => 'modulo'],
        ],
        'administrador' => [
            ['texto' => 'Panel',          'url' => BASE_URL . '/administrador/panel.php', 'icono' => 'icono_inicio.png', 'categoria' => 'vista'],
            ['texto' => 'Auditoría',      'url' => BASE_URL . '/administrador/auditoria.php', 'icono' => 'icono_auditoria.png', 'categoria' => 'vista'],
            ['texto' => 'Ver como usuario', 'url' => BASE_URL . '/administrador/ver_como.php', 'icono' => 'icono_ver_como.png', 'categoria' => 'modulo'],
            ['texto' => 'Usuarios',       'url' => BASE_URL . '/administrador/usuarios.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Grupos',         'url' => BASE_URL . '/administrador/grupos.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Promoción de año', 'url' => BASE_URL . '/administrador/promocion.php', 'icono' => 'icono_promocion.png', 'categoria' => 'modulo'],
            ['texto' => 'Asignar grupos', 'url' => BASE_URL . '/administrador/asignar_grupos_personal.php', 'icono' => 'icono_asignar.png', 'categoria' => 'modulo'],
            ['texto' => 'Años lectivos', 'url' => BASE_URL . '/administrador/anios_lectivos.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Inscripciones', 'url' => BASE_URL . '/secretaria_academica/inscripciones.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Matrículas', 'url' => BASE_URL . '/secretaria_academica/matriculas.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Plan académico', 'url' => BASE_URL . '/administrador/plan_academico.php', 'icono' => 'icono_grupo.png', 'categoria' => 'modulo'],
            ['texto' => 'Evaluación', 'url' => BASE_URL . '/administrador/evaluacion_config.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Seguimiento', 'url' => BASE_URL . '/docente/seguimiento.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Boletines', 'url' => BASE_URL . '/secretaria_academica/boletines.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Institución', 'url' => BASE_URL . '/administrador/institucion.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Certificados', 'url' => BASE_URL . '/secretaria_academica/certificados.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Hoja de vida', 'url' => BASE_URL . '/estudiantes.php', 'icono' => 'icono_usuarios.png', 'categoria' => 'modulo'],
            ['texto' => 'Casos', 'url' => BASE_URL . '/casos.php', 'icono' => 'icono_alertas.png', 'categoria' => 'modulo'],
            ['texto' => 'Importar estudiantes', 'url' => BASE_URL . '/administrador/importar_estudiantes.php', 'icono' => 'icono_importar.png', 'categoria' => 'modulo'],
            ['texto' => 'Aprobaciones',   'url' => BASE_URL . '/administrador/aprobaciones.php', 'icono' => 'icono_aprobaciones.png', 'categoria' => 'modulo'],
            ['texto' => 'Resetear contraseña', 'url' => BASE_URL . '/administrador/resetear_password.php', 'icono' => 'icono_password.png', 'categoria' => 'modulo'],
            ['texto' => 'Lugares de ayuda', 'url' => BASE_URL . '/administrador/lugares_apoyo.php', 'icono' => 'icono_recursos.png', 'categoria' => 'modulo'],
            ['texto' => 'Configuración', 'url' => BASE_URL . '/administrador/configuracion.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
            ['texto' => 'Salud del sistema', 'url' => BASE_URL . '/administrador/salud.php', 'icono' => 'icono_salud.png', 'categoria' => 'modulo'],
            ['texto' => 'Chat',           'url' => BASE_URL . '/administrador/mensajes.php', 'icono' => 'icono_mensajes.png', 'categoria' => 'modulo'],
            ['texto' => 'Mi perfil',      'url' => BASE_URL . '/administrador/perfil.php', 'icono' => 'icono_perfil.png', 'categoria' => 'modulo'],
            ['texto' => 'Horarios', 'url' => BASE_URL . '/horarios_admin.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Horario', 'url' => BASE_URL . '/horario.php', 'icono' => 'icono_calendario.png', 'categoria' => 'modulo'],
            ['texto' => 'Asistencia', 'url' => BASE_URL . '/docente/asistencia.php', 'icono' => 'icono_seguimiento.png', 'categoria' => 'modulo'],
            ['texto' => 'Reporte de asistencia', 'url' => BASE_URL . '/asistencia_reporte.php', 'icono' => 'icono_estadisticas.png', 'categoria' => 'modulo'],
            ['texto' => 'Plantillas de certificados', 'url' => BASE_URL . '/administrador/certificados_plantillas.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'],
        ],
    ];
    $lista = $enlaces[$rol] ?? [];
    if ($lista !== []) {
        // Módulo de informes y sus capturas (cada página vuelve a comprobar el rol; esto solo decide qué enlaces se muestran).
        $extrasInformes = [
            ['Informes', '/informes.php', 'icono_estadisticas.png', ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'docente', 'psicologo', 'orientador']],
            ['Refuerzos y planes', '/refuerzos.php', 'icono_seguimiento.png', ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'docente']],
            ['SIMAT', '/simat.php', 'icono_estadisticas.png', ['secretaria_academica', 'administrador', 'rector']],
            ['SIMPADE', '/simpade.php', 'icono_seguimiento.png', ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'psicologo', 'orientador']],
            ['Caracterización', '/caracterizacion.php', 'icono_usuarios.png', ['secretaria_academica', 'administrador', 'rector', 'coordinador', 'psicologo', 'orientador']],
            ['Restaurante', '/restaurante.php', 'icono_recursos.png', ['secretaria_academica', 'administrador', 'rector', 'coordinador']],
            ['Cartera', '/cartera.php', 'icono_estadisticas.png', ['secretaria_academica', 'administrador', 'rector']],
        ];
        foreach ($extrasInformes as [$texto, $ruta, $icono, $roles]) {
            if (in_array($rol, $roles, true)) {
                $lista[] = ['texto' => $texto, 'url' => BASE_URL . $ruta, 'icono' => $icono, 'categoria' => 'modulo'];
            }
        }
        // Verificación en 2 pasos: disponible para todos los roles (opcional, la activa cada persona).
        $lista[] = ['texto' => 'Seguridad', 'url' => BASE_URL . '/seguridad.php', 'icono' => 'icono_password.png', 'categoria' => 'modulo'];
        // Dispositivos con sesión activa y cierre de sesión remota: igual, disponible para todos los roles.
        $lista[] = ['texto' => 'Mis sesiones', 'url' => BASE_URL . '/sesiones.php', 'icono' => 'icono_sesiones.png', 'categoria' => 'modulo'];
        // Tema y notificaciones por correo: igual, disponible para todos los roles.
        $lista[] = ['texto' => 'Preferencias', 'url' => BASE_URL . '/preferencias.php', 'icono' => 'icono_configuracion.png', 'categoria' => 'modulo'];
        // Centro de ayuda: guía del rol de quien entra, descargable en PDF y HTML.
        $lista[] = ['texto' => 'Ayuda', 'url' => BASE_URL . '/ayuda.php', 'icono' => 'icono_recursos.png', 'categoria' => 'modulo'];
    }
    return $lista;
}

// Separa los enlaces de un rol en "vistas" (paneles de resumen/lectura) y "módulos"
// (donde se gestiona o registra algo puntual) — usado en la fila de pestañas de la
// barra superior. La clasificación viene del propio array de arriba (clave 'categoria');
// cualquier enlace sin categoría explícita cae en 'modulo' por defecto.
function enlacesPorCategoria(string $rol): array
{
    $vistas = [];
    $modulos = [];
    foreach (enlacesNavegacionPorRol($rol) as $enlace) {
        if (($enlace['categoria'] ?? 'modulo') === 'vista') {
            $vistas[] = $enlace;
        } else {
            $modulos[] = $enlace;
        }
    }
    return ['vistas' => $vistas, 'modulos' => $modulos];
}

// Categorías del menú lateral: por rol, en el orden en que se muestran, cada una con los textos de sus módulos.
// Lo que un rol tenga y no aparezca aquí cae en «Cuenta» (perfil, seguridad, sesiones, preferencias y ayuda).
function categoriasNavegacionPorRol(string $rol): array
{
    $mapa = [
        'estudiante' => [
            'Inicio' => ['Inicio', 'Calendario', 'Notificaciones'],
            'Académico' => ['Mis notas', 'Mi horario', 'Mi asistencia', 'Hoja de vida', 'Estadísticas'],
            'Bienestar' => ['Emociones', 'Sueño', 'Diario', 'Hábitos', 'Respiración', 'Ayuda urgente', 'Recursos'],
            'Organización' => ['Metas', 'Recordatorios', 'Citas'],
            'Comunicación' => ['Encuestas', 'Chat'],
        ],
        'acudiente' => [
            'Inicio' => ['Panel'],
            'Mi estudiante' => ['Hoja de vida', 'Boletines', 'Asistencia', 'Horario'],
        ],
        'docente' => [
            'Inicio' => ['Panel'],
            'Clase' => ['Mi grupo', 'Asistencia', 'Reporte de asistencia', 'Planeador', 'Seguimiento', 'Mi horario'],
            'Informes' => ['Informes', 'Refuerzos y planes'],
            'Estudiantes' => ['Hoja de vida', 'Pulso de grupo'],
            'Comunicación' => ['Chat'],
        ],
        'psicologo' => [
            'Inicio' => ['Panel', 'Reportes'],
            'Estudiantes' => ['Hoja de vida', 'Estudiantes', 'Casos', 'Alertas', 'Seguimientos', 'Caracterización'],
            'Informes' => ['Informes', 'SIMPADE'],
            'Agenda' => ['Agenda', 'Encuestas'],
            'Comunicación' => ['Chat'],
        ],
        'orientador' => [
            'Inicio' => ['Panel'],
            'Estudiantes' => ['Hoja de vida', 'Mi grupo', 'Casos', 'Alertas', 'Seguimientos', 'Caracterización'],
            'Informes' => ['Informes', 'SIMPADE'],
            'Agenda' => ['Agenda'],
            'Comunicación' => ['Chat'],
        ],
        'coordinador' => [
            'Inicio' => ['Panel', 'Reportes'],
            'Estudiantes' => ['Hoja de vida', 'Casos', 'Caracterización'],
            'Académico' => ['Boletines', 'Refuerzos y planes'],
            'Horarios y asistencia' => ['Horarios', 'Horario', 'Reporte de asistencia'],
            'Informes' => ['Informes', 'SIMPADE'],
            'Servicios' => ['Restaurante'],
            'Comunicación' => ['Chat'],
        ],
        'secretaria_academica' => [
            'Inicio' => ['Panel'],
            'Matrícula' => ['Inscripciones', 'Matrículas'],
            'Estudiantes' => ['Hoja de vida', 'Caracterización'],
            'Académico' => ['Años lectivos', 'Plan académico', 'Evaluación', 'Seguimiento', 'Refuerzos y planes', 'Boletines', 'Certificados'],
            'Horarios y asistencia' => ['Horarios', 'Horario', 'Asistencia', 'Reporte de asistencia'],
            'Informes' => ['Informes', 'SIMAT', 'SIMPADE'],
            'Servicios' => ['Restaurante', 'Cartera'],
        ],
        'rector' => [
            'Inicio' => ['Panel'],
            'Estudiantes' => ['Hoja de vida', 'Casos', 'Caracterización'],
            'Matrícula' => ['Inscripciones', 'Matrículas'],
            'Académico' => ['Años lectivos', 'Plan académico', 'Evaluación', 'Seguimiento', 'Refuerzos y planes', 'Boletines', 'Certificados', 'Plantillas de certificados'],
            'Horarios y asistencia' => ['Horarios', 'Horario', 'Asistencia', 'Reporte de asistencia'],
            'Informes' => ['Informes', 'SIMAT', 'SIMPADE'],
            'Servicios' => ['Restaurante', 'Cartera'],
            'Institución' => ['Institución'],
            'Comunicación' => ['Chat'],
        ],
        'administrador' => [
            'Inicio' => ['Panel'],
            'Estudiantes' => ['Hoja de vida', 'Casos', 'Caracterización', 'Importar estudiantes', 'Promoción de año'],
            'Matrícula' => ['Inscripciones', 'Matrículas', 'Aprobaciones'],
            'Académico' => ['Años lectivos', 'Plan académico', 'Evaluación', 'Seguimiento', 'Refuerzos y planes', 'Boletines', 'Certificados', 'Plantillas de certificados'],
            'Horarios y asistencia' => ['Horarios', 'Horario', 'Asistencia', 'Reporte de asistencia'],
            'Informes' => ['Informes', 'SIMAT', 'SIMPADE'],
            'Servicios' => ['Restaurante', 'Cartera'],
            'Institución' => ['Institución', 'Grupos', 'Asignar grupos', 'Usuarios'],
            'Sistema' => ['Auditoría', 'Ver como usuario', 'Resetear contraseña', 'Lugares de ayuda', 'Configuración', 'Salud del sistema'],
            'Comunicación' => ['Chat'],
        ],
        'secretaria' => [
            'Inicio' => ['Panel', 'Comparativo', 'Reportes'],
            'Estudiantes' => ['Hoja de vida', 'Trasladar estudiante'],
            'Colegios' => ['Colegios', 'Importar colegios', 'Territorio', 'Personal'],
            'Comunicación' => ['Comunicados'],
        ],
        'superadmin' => [
            'Inicio' => ['Panel', 'Comparativo', 'Reportes'],
            'Estudiantes' => ['Hoja de vida', 'Trasladar estudiante'],
            'Plataforma' => ['Colegios', 'Personal', 'Secretarías', 'Importar colegios', 'Territorio', 'Comunicados'],
            'Sistema' => ['Auditoría', 'Lugares de ayuda', 'Salud del sistema', 'Diagnóstico de IA', 'Respaldos y ajustes', 'Datos de prueba'],
        ],
    ];
    return $mapa[$rol] ?? [];
}

/**
 * Agrupa los enlaces de un rol por categoría para el menú lateral.
 * @return array<int, array{titulo:string, enlaces:array}>
 */
function gruposNavegacion(string $rol, ?array $enlaces = null): array
{
    $enlaces ??= enlacesNavegacionPorRol($rol);
    $porTexto = [];
    foreach ($enlaces as $enlace) {
        $porTexto[$enlace['texto']][] = $enlace;
    }
    $grupos = [];
    foreach (categoriasNavegacionPorRol($rol) as $titulo => $textos) {
        $items = [];
        foreach ($textos as $t) {
            foreach ($porTexto[$t] ?? [] as $enlace) {
                $items[] = $enlace;
            }
        }
        if ($items) {
            $grupos[] = ['titulo' => $titulo, 'enlaces' => $items];
        }
    }
    $asignados = [];
    foreach ($grupos as $g) {
        foreach ($g['enlaces'] as $enlace) { $asignados[$enlace['texto'] . '|' . $enlace['url']] = true; }
    }
    $cuenta = [];
    foreach ($enlaces as $enlace) {
        if (!isset($asignados[$enlace['texto'] . '|' . $enlace['url']])) { $cuenta[] = $enlace; }
    }
    if ($cuenta) {
        $grupos[] = ['titulo' => 'Cuenta', 'enlaces' => $cuenta];
    }
    return $grupos;
}

/** ¿Este enlace es la página actual? La hoja de vida abre en estudiante_ficha.php, que también cuenta como el directorio. */
function enlaceNavegacionActivo(array $enlace, string $rutaActual): bool
{
    $destino = basename((string) parse_url((string) $enlace['url'], PHP_URL_PATH));
    if ($destino === $rutaActual) {
        return true;
    }
    return $destino === 'estudiantes.php' && $rutaActual === 'estudiante_ficha.php';
}

// Comprueba si el archivo de ícono realmente existe en disco antes de intentar
// mostrarlo. Así, mientras se van generando los assets, un ícono que todavía no
// existe simplemente no se dibuja (ni imagen rota ni emoji de relleno) en vez de
// mostrar un error visual.
function existeIconoRecurso(string $nombreArchivo): bool
{
    static $cache = [];
    if (isset($cache[$nombreArchivo])) {
        return $cache[$nombreArchivo];
    }
    $ruta = __DIR__ . '/../../public/assets/img/recursos/' . $nombreArchivo;
    return $cache[$nombreArchivo] = is_file($ruta);
}
