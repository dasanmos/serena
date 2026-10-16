# Centro de ayuda de Serena

- `public/ayuda.php` — página (pública = guía general; con sesión = guía del rol; el superadmin puede ver la guía de cualquier rol con `?rol=`; el administrador, solo las de los roles de colegio — nunca las de `secretaria` ni `superadmin`).
- `public/ayuda_descargar.php?formato=pdf|html&ambito=general|rol` — descargas.
- `src/ayuda/contenido.json` — **todo el texto**. Edítalo aquí; web, HTML y PDF se actualizan solos.
- `src/helpers/ayuda.php`, `ayuda_pdf.php`, `GeneradorPDF.php` — generación.
- `public/assets/css/ayuda.css`, `assets/js/ayuda.js` — estilos y buscador.

## Editar contenido
Capítulo: `id`, `titulo`, `icono`, `resumen`, `grupo` (opcional: categoría del menú, p. ej. «Matrícula»; se muestra en el índice), `ruta` (opcional), `secciones`.
Sección: `titulo` + cualquiera de `parrafos`, `pasos`, `puntos`, `consejo`, `aviso` (se muestran en ese orden) y `imagenes`.
`**texto**` = negrita. `{rol}` en `ruta` se reemplaza por el rol. Sin emojis fuera de `icono` (el PDF los omite).

## Capturas
Guarda el JPG/PNG en `public/assets/img/ayuda/` y en la sección: `"imagenes": [{"archivo": "est-emociones-1.jpg", "pie": "Registro de emoción"}]`.
Web: por URL. HTML descargable: incrustada. PDF: JPEG directo; PNG solo si PHP tiene GD.

## Al agregar una sección nueva al menú
Agrega su capítulo en el JSON del rol (con `ruta`).

## Roles con guía
`estudiante`, `psicologo`, `orientador`, `docente`, `coordinador`, `rector`, `administrador`, `secretaria_academica`, `acudiente`, `secretaria`, `superadmin`. Cada guía abre con el capítulo «Tu rol y sus límites» (alcance, lo que sí y lo que no ve, a quién acudir).
Para agregar un rol nuevo: (1) añádelo a `AYUDA_ROLES` (y a `AYUDA_ROLES_COLEGIO` si es un rol de colegio, para que el administrador pueda abrir su guía) en `src/helpers/ayuda.php`, (2) crea su bloque en `roles` del JSON con `nombre`, `resumen`, `capitulos` y `faq`, (3) menciónalo en el capítulo general «Los roles de Serena».
Si un rol ve las pantallas de otro (como secretaría con superadmin), usa en `ruta` la carpeta propia del rol (`/secretaria/...`).

## Capturas pendientes
La lista completa de archivos que faltan, con la página que hay que capturar, está en `CAPTURAS_PENDIENTES.md` en la raíz del proyecto (se genera con `python tools/capturar_ayuda.py --lista`).

## Qué es de cada nivel (no mezclar)
- **Administrador (colegio):** usuarios, grupos, matrícula, años, informes, auditoría y salud de su colegio, textos de correos, lugares propios.
- **Secretaría de Educación:** colegios asignados, semáforo, comparativo, reportes, traslados, comunicados.
- **Superadmin:** secretarías, DANE, cuenta maestra, respaldos, diagnóstico de IA, datos de prueba, lugares globales.
Si una pantalla es solo del superadmin, su capítulo va en `superadmin`, no en `administrador`.
