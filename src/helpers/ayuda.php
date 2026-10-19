<?php
declare(strict_types=1);

/**
 * Centro de ayuda de Serena — una sola fuente de contenido (src/ayuda/contenido.json)
 * que alimenta tres salidas: la página web (public/ayuda.php), el HTML descargable
 * y el PDF descargable (ayuda_pdf.php). Para cambiar un texto, edita el JSON; las
 * tres salidas se actualizan solas.
 *
 * Capturas de pantalla: cualquier sección del JSON puede llevar
 *   "imagenes": [ {"archivo": "est-emociones-1.jpg", "pie": "Formulario de registro"} ]
 * y el archivo va en public/assets/img/ayuda/. Si el archivo no existe, se omite sin error.
 */

require_once __DIR__ . '/csrf.php';

const AYUDA_ROLES = ['estudiante', 'psicologo', 'orientador', 'docente', 'coordinador', 'rector', 'administrador', 'secretaria_academica', 'acudiente', 'secretaria', 'superadmin'];

/**
 * Roles de colegio: los que el administrador de un colegio puede consultar. Las guías de
 * «secretaria» (Secretaría de Educación) y «superadmin» (equipo de Serena) describen pantallas
 * que el administrador no tiene, así que solo las puede abrir el superadmin.
 */
const AYUDA_ROLES_COLEGIO = ['estudiante', 'psicologo', 'orientador', 'docente', 'coordinador', 'rector', 'administrador', 'secretaria_academica', 'acudiente'];

/** Roles que pueden consultar la guía de otros roles (?rol=...). */
function ayudaPuedeVerOtrosRoles(string $rolSesion): bool
{
    return in_array($rolSesion, ['administrador', 'superadmin'], true);
}

/** Guías de rol que $rolSesion puede abrir: la suya y, si corresponde, las de otros roles. */
function ayudaRolesVisibles(string $rolSesion): array
{
    if ($rolSesion === 'superadmin') {
        return AYUDA_ROLES;
    }
    if ($rolSesion === 'administrador') {
        return AYUDA_ROLES_COLEGIO;
    }
    return in_array($rolSesion, AYUDA_ROLES, true) ? [$rolSesion] : [];
}

/** ¿Puede $rolSesion ver la guía de $rolPedido? */
function ayudaPuedeVerRol(string $rolSesion, string $rolPedido): bool
{
    return in_array($rolPedido, ayudaRolesVisibles($rolSesion), true) && ayudaRolValido($rolPedido);
}

function ayudaDirImagenes(): string
{
    return __DIR__ . '/../../public/assets/img/ayuda/';
}

function ayudaContenido(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $json = @file_get_contents(__DIR__ . '/../ayuda/contenido.json');
    $datos = $json === false ? null : json_decode($json, true);
    if (!is_array($datos)) {
        error_log('ayuda: no se pudo leer o interpretar src/ayuda/contenido.json');
        $datos = [
            'titulo' => 'Manual de Serena', 'version' => '', 'actualizado' => '',
            'general' => ['capitulos' => []], 'cuenta' => ['capitulos' => []], 'roles' => [], 'faq' => [],
        ];
    }
    $cache = $datos;
    return $cache;
}

function ayudaRolValido(string $rol): bool
{
    return in_array($rol, AYUDA_ROLES, true) && isset(ayudaContenido()['roles'][$rol]);
}

function ayudaFechaLarga(string $fechaYmd): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
              'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $t = strtotime($fechaYmd);
    if ($t === false) {
        return $fechaYmd;
    }
    return (int) date('j', $t) . ' de ' . $meses[(int) date('n', $t)] . ' de ' . date('Y', $t);
}

/**
 * Arma el libro: ámbito 'general' (Conoce Serena + tu cuenta + preguntas frecuentes)
 * o 'rol' (además, la guía completa del rol). Numera los capítulos de corrido.
 */
