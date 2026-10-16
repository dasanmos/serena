<?php
declare(strict_types=1);
require __DIR__ . '/../src/config/config.php';

header('Content-Type: application/javascript; charset=utf-8');
// El service worker en sí no debe quedar cacheado por el navegador — si el navegador
// cachea el sw.php viejo, nunca llegaría a pedir el nuevo y todo lo demás dejaría de
// actualizarse con él.
header('Cache-Control: no-cache');
header('Service-Worker-Allowed: ' . (BASE_URL !== '' ? BASE_URL . '/' : '/'));

$base = BASE_URL;

// Archivos que se guardan de entrada para que la app cargue rápido y funcione sin
// conexión: solo lo estático (CSS, JS, íconos). Las páginas PHP dinámicas se cachean
// sobre la marcha (ver estrategia "network-first" más abajo), nunca de entrada.
$archivosEstaticos = [
    '/assets/css/base.css',
    '/assets/css/menu_barra.css',
    '/assets/css/responsive.css',
    '/assets/js/menu_barra.js',
    '/assets/css/panel.css',
    '/assets/css/estudiante.css',
    '/assets/css/mensajes.css',
    '/assets/css/administrador.css',
    '/assets/css/panel_apoyo.css',
    '/assets/css/alertas.css',
    '/assets/css/encuestas.css',
    '/assets/css/grupos.css',
    '/assets/css/login.css',
    '/assets/css/landing.css',

    '/assets/js/base.js',
    '/assets/js/sonidos.js',

    '/assets/img/logo/logo.png',
    '/assets/img/logo/icon-192.png',
    '/assets/img/logo/icon-512.png',

    '/assets/favicon/favicon.ico',

    '/offline.html'
];

// Hash a partir de filemtime() de cada archivo — igual que assetVersion(), pero para
// todo el paquete a la vez: si CUALQUIERA de estos archivos cambia (o si este propio
// sw.php cambia), la versión del caché cambia sola y el navegador descarta el caché
// viejo, sin tener que acordarse de subir un número a mano.
$firma = '';
foreach ($archivosEstaticos as $ruta) {
    $absoluta = __DIR__ . $ruta;
    $firma .= $ruta . ':' . (@filemtime($absoluta) ?: 0) . ';';
}
$firma .= 'sw:' . filemtime(__FILE__);
$version = substr(md5($firma), 0, 12);

$archivosEstaticosJson = json_encode(array_map(fn($r) => $base . $r, $archivosEstaticos));
$baseJson = json_encode($base);
?>
const VERSION = '<?= $version ?>';
const CACHE_NAME = 'serena-' + VERSION;
const BASE = <?= $baseJson ?>;
const OFFLINE_URL = BASE + '/offline.html';
const ARCHIVOS_ESTATICOS = <?= $archivosEstaticosJson ?>;

// Nunca se cachea ni se intercepta nada bajo estas rutas: el diario (incluida la
// pantalla del PIN) se decidió a propósito que NO funcione sin conexión, y las
// llamadas a /api/ siempre deben pedir datos frescos (nunca servir una respuesta
// vieja de la campanita de notificaciones, el chat, etc.). seguridad.php y
// verificacion_2pasos.php muestran el secreto del autenticador y los códigos de
// recuperación: tampoco pueden quedar guardadas en el caché del dispositivo.
const RUTAS_EXCLUIDAS = ['/estudiante/diario', '/api/', '/seguridad.php', '/verificacion_2pasos.php'];

function rutaExcluida(pathname) {
    return RUTAS_EXCLUIDAS.some((r) => pathname.includes(r));
}

self.addEventListener('install', (evento) => {
    evento.waitUntil(
        caches.open(CACHE_NAME).then((cache) =>
            Promise.all(
                ARCHIVOS_ESTATICOS.map((url) => cache.add(url).catch(() => {
                    // Un solo archivo faltante (ej. si algún día se borra un ícono) no debe
                    // tumbar la instalación completa: mejor cachear los demás igual.
                }))
            )
        ).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(
                nombres.filter((n) => n.startsWith('serena-') && n !== CACHE_NAME).map((n) => caches.delete(n))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;
    if (peticion.method !== 'GET') return; // nunca interceptar POST/PUT/DELETE (formularios, reservas, etc.)

    const url = new URL(peticion.url);
    if (url.origin !== self.location.origin) return; // fuentes de Google, CDN de Chart.js, reCAPTCHA: nativo, sin caché
    if (rutaExcluida(url.pathname)) return; // diario y /api/: siempre red, nunca caché

    if (peticion.mode === 'navigate') {
        // Páginas completas (dar clic en un enlace, escribir la URL): red primero para
        // que siempre se vea lo más reciente; si no hay señal, se cae al último
        // resultado guardado de esa misma página, y si nunca se guardó, a offline.html.
        evento.respondWith(
            fetch(peticion)
                .then((respuesta) => {
                    const copia = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(peticion, copia));
                    return respuesta;
                })
                .catch(() => caches.match(peticion).then((r) => r || caches.match(OFFLINE_URL)))
        );
        return;
    }

    if (url.pathname.startsWith(BASE + '/assets/')) {
        // Estáticos: caché primero (carga instantánea), y de paso se refresca en
        // segundo plano para la próxima vez.
        evento.respondWith(
            caches.match(peticion).then((cacheada) => {
                const redFetch = fetch(peticion).then((respuesta) => {
                    // clone() debe llamarse de inmediato, no dentro de un .then() anidado —
                    // si se espera a que resuelva caches.open() primero, el navegador ya
                    // puede haber empezado a leer el cuerpo de la respuesta (porque ya se
                    // hizo "return respuesta" hacia el fetch original), y entonces clone()
                    // truena con "Response body is already used".
                    const copiaParaCache = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(peticion, copiaParaCache));
                    return respuesta;
                }).catch(() => cacheada);
                return cacheada || redFetch;
            })
        );
    }
    // Cualquier otra petición (llamadas fetch() sueltas desde el JS, etc.): nativo, sin caché.
});
