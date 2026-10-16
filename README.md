<div align="center">

<img src="public/assets/img/logo/logo.png" alt="Logo de Serena" width="180">

# Serena

### Plataforma web académica y de bienestar emocional para toda la institución educativa

*Cuerpo · Mente · Bienestar*

[![Demo en vivo](https://img.shields.io/badge/Demo-en%20vivo-39A900?style=for-the-badge&logo=googlechrome&logoColor=white)](https://serena.infinityfreeapp.com/)
[![Presentación](https://img.shields.io/badge/Presentaci%C3%B3n-SENA-F5B700?style=for-the-badge&logo=microsoftpowerpoint&logoColor=white)](docs/Presentacion_Serena_SENA_final.pptx)

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?logo=javascript&logoColor=black)
![Sin frameworks](https://img.shields.io/badge/frameworks-ninguno-39A900)
![Costo](https://img.shields.io/badge/costo%20para%20la%20instituci%C3%B3n-%240-39A900)

**[Ver la demostración en vivo →](https://serena.infinityfreeapp.com/)**

</div>

---

## Tabla de contenido

- [Sobre el proyecto](#sobre-el-proyecto)
- [Demostración en vivo](#demostraci%C3%B3n-en-vivo)
- [Qué incluye](#qu%C3%A9-incluye)
- [Los 11 roles](#los-11-roles)
- [Seguridad y privacidad](#seguridad-y-privacidad)
- [Tecnología](#tecnolog%C3%ADa)
- [Estructura del repositorio](#estructura-del-repositorio)
- [Instalación local](#instalaci%C3%B3n-local)
- [Documentación](#documentaci%C3%B3n)
- [Equipo](#equipo)

---

## Sobre el proyecto

Estrés, ansiedad, falta de motivación y desorganización afectan a muchos jóvenes por sus responsabilidades académicas y personales. Existen aplicaciones que miden emociones, hábitos o diario **por separado**, y plataformas académicas que solo gestionan notas y matrículas, pero faltaba una herramienta que lo unificara.

**Serena reúne ambas cosas en un solo lugar y conecta al estudiante con psicología y orientación:**

| | |
|---|---|
| 🧑‍🎓 **Estudiante** | Registra cómo se siente, cómo duerme y sus hábitos; consulta sus notas y asistencia. |
| 🧠 **Psicología y orientación** | Reciben alertas tempranas y acompañan por chat, citas y encuestas. |
| 🏫 **Docentes y secretaría** | Gestionan matrículas, notas, asistencia, horarios, certificados e informes. |
| 🏛️ **Coordinación y rectoría** | Ven informes agregados, sin datos individuales de bienestar. |

Está pensada para toda la institución **desde grado sexto**, es **gratuita** (PHP y MySQL libres, hosting gratuito) y funciona en celular, tablet y computador.

## Demostración en vivo

🔗 **https://serena.infinityfreeapp.com/**

Serena funcionando en un servidor real. Para presentar todas las funciones con una sola cuenta existe un **modo demostración**: una cuenta maestra entra con cualquiera de los 11 roles sobre un colegio ficticio aparte, que se puede restablecer en un clic (ver [`docs/notas-de-desarrollo/LEEME_DEMO.txt`](docs/notas-de-desarrollo/LEEME_DEMO.txt)).

## Qué incluye

### 💛 Bienestar emocional

| Módulo | Qué hace |
|---|---|
| **Emociones** | Registro varias veces al día, con nota y momento del día. |
| **Sueño** | Horas, calidad y su efecto en el ánimo. |
| **Diario** | Texto e imágenes, protegido con PIN. |
| **Hábitos y metas** | Rachas, subtareas e insignias. |
| **Estadísticas** | Gráficos por periodo e informe en PDF. |
| **Alertas tempranas** | Aviso tras días seguidos de ánimo bajo. |
| **Chat y citas** | Contacto directo con psicología u orientación. |
| **Apoyo** | Encuestas, ejercicios de respiración y líneas de ayuda. |

### 📚 Gestión académica

| Módulo | Qué hace |
|---|---|
| **Matrícula** | Inscripción web, matrícula y renovación por año lectivo. |
| **Notas** | Actividades, logros, planilla de seguimiento y boletín (con dictado por voz). |
| **Asistencia** | Lista por clase y alerta de ausentismo. |
| **Horarios** | Franjas y grupos, con detección de cruces. |
| **Hoja de vida** | Matrícula, desarrollo, observador y convivencia (Ley 1620). |
| **Certificados** | Con código QR verificable en línea, sin iniciar sesión. |
| **Informes** | Catálogo de informes en PDF, CSV o impresión. |
| **SIMAT y SIMPADE** | Revisión y exportación de reportes. |

### 🗺️ Varios colegios y asistente de IA

- **Varios colegios:** capa de Secretaría de Educación (semáforo, comparativos, reportes, comunicados) y superadmin de plataforma, siempre con cifras agrupadas. Cada colegio está aislado de los demás.
- **Asistente «Pregúntale a Serena»** y análisis en informes de psicología y encuestas mediante OpenRouter (modelos gratuitos). Los datos se anonimizan antes de enviarse. Es opcional: sin clave, Serena sigue funcionando.
- **Centro de ayuda integrado** por rol (web, PDF y HTML descargables, buscador y asistente): ver [`docs/AYUDA.md`](docs/AYUDA.md).

## Los 11 roles

Cada rol tiene su propio panel y menú.

| Rol | Para qué sirve |
|---|---|
| `estudiante` | Registra su bienestar y consulta notas y asistencia. |
| `acudiente` | Sigue a sus hijos. |
| `docente` | Toma lista y califica. |
| `psicologo` | Recibe alertas y acompaña por chat, citas y encuestas. |
| `orientador` | Igual que psicología, desde orientación escolar. |
| `coordinador` | Convivencia, informes agregados y seguimiento académico. |
| `rector` | Informes agregados de la institución. |
| `secretaria_academica` | Matricula y emite certificados. |
| `administrador` | Administra usuarios, grupos y configuración del colegio. |
| `secretaria` | Secretaría de Educación: vista de varios colegios. |
| `superadmin` | Administración de toda la plataforma. |

## Seguridad y privacidad

- ✅ **Consentimiento explícito** de tratamiento de datos al registrarse (Ley 1581 de 2012).
- 🔐 **Verificación en 2 pasos:** app autenticadora, correo, WhatsApp o rostro; secretos cifrados con AES-256-GCM.
- 🛡️ **Acceso seguro:** sentencias preparadas (PDO), tokens CSRF y control de acceso por rol.
- ⏱️ **Límite de intentos:** 5 fallidos bloquean la cuenta 15 minutos.
- 📓 **Diario con PIN**, sesiones activas visibles y bitácora de auditoría de cambios sensibles.
- 🕶️ Los informes para coordinación y rectoría son **agregados y sin datos individuales**.

## Tecnología

| Capa | Herramientas |
|---|---|
| **Backend** | PHP 8.1+ (sin frameworks), PDO |
| **Base de datos** | MySQL 8 |
| **Frontend** | HTML, CSS y JavaScript, diseño responsive y modo oscuro |
| **Correo** | API de Brevo (HTTPS) |
| **IA (opcional)** | OpenRouter, modelos gratuitos |
| **Hosting** | InfinityFree (gratuito) |

> **Decisión técnica:** se eligió software libre con hosting gratuito (costo cero) y se descartó Firebase por su costo a gran escala para toda una institución.

## Estructura del repositorio

```
serena/
├── index.php, .htaccess     Entrada del sitio
├── public/                  Páginas y recursos accesibles desde el navegador
│   ├── <rol>/               Pantallas de cada rol (estudiante, docente, administrador, …)
│   ├── api/                 Endpoints JSON
│   ├── parciales/           Encabezado y pie compartidos
│   └── assets/              CSS, JavaScript e imágenes
├── src/                     Lógica fuera del alcance del navegador
│   ├── config/              Configuración (.env.example, conexión)
│   ├── helpers/             Autenticación, CSRF, correo, IA, PDF, ayuda, …
│   ├── modelos/, controladores/, informes/
│   ├── ayuda/contenido.json Todo el texto del centro de ayuda
│   └── respaldos/, uploads/ Carpetas de datos (vacías en el repositorio)
├── sql/                     schema.sql, migraciones y datos
├── tools/                   Utilidades (capturas automáticas de la ayuda)
└── docs/                    Instalación, ayuda, presentación y notas de desarrollo
```

## Instalación local

**Requisitos:** PHP 8.1+, MySQL 8+ y Apache con `mod_rewrite` (XAMPP o Laragon sirven).

1. **Copia** esta carpeta dentro de `htdocs` (XAMPP) o de la carpeta de proyectos de Laragon.
2. **Crea la base:** en phpMyAdmin crea la base `serena` y ejecuta `sql/schema.sql` (ya incluye todas las tablas).
3. **Configura las credenciales:** copia `src/config/.env.example` a `src/config/.env` y completa tus valores.

   | Variable | Para qué sirve |
   |---|---|
   | `DB_HOST`, `DB_NOMBRE`, `DB_USUARIO`, `DB_PASSWORD` | Conexión a MySQL (en XAMPP: `localhost`, `serena`, `root`, vacío). |
   | `BREVO_API_KEY` | Correos (opcional). |
   | `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY` | reCAPTCHA (opcional). |
   | `OPENROUTER_API_KEY` | Inteligencia artificial (opcional). |
   | `SERENA_2FA_KEY` | 64 caracteres hexadecimales para cifrar la verificación en 2 pasos (opcional). |

   Sin las claves opcionales la plataforma funciona; solo se desactivan correo, reCAPTCHA e IA.
4. **Abre el sitio:** idealmente apunta el document root a `public/`. Sin virtual host: `http://localhost/serena/public/`.
5. **Inicia sesión** desde `login.php`. Las instrucciones completas están en [`docs/INSTALACION.md`](docs/INSTALACION.md).

### Migraciones y datos de demostración

`sql/migraciones/` contiene los cambios de la base en orden numérico (útil si ya tenías una instalación anterior). Una base nueva solo necesita `schema.sql`. `004_datos_demo.sql` carga datos ficticios de ejemplo.

### ⚠️ Antes de publicar o desplegar

- **Nunca subas `src/config/.env`.** Contiene credenciales; el `.gitignore` ya lo excluye.
- Cambia las contraseñas de las cuentas de prueba.
- `src/respaldos/` y `src/uploads/` guardan datos personales de estudiantes (Ley 1581): no se versionan.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/INSTALACION.md`](docs/INSTALACION.md) | Instalación paso a paso, 2FA y correo. |
| [`docs/AYUDA.md`](docs/AYUDA.md) | Cómo editar el centro de ayuda por rol. |
| [`docs/Presentacion_Serena_SENA_final.pptx`](docs/Presentacion_Serena_SENA_final.pptx) | Presentación de la socialización de etapa productiva. |
| [`docs/notas-de-desarrollo/`](docs/notas-de-desarrollo/) | Notas internas de cada fase del desarrollo. |

## Equipo

Proyecto de etapa productiva del **Técnico en Desarrollo de Software, SENA**.

| Aprendiz | Rol en el proyecto |
|---|---|
| **David Santiago Mosquera Mosquera** | Desarrollo de la plataforma |
| **Ana Sofía Ángel Moncada** | Desarrollo de la plataforma y diseño del logo |

---

<div align="center">

**Serena** · bienestar emocional y gestión académica al alcance de toda la institución

<sub>SENA · Técnico en Desarrollo de Software · 2026</sub>

</div>