function ayudaArmarLibro(string $ambito, ?string $rol = null): array
{
    $c = ayudaContenido();
    $rolValido = $rol !== null && ayudaRolValido($rol);
    if ($ambito === 'rol' && !$rolValido) {
        $ambito = 'general';
    }
    $rolNombre = ($ambito === 'rol' && $rolValido) ? (string) $c['roles'][$rol]['nombre'] : '';

    $partes = [];
    $partes[] = [
        'id' => 'parte-conoce', 'titulo' => 'Conoce Serena',
        'intro' => 'Qué es la plataforma, cómo se entra, cómo se ve y cómo se cuidan tus datos.',
        'capitulos' => $c['general']['capitulos'] ?? [],
    ];

    if ($ambito === 'rol') {
        $partes[] = [
            'id' => 'parte-rol', 'titulo' => 'Guía para ' . mb_strtolower($rolNombre),
            'intro' => (string) $c['roles'][$rol]['resumen'],
            'capitulos' => $c['roles'][$rol]['capitulos'] ?? [],
        ];
    }

    $cuenta = [];
    foreach (($c['cuenta']['capitulos'] ?? []) as $cap) {
        if (isset($cap['ruta'])) {
            if ($ambito === 'rol') {
                $cap['ruta'] = str_replace('{rol}', (string) $rol, $cap['ruta']);
            } else {
                unset($cap['ruta']); // sin sesión no hay a dónde enlazar
            }
        }
        $cuenta[] = $cap;
    }
    $partes[] = [
        'id' => 'parte-cuenta', 'titulo' => 'Tu cuenta y tu seguridad',
        'intro' => 'Lo que puedes hacer con tu perfil, tu contraseña y tus dispositivos. Es igual para todos los roles.',
        'capitulos' => $cuenta,
    ];

    $preguntas = $c['faq'] ?? [];
    if ($ambito === 'rol') {
        $preguntas = array_merge($c['roles'][$rol]['faq'] ?? [], $preguntas);
    }
    $secciones = [];
    foreach ($preguntas as $par) {
        $secciones[] = ['titulo' => (string) $par['p'], 'parrafos' => [(string) $par['r']]];
    }
    $partes[] = [
        'id' => 'parte-faq', 'titulo' => 'Preguntas frecuentes',
        'intro' => 'Respuestas rápidas a las dudas más comunes.',
        'capitulos' => [[
            'id' => 'preguntas-frecuentes', 'titulo' => 'Preguntas frecuentes', 'icono' => '❓',
            'resumen' => 'Respuestas rápidas.', 'secciones' => $secciones,
        ]],
    ];

    $numero = 0;
    foreach ($partes as $i => $parte) {
        foreach ($parte['capitulos'] as $j => $cap) {
            $numero++;
            $partes[$i]['capitulos'][$j]['num'] = $numero;
            $partes[$i]['capitulos'][$j]['ancla'] = 'cap-' . $cap['id'];
        }
    }

    return [
        'ambito' => $ambito,
        'rol' => $ambito === 'rol' ? $rol : null,
        'rolNombre' => $rolNombre,
        'titulo' => (string) ($c['titulo'] ?? 'Manual de Serena'),
        'subtitulo' => $ambito === 'rol' ? 'Guía para: ' . $rolNombre : 'Guía general',
        'version' => (string) ($c['version'] ?? ''),
        'actualizado' => (string) ($c['actualizado'] ?? ''),
        'partes' => $partes,
    ];
}

function ayudaNombreArchivo(string $texto): string
{
    $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
             'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N'];
    $texto = strtr($texto, $mapa);
    $limpio = preg_replace('/[^A-Za-z0-9]+/', '-', $texto) ?? '';
    $limpio = trim($limpio, '-');
    return $limpio !== '' ? $limpio : 'General';
}

// ---------------------------------------------------------------- HTML

function ayudaH(string $t): string
{
    return htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

/** Escapa el texto y convierte **negrita** en <strong>. */
function ayudaFormatear(string $t): string
{
    $seguro = ayudaH($t);
    return preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $seguro) ?? $seguro;
}

/** Devuelve las capturas de una sección que existen de verdad en disco. */
function ayudaImagenesSeccion(array $seccion): array
{
    $salida = [];
    foreach (($seccion['imagenes'] ?? []) as $img) {
        $archivo = basename((string) ($img['archivo'] ?? ''));
        if ($archivo === '' || !is_file(ayudaDirImagenes() . $archivo)) {
            continue;
        }
        $salida[] = ['ruta' => ayudaDirImagenes() . $archivo, 'archivo' => $archivo, 'pie' => (string) ($img['pie'] ?? '')];
    }
    return $salida;
}

function ayudaMimeImagen(string $archivo): string
{
    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    return $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
}

/**
 * HTML de las partes y capítulos. $enlazar: mostrar botones «Abrir…» (solo con sesión).
 * $incrustar: las capturas van dentro del HTML en base64 (descarga) en vez de por URL.
 */
