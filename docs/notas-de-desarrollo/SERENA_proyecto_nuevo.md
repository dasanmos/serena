# Proyecto SERENA — Plataforma de Bienestar Emocional Institucional
 
## Equipo
- **David Santiago Mosquera Mosquera** y **Ana Sofía Ángel Moncada**
- Certificación con el SENA, profesora Wendy (pendiente registrar a David como segundo integrante)
- Trabajan todos juntos, no dividido por módulos separados
- **Este es un proyecto NUEVO, desde cero** — no se continúa sobre el código PHP anterior (`serenabyana`), aunque sirve como referencia de lo aprendido
## Fechas clave
- Entrega oficial al SENA: **octubre**
- Meta interna: sistema funcionando **antes del 1 de septiembre**, para lanzarlo a la institución y recopilar datos reales de uso antes de la entrega
- Alcance de entrega: el proyecto **completo**, no una versión mínima
## Visión y alcance
Plataforma web de bienestar emocional dirigida a **toda la institución educativa** (no solo estudiantes del SENA), desde **grado sexto en adelante** (~10-11 años). No se requiere autorización de un adulto para que un menor se registre. Meta de usuarios: toda la institución (potencialmente 1000+ estudiantes).
 
## Roles del sistema
1. **Administrador** — gestión general, crea grupos, importa estudiantes, aprueba cambios de datos sensibles, resetea contraseñas
2. **Estudiante** — usuario principal: emociones, sueño, diario, hábitos, metas, perfil
3. **Psicólogo** — ve datos emocionales de estudiantes, lanza encuestas, recibe alertas tempranas
4. **Profesor orientador** — ve datos emocionales de su grupo/grado, recibe alertas
5. **Docente de aula / director de grupo** — seguimiento de su grupo asignado
6. **Coordinador de convivencia** — reportes agregados por grado/institución
7. **Rector** — visión general institucional (reportes agregados, no datos individuales)
## Marco teórico (heredado del proyecto original de Ana)
- **Problema**: estrés, ansiedad, falta de motivación y desorganización en jóvenes por responsabilidades académicas/personales; faltan apps que unifiquen seguimiento emocional, hábitos, metas y diario en un solo lugar
- **Referentes**: Daylio (seguimiento emocional + estadísticas), Habitica (gamificación de hábitos), Reflectly (diario con IA)
- **Marco legal**: Ley 1581 de 2012 (Colombia, protección de datos personales) — el sistema pedirá **consentimiento explícito** al registrarse
- **Metodología**: cascada (Análisis → Diseño → Desarrollo → Pruebas → Implementación)
## Módulos funcionales
 