function ayudaHtmlLibro(array $libro, bool $enlazar, bool $incrustar = false): string
{
    $h = '';
    foreach ($libro['partes'] as $indiceParte => $parte) {
        $h .= '<section class="ayuda-parte" id="' . ayudaH($parte['id']) . '">';
        $h .= '<header class="ayuda-parte-cab"><span class="ayuda-parte-num">Parte ' . ($indiceParte + 1) . '</span>';
        $h .= '<h2>' . ayudaH($parte['titulo']) . '</h2>';
        if (!empty($parte['intro'])) {
            $h .= '<p>' . ayudaH($parte['intro']) . '</p>';
        }
        $h .= '</header>';

        foreach ($parte['capitulos'] as $cap) {
            $h .= '<article class="ayuda-capitulo" id="' . ayudaH($cap['ancla']) . '" data-capitulo>';
            $h .= '<header class="ayuda-cap-cab"><span class="ayuda-cap-icono" aria-hidden="true">' . ayudaH((string) ($cap['icono'] ?? '')) . '</span>';
            $h .= '<div><span class="ayuda-cap-num">Capítulo ' . (int) $cap['num'] . (!empty($cap['grupo']) ? ' · ' . ayudaH((string) $cap['grupo']) : '') . '</span>';
            $h .= '<h3>' . ayudaH($cap['titulo']) . '</h3></div></header>';
            $h .= '<p class="ayuda-cap-resumen">' . ayudaH((string) ($cap['resumen'] ?? '')) . '</p>';
            if ($enlazar && !empty($cap['ruta'])) {
                $h .= '<p><a class="ayuda-boton" href="' . ayudaH(BASE_URL . $cap['ruta']) . '">Abrir «' . ayudaH($cap['titulo']) . '»</a></p>';
            }
            foreach ($cap['secciones'] as $sec) {
                $h .= '<section class="ayuda-seccion"><h4>' . ayudaH($sec['titulo']) . '</h4>';
                foreach (($sec['parrafos'] ?? []) as $p) {
                    $h .= '<p>' . ayudaFormatear((string) $p) . '</p>';
                }
                if (!empty($sec['pasos'])) {
                    $h .= '<ol class="ayuda-pasos">';
                    foreach ($sec['pasos'] as $p) {
                        $h .= '<li>' . ayudaFormatear((string) $p) . '</li>';
                    }
                    $h .= '</ol>';
                }
                if (!empty($sec['puntos'])) {
                    $h .= '<ul class="ayuda-puntos">';
                    foreach ($sec['puntos'] as $p) {
                        $h .= '<li>' . ayudaFormatear((string) $p) . '</li>';
                    }
                    $h .= '</ul>';
                }
                foreach (ayudaImagenesSeccion($sec) as $img) {
                    if ($incrustar) {
                        $bin = @file_get_contents($img['ruta']);
                        if ($bin === false) {
                            continue;
                        }
                        $src = 'data:' . ayudaMimeImagen($img['archivo']) . ';base64,' . base64_encode($bin);
                    } else {
                        $src = BASE_URL . '/assets/img/ayuda/' . rawurlencode($img['archivo']);
                    }
                    $h .= '<figure class="ayuda-captura"><img src="' . ayudaH($src) . '" alt="' . ayudaH($img['pie']) . '" loading="lazy">';
                    if ($img['pie'] !== '') {
                        $h .= '<figcaption>' . ayudaH($img['pie']) . '</figcaption>';
                    }
                    $h .= '</figure>';
                }
                if (!empty($sec['consejo'])) {
                    $h .= '<aside class="ayuda-nota ayuda-nota-consejo"><strong class="ayuda-nota-etq">Consejo</strong><p>' . ayudaFormatear((string) $sec['consejo']) . '</p></aside>';
                }
                if (!empty($sec['aviso'])) {
                    $h .= '<aside class="ayuda-nota ayuda-nota-aviso"><strong class="ayuda-nota-etq">Importante</strong><p>' . ayudaFormatear((string) $sec['aviso']) . '</p></aside>';
                }
                $h .= '</section>';
            }
            $h .= '</article>';
        }
        $h .= '</section>';
    }
    return $h;
}

/** Índice (tabla de contenido) como lista de enlaces. */
function ayudaHtmlIndice(array $libro): string
{
    $h = '<ol class="ayuda-indice-lista">';
    foreach ($libro['partes'] as $parte) {
        $h .= '<li class="ayuda-indice-parte"><a href="#' . ayudaH($parte['id']) . '">' . ayudaH($parte['titulo']) . '</a><ol>';
        $grupoActual = '';
        foreach ($parte['capitulos'] as $cap) {
            $grupo = (string) ($cap['grupo'] ?? '');
            if ($grupo !== '' && $grupo !== $grupoActual) {
                $h .= '<li class="ayuda-indice-grupo" data-grupo>' . ayudaH($grupo) . '</li>';
                $grupoActual = $grupo;
            }
            $h .= '<li><a href="#' . ayudaH($cap['ancla']) . '" data-capitulo-enlace="' . ayudaH($cap['ancla']) . '">';
            $h .= '<span class="ayuda-indice-num">' . (int) $cap['num'] . '</span> ' . ayudaH($cap['titulo']) . '</a></li>';
        }
        $h .= '</ol></li>';
    }
    return $h . '</ol>';
}

function ayudaUrlDescarga(string $formato, string $ambito, ?string $rol): string
{
    $url = BASE_URL . '/ayuda_descargar.php?formato=' . $formato . '&ambito=' . $ambito;
    if ($rol !== null && $rol !== '') {
        $url .= '&rol=' . rawurlencode($rol);
    }
    return $url;
}

/**
 * Bloque completo de la página de ayuda (cabecera, pestañas, descargas, índice y libro).
 * $ctx: logueado (bool), rolSesion (string), rolVista (string), verGeneral (bool).
 */