### Registro emocional
- Varios registros al día (mañana/tarde/noche), editable
- Más allá de 5 niveles básicos: incluir emociones como ansioso, motivado, cansado, etc.
- Nota/observación asociada a cada registro
- Notificación si el usuario no registra en el día
### Sueño
- Horas de sueño, calidad (mala/regular/buena), hora de dormir y hora de despertar
- Relación entre sueño y estado emocional en las estadísticas
### Diario personal
- Texto libre, con imágenes adjuntas
- Editable y eliminable
- Búsqueda/filtro por fecha
- Protección adicional con PIN propio
### Hábitos
- Frecuencia diaria, semanal o personalizada
- Editables y eliminables, sin límite de cantidad
- Historial mensual de cumplimiento
- Categorías (salud, estudio, mental, etc.)
### Metas
- Corto o largo plazo, con subtareas
- Porcentaje de avance + estado (activa/completada)
- Si vence sin completarse: preguntar al usuario si continúa o reinicia
- Se pueden vincular a hábitos
### Perfil de usuario (tipo dashboard)
- Nombre, edad, foto, correo, teléfono (opcional), grado, grupo
- Admin crea grupos e importa estudiantes (o estudiantes se autoasignan a su grupo)
- Cambios de datos requieren aprobación del administrador (notificación al admin)
- Opción de eliminar cuenta y todos sus datos
### Estadísticas
- Todos los tipos de gráficos (barras, líneas, torta, circular)
- Por periodo seleccionable con calendario: diario, semanal, mensual, anual, rango libre
- Comparación entre semanas (opcional para el usuario)
- Informe descargable en buen formato
### Privacidad y datos
- Datos emocionales visibles para: psicólogo, profesor orientador, administrador
- Recuperación de contraseña por correo + reseteo manual por admin
- Consentimiento de datos explícito en el registro (Ley 1581)
## Funciones adicionales aprobadas
1. Dashboard de bienvenida con resumen del día al iniciar sesión
2. Panel del psicólogo/orientador con alertas por estudiante o grupo
3. Alertas tempranas automáticas si hay varios días seguidos de emociones muy bajas
4. Chat interno estudiante–psicólogo/orientador
5. Calendario integrado (emociones + hábitos + metas superpuestos)
6. Sistema de grupos/cursos (admin crea grados y grupos, docente ve solo el suyo)
7. Encuestas periódicas lanzadas por el psicólogo
8. Reportes agregados anónimos para administrador/rector/coordinador
9. Recordatorios configurables por el usuario (hora elegida) — incluye avisos para registrar emoción, tomar agua, dormir a cierta hora
10. Bitácora de auditoría de cambios de datos sensibles aprobados por el admin
11. Racha (streaks) — días consecutivos cumpliendo un hábito o registrando emoción
12. Insignias/logros — gamificación ligera (7 días seguidos, primer mes, etc.)
13. Exportar reportes — PDF/imagen con el resumen semanal o mensual, para llevar a un psicólogo si el usuario quiere
14. Recursos de apoyo — artículos, ejercicios de respiración, contactos de líneas de ayuda emocional
15. Comparativa mensual — evolución mes a mes, no solo semana actual
16. Etiquetas en el diario — por tema (familia, estudio, amigos) para filtrar después
17. Modo oscuro — accesibilidad visual
18. Alertas de bienestar — si hay varios días seguidos de emociones muy bajas, sugerir un recurso de ayuda (sin diagnosticar, solo sugerir)
## Diseño
- Paleta: tonos amarillos/cálidos (asociados a tranquilidad y serenidad)
- Logo: lo crea Ana, junto con variantes/iconos derivados
- Responsive: celular, tablet y computador (menú tipo hamburguesa en móvil)
## Decisión técnica (stack)
- **PHP + MySQL + HTML/CSS/JS**
- Se descarta Firebase por costo a gran escala (proyecto debe ser 100% gratuito)
- Hosting: gratuito, aún por definir (pendiente que Claude ayude a elegir uno que soporte bien base de datos + backend PHP a la escala esperada)
- Repositorio nuevo en GitHub, desde cero
## Lecciones del intento anterior (código de Ana, `serenabyana`)
- El login/registro no usaba sentencias preparadas → riesgo de inyección SQL; en el proyecto nuevo usar PDO/mysqli con prepared statements desde el inicio
- Faltaba el módulo de perfil de usuario
- Sirve como referencia de las tablas base: usuarios, estado_emocional, sueño, hábitos, seguimiento_habitos, metas, diario — pero el modelo de datos nuevo debe ampliarse para roles, grupos, notificaciones, chat y auditoría
## Qué se necesita del Proyecto de Claude
- Definir el modelo de base de datos completo (usuarios, roles, grupos, emociones, sueño, diario, hábitos, metas, notificaciones, chat, auditoría)
- Recomendar un hosting gratuito viable para la escala esperada (1000+ usuarios)
- Ir construyendo el backend PHP seguro (prepared statements, hashing, sesiones) y el frontend responsive
- Diseñar el sistema de roles y permisos
- Apoyar en la implementación de estadísticas/gráficos y reportes descargables