function ayudaHtmlPagina(array $libro, array $ctx): string
{
    $contenido = ayudaContenido();
    $logueado = (bool) $ctx['logueado'];
    $verGeneral = (bool) $ctx['verGeneral'];
    $rolVista = (string) $ctx['rolVista'];
    $esAdmin = ayudaPuedeVerOtrosRoles((string) ($ctx['rolSesion'] ?? ''));
    $nombreRol = $rolVista !== '' && ayudaRolValido($rolVista) ? (string) $contenido['roles'][$rolVista]['nombre'] : '';
    $sufijoRol = ($esAdmin && $rolVista !== '') ? '&rol=' . rawurlencode($rolVista) : '';

    $h = '<div class="ayuda" id="ayuda">';
    $h .= '<header class="ayuda-cabecera">';
    $h .= '<h1>Ayuda de Serena</h1>';
    $h .= '<p class="ayuda-intro">Busca lo que necesitas o recorre la guía por capítulos.';
    if ($logueado && !$verGeneral && $nombreRol !== '') {
        $h .= ' Estás viendo la guía para <strong>' . ayudaH(mb_strtolower($nombreRol)) . '</strong>.';
    } elseif (!$logueado) {
        $h .= ' <a href="' . ayudaH(BASE_URL . '/login.php') . '">Inicia sesión</a> para ver también la guía de tu rol.';
    }
    $h .= '</p>';

    if ($logueado && $nombreRol !== '') {
        $h .= '<div class="ayuda-controles">';
        $h .= '<nav class="ayuda-pestanas" aria-label="Tipo de guía">';
        $h .= '<a href="' . ayudaH(BASE_URL . '/ayuda.php' . ($sufijoRol !== '' ? '?' . ltrim($sufijoRol, '&') : '')) . '" class="' . ($verGeneral ? '' : 'activa') . '"' . ($verGeneral ? '' : ' aria-current="page"') . '>Mi guía: ' . ayudaH($nombreRol) . '</a>';
        $h .= '<a href="' . ayudaH(BASE_URL . '/ayuda.php?ambito=general') . '" class="' . ($verGeneral ? 'activa' : '') . '"' . ($verGeneral ? ' aria-current="page"' : '') . '>Guía general</a>';
        $h .= '</nav>';
        if ($esAdmin) {
            $h .= '<form class="ayuda-selector-rol" action="' . ayudaH(BASE_URL . '/ayuda.php') . '" method="GET"><label for="ayuda-rol">Ver la guía de</label> <select id="ayuda-rol" name="rol" onchange="this.form.submit()">';
            foreach (ayudaRolesVisibles((string) ($ctx['rolSesion'] ?? '')) as $r) {
                if (!ayudaRolValido($r)) {
                    continue;
                }
                $h .= '<option value="' . ayudaH($r) . '"' . ($r === $rolVista ? ' selected' : '') . '>' . ayudaH((string) $contenido['roles'][$r]['nombre']) . '</option>';
            }
            $h .= '</select><noscript> <button type="submit">Ver</button></noscript></form>';
        }
        $h .= '</div>';
    }

    // Buscador: lo primero que se usa. Sin JavaScript no se muestra (el índice sigue funcionando).
    $h .= '<div class="ayuda-busqueda" role="search">';
    $h .= '<label class="ayuda-sr" for="ayuda-buscar">Buscar en la ayuda</label>';
    $h .= '<span class="ayuda-busqueda-icono" aria-hidden="true">🔍</span>';
    $h .= '<input type="search" id="ayuda-buscar" class="ayuda-buscar" placeholder="Escribe una palabra: contraseña, cita, boletín…" autocomplete="off" enterkeyhint="search" aria-describedby="ayuda-resultados">';
    $h .= '<button type="button" class="ayuda-busqueda-limpiar" id="ayuda-limpiar" hidden aria-label="Borrar búsqueda">✕</button>';
    $h .= '<kbd class="ayuda-busqueda-atajo" aria-hidden="true" title="Pulsa / para buscar">/</kbd>';
    $h .= '<p class="ayuda-resultados" id="ayuda-resultados" role="status" aria-live="polite"></p>';
    $h .= '</div>';
    // Temas frecuentes: ayuda.js oculta los que no tienen resultados en esta guía.
    $temas = ['contraseña', 'verificación en 2 pasos', 'cita', 'diario', 'boletín', 'asistencia', 'matrícula', 'certificado', 'chat', 'alerta'];
    $h .= '<div class="ayuda-temas" id="ayuda-temas" hidden><span class="ayuda-temas-etq">Temas frecuentes:</span>';
    foreach ($temas as $t) {
        $h .= '<button type="button" class="ayuda-tema" data-buscar="' . ayudaH($t) . '">' . ayudaH(mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1)) . '</button>';
    }
    $h .= '</div>';
    $h .= '</header>';

    $h .= '<div class="ayuda-apoyo">';
    // Asistente con IA: responde dudas de uso a partir de este mismo manual
    $h .= '<section class="ayuda-ia" id="ayuda-ia" aria-labelledby="ayuda-ia-titulo"'
        . ' data-endpoint="' . ayudaH(BASE_URL . '/api/ayuda_ia.php') . '"'
        . ' data-csrf="' . ayudaH(tokenCSRF()) . '"'
        . ' data-rol="' . ayudaH($esAdmin ? $rolVista : '') . '">';
    $h .= '<h2 id="ayuda-ia-titulo">Pregúntale a Serena</h2>';
    $h .= '<p class="ayuda-ia-sub">Escribe tu duda con tus palabras y te respondo con lo que dice esta guía. No escribas datos personales.</p>';
    $h .= '<div class="ayuda-ia-fila"><label class="ayuda-sr" for="ayuda-ia-pregunta">Tu pregunta</label>';
    $h .= '<input type="text" id="ayuda-ia-pregunta" maxlength="300" placeholder="Ej.: ¿cómo cambio mi contraseña?" autocomplete="off" enterkeyhint="send">';
    $h .= '<button type="button" id="ayuda-ia-enviar" class="ayuda-boton">Preguntar</button></div>';
    $h .= '<div class="ayuda-ia-respuesta" id="ayuda-ia-respuesta" role="status" aria-live="polite" hidden></div>';
    $h .= '</section>';

    $h .= '<aside class="ayuda-lateral" aria-label="Descargas y ayuda inmediata">';
    $h .= '<h2>Descargar la guía</h2><ul class="ayuda-descargas">';
    if ($logueado && $nombreRol !== '') {
        $h .= '<li><span>Manual de ' . ayudaH(mb_strtolower($nombreRol)) . '<small>Guía general, tu rol y tu cuenta</small></span>';
        $h .= '<span class="ayuda-descarga-botones"><a class="ayuda-boton" href="' . ayudaH(ayudaUrlDescarga('pdf', 'rol', $esAdmin ? $rolVista : null)) . '">PDF</a>';
        $h .= '<a class="ayuda-boton ayuda-boton-sec" href="' . ayudaH(ayudaUrlDescarga('html', 'rol', $esAdmin ? $rolVista : null)) . '">HTML</a></span></li>';
    }
    $h .= '<li><span>Guía general<small>Lo que todas las personas deben saber</small></span>';
    $h .= '<span class="ayuda-descarga-botones"><a class="ayuda-boton' . ($logueado ? ' ayuda-boton-sec' : '') . '" href="' . ayudaH(ayudaUrlDescarga('pdf', 'general', null)) . '">PDF</a>';
    $h .= '<a class="ayuda-boton ayuda-boton-sec" href="' . ayudaH(ayudaUrlDescarga('html', 'general', null)) . '">HTML</a></span></li>';
    $h .= '</ul>';
    $h .= '<p class="ayuda-urgente"><strong>¿Es urgente?</strong> Llama gratis a la <a href="tel:106">Línea 106</a> (salud mental, 24 horas) o al <a href="tel:123">123</a>.</p>';
    $h .= '</aside>';
    $h .= '</div>';

    // Índice + libro
    $h .= '<div class="ayuda-layout">';
    $h .= '<nav class="ayuda-indice" aria-label="Índice de la ayuda">';
    $h .= '<button type="button" class="ayuda-indice-toggle" aria-expanded="false">Índice</button>';
    $h .= '<div class="ayuda-indice-cuerpo">';
    $h .= ayudaHtmlIndice($libro);
    $h .= '</div></nav>';
    $h .= '<div class="ayuda-libro" id="ayuda-libro">' . ayudaHtmlLibro($libro, $logueado);
    $h .= '<p class="ayuda-vacio" id="ayuda-vacio" hidden>No encontramos nada con esas palabras. Prueba con otra o con menos palabras.</p></div>';
    $h .= '</div></div>';
    return $h;
}

/** Documento HTML autónomo para descargar (CSS y logo incrustados, sin JavaScript). */
function ayudaHtmlDocumento(array $libro): string
{
    $css = (string) @file_get_contents(__DIR__ . '/../../public/assets/css/ayuda.css');
    $logo = '';
    $rutaLogo = __DIR__ . '/../../public/assets/img/logo/icon-192.png';
    if (is_file($rutaLogo)) {
        $logo = '<img class="ayuda-doc-logo" alt="" src="data:image/png;base64,' . base64_encode((string) file_get_contents($rutaLogo)) . '">';
    }
    $meta = [];
    if ($libro['version'] !== '') {
        $meta[] = 'Versión ' . $libro['version'];
    }
    if ($libro['actualizado'] !== '') {
        $meta[] = 'Contenido actualizado el ' . ayudaFechaLarga($libro['actualizado']);
    }
    $meta[] = 'Generado el ' . date('d/m/Y H:i');
    $titulo = $libro['titulo'] . ' — ' . ($libro['rolNombre'] !== '' ? $libro['rolNombre'] : 'General');

    $oscuro = ':root{--color-fondo:#1C1A16;--color-superficie:#24211C;--color-texto:#EDE8DC;--color-texto-suave:#A79E8C;'
        . '--color-borde:#3A362C;--color-dorado-texto:#F0C63E;--color-info:#6FB2D9;--color-info-fondo:#1C2E36;'
        . '--color-aviso:#E0B24F;--color-aviso-fondo:#3A2E14;--color-peligro:#E27B6E;--color-peligro-fondo:#3A2420;}';

    $h = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
    $h .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $h .= '<title>' . ayudaH($titulo) . '</title>';
    $h .= '<style>' . $css . '@media (prefers-color-scheme: dark){' . $oscuro . '}</style></head>';
    $h .= '<body class="ayuda-doc"><header class="ayuda-doc-portada">' . $logo;
    $h .= '<p class="ayuda-doc-marca">SERENA</p><h1>' . ayudaH($libro['titulo']) . '</h1>';
    $h .= '<p class="ayuda-doc-sub">' . ayudaH($libro['subtitulo']) . '</p>';
    $h .= '<p class="ayuda-doc-meta">' . ayudaH(implode(' · ', $meta)) . '</p></header>';
    $h .= '<nav class="ayuda-doc-indice"><h2>Contenido</h2>' . ayudaHtmlIndice($libro) . '</nav>';
    $h .= '<main class="ayuda-libro">' . ayudaHtmlLibro($libro, false, true) . '</main>';
    $h .= '<footer class="ayuda-doc-pie">Serena — Plataforma de Bienestar Emocional Institucional</footer>';
    $h .= '</body></html>';
    return $h;
}

function ayudaEnviarHtml(array $libro, string $nombreBase): void
{
    $html = ayudaHtmlDocumento($libro);
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreBase . '.html"');
    header('Content-Length: ' . strlen($html));
    header('Cache-Control: private, max-age=0, must-revalidate');
    echo $html;
    exit;
}
