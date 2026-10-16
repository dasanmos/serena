<?php
declare(strict_types=1);

require_once __DIR__ . '/ayuda.php';
require_once __DIR__ . '/ia.php';
require_once __DIR__ . '/texto_ia_cache.php';

/**
 * ASISTENTE DE AYUDA CON IA («Pregúntale a Serena»)
 *
 * Responde dudas de USO de la plataforma a partir del propio manual (src/ayuda/contenido.json):
 *   1. Busca en el manual los 3 capítulos más relacionados con la pregunta (sin IA, por palabras).
 *   2. Le pasa SOLO esos fragmentos al modelo y le pide que responda con ellos.
 *   3. Si la IA no está disponible, devuelve esos mismos fragmentos como respuesta: siempre sirve.
 *
 * Seguridad:
 *   - Preguntas sobre crisis o autolesión NO pasan por la IA: se responde con un mensaje fijo que remite
 *     a «Ayuda urgente» y a un adulto de confianza.
 *   - No recibe datos de la cuenta, de otros usuarios ni del bienestar emocional: solo la pregunta
 *     (anonimizada: sin correos, enlaces ni números) y texto del manual.
 */

const AYUDA_IA_MAX_PREGUNTA = 300;
const AYUDA_IA_MAX_POR_VENTANA = 8;     // preguntas por sesión
const AYUDA_IA_VENTANA_S = 600;         // ...cada 10 minutos

/** Minúsculas y sin tildes, para comparar. */
function ayudaIANormalizar(string $t): string
{
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    return $t;
}

/** Palabras útiles de una frase (sin palabras vacías, con singular aproximado). @return string[] */
function ayudaIAPalabras(string $t): array
{
    static $vacias = ['como', 'donde', 'cuando', 'cual', 'cuales', 'quien', 'para', 'por', 'que', 'con', 'sin', 'una', 'uno', 'unos', 'unas',
        'los', 'las', 'del', 'les', 'mis', 'tus', 'sus', 'este', 'esta', 'esto', 'estos', 'estas', 'puedo', 'puede', 'pueden', 'hago', 'hacer',
        'quiero', 'necesito', 'debo', 'dónde', 'cómo', 'hay', 'ver', 'uso', 'usar', 'serena', 'plataforma', 'sistema', 'algo', 'favor',
        'hola', 'gracias', 'sobre', 'entre', 'desde', 'hasta', 'esta', 'estoy', 'tengo', 'mas', 'muy', 'pero', 'porque'];
    $t = ayudaIANormalizar($t);
    $salida = [];
    foreach (preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
        if (strlen($p) < 3 || in_array($p, $vacias, true)) {
            continue;
        }
        if (strlen($p) > 4 && substr($p, -2) === 'es') {
            $p = substr($p, 0, -2);
        } elseif (strlen($p) > 3 && substr($p, -1) === 's') {
            $p = substr($p, 0, -1);
        }
        $salida[$p] = true;
    }
    return array_keys($salida);
}

/** Quita el formato **negrita** y junta el texto de un capítulo. */
function ayudaIATextoCapitulo(array $cap): string
{
    $partes = [(string) ($cap['resumen'] ?? '')];
    foreach (($cap['secciones'] ?? []) as $s) {
        $partes[] = (string) ($s['titulo'] ?? '') . ':';
        foreach (['parrafos', 'pasos', 'puntos'] as $k) {
            foreach (($s[$k] ?? []) as $x) {
                $partes[] = (string) $x;
            }
        }
        foreach (['consejo', 'aviso'] as $k) {
            if (!empty($s[$k])) {
                $partes[] = (string) $s[$k];
            }
        }
    }
    return trim(str_replace('**', '', implode(' ', $partes)));
}

/**
 * Fragmentos consultables según el rol: capítulos generales + cuenta + guía del rol, y las preguntas frecuentes.
 * @return array<int,array{ancla:string,titulo:string,resumen:string,texto:string,ruta:string}>
 */
function ayudaIAFragmentos(string $rol): array
{
    $c = ayudaContenido();
    $bloques = [$c['general']['capitulos'] ?? [], $c['cuenta']['capitulos'] ?? []];
    if ($rol !== '' && ayudaRolValido($rol)) {
        $bloques[] = $c['roles'][$rol]['capitulos'] ?? [];
    }
    $frag = [];
    foreach ($bloques as $caps) {
        foreach ($caps as $cap) {
            $frag[] = [
                'ancla'   => 'cap-' . (string) $cap['id'],
                'titulo'  => (string) $cap['titulo'],
                'resumen' => (string) ($cap['resumen'] ?? ''),
                'texto'   => ayudaIATextoCapitulo($cap),
                'ruta'    => str_replace('{rol}', $rol, (string) ($cap['ruta'] ?? '')),
            ];
        }
    }
    // Preguntas frecuentes (generales y del rol): un fragmento por pregunta.
    $faqs = $c['faq'] ?? [];
    if ($rol !== '' && ayudaRolValido($rol)) {
        $faqs = array_merge($faqs, $c['roles'][$rol]['faq'] ?? []);
    }
    foreach ($faqs as $f) {
        $frag[] = ['ancla' => '', 'titulo' => (string) ($f['p'] ?? ''), 'resumen' => '', 'texto' => str_replace('**', '', (string) ($f['r'] ?? '')), 'ruta' => ''];
    }
    return $frag;
}

/**
 * Los fragmentos más relacionados con la pregunta (título pesa más que el cuerpo).
 * @return array<int,array<string,mixed>>
 */
function ayudaIABuscar(string $pregunta, string $rol, int $max = 3): array
{
    $palabras = ayudaIAPalabras($pregunta);
    if (!$palabras) {
        return [];
    }
    $frag = ayudaIAFragmentos($rol);

    // Rareza de cada palabra: las que aparecen en pocos capítulos valen más.
    $doc = [];
    foreach ($frag as $i => $f) {
        $doc[$i] = ayudaIANormalizar($f['titulo'] . ' ' . $f['resumen'] . ' ' . $f['texto']);
    }
    $peso = [];
    foreach ($palabras as $p) {
        $n = 0;
        foreach ($doc as $d) {
            if (strpos($d, $p) !== false) {
                $n++;
            }
        }
        $peso[$p] = $n > 0 ? 1.0 + log((count($frag) + 1) / $n) : 0.0;
    }

    $puntajes = [];
    foreach ($frag as $i => $f) {
        $titulo = ayudaIANormalizar($f['titulo']);
        $resumen = ayudaIANormalizar($f['resumen']);
        $s = 0.0;
        foreach ($palabras as $p) {
            if ($peso[$p] <= 0) {
                continue;
            }
            $s += $peso[$p] * ((strpos($titulo, $p) !== false ? 4 : 0) + (strpos($resumen, $p) !== false ? 2 : 0) + (strpos($doc[$i], $p) !== false ? 1 : 0));
        }
        if ($s > 0) {
            $puntajes[$i] = $s;
        }
    }
    arsort($puntajes);
    $salida = [];
    foreach (array_slice(array_keys($puntajes), 0, $max) as $i) {
        $salida[] = $frag[$i];
    }
    return $salida;
}

/** ¿La pregunta habla de una crisis personal? Se responde con un mensaje fijo, sin IA. */
function ayudaIAEsCrisis(string $pregunta): bool
{
    $t = ayudaIANormalizar($pregunta);
    return (bool) preg_match('/suicid|quitarme la vida|quitarse la vida|matarme|no quiero vivir|no quiero seguir viviendo|hacerme dano|lastimarme|autolesion|cortarme|me quiero morir|abuso|me pegan|me maltratan|me violaron|acoso/', $t);
}

function ayudaIAMensajeCrisis(): string
{
    return 'Lo que cuentas es importante y no tienes que cargarlo solo. Entra a «Ayuda urgente» (en el menú del estudiante) o habla hoy mismo con '
        . 'un adulto de confianza: tu psicólogo o psicóloga, tu docente orientador, tu familia. Si estás en peligro ahora, llama a la línea de emergencias de tu ciudad. '
        . 'Yo solo sé explicar cómo usar Serena, y esto merece una persona que te escuche.';
}

/** Respuesta sin IA: lo más relevante del manual, tal cual. */
function ayudaIARespuestaBase(array $fragmentos): string
{
    if (!$fragmentos) {
        return 'No encontré nada en la ayuda sobre eso. Prueba con otras palabras en el buscador de arriba, o pregúntale a quien administra Serena en tu colegio.';
    }
    $partes = [];
    foreach (array_slice($fragmentos, 0, 2) as $f) {
        $texto = $f['resumen'] !== '' ? $f['resumen'] : mb_substr($f['texto'], 0, 280);
        $partes[] = $f['titulo'] . ': ' . rtrim($texto, '. ') . '.';
    }
    return 'Esto es lo que dice la ayuda. ' . implode(' ', $partes);
}

function ayudaIAPrompt(string $pregunta, array $fragmentos): string
{
    $manual = '';
    foreach ($fragmentos as $i => $f) {
        $manual .= '[' . ($i + 1) . '] ' . $f['titulo'] . ($f['ruta'] !== '' ? ' (menú: ' . $f['titulo'] . ')' : '') . "\n"
            . mb_substr($f['texto'], 0, 1800) . "\n\n";
    }
    return 'Eres el asistente de ayuda de Serena, una plataforma escolar de bienestar emocional. '
        . 'Responde la pregunta de la persona usando ÚNICAMENTE el manual de abajo. '
        . 'Reglas: español sencillo y amable; máximo 5 oraciones o 5 pasos cortos; si hay pasos, enuméralos; '
        . 'si el manual no contiene la respuesta, dilo con claridad y sugiere usar el buscador de la ayuda o consultar a quien administra Serena; '
        . 'no inventes botones, rutas ni funciones; no des consejos médicos ni psicológicos; no hables de otros temas; '
        . 'no uses viñetas con símbolos ni encabezados. Termina la última oración con punto final.'
        . "\n\nMANUAL:\n" . $manual
        . "PREGUNTA:\n" . $pregunta;
}

/** ¿Ya se gastó el cupo diario de preguntas con IA? (protege el límite de los modelos gratis) */
function ayudaIACupoAgotado(PDO $pdo): bool
{
    $max = (int) entornoServer('OPENROUTER_AYUDA_MAX_DIA', '200');
    try {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM ia_registro WHERE tarea = 'ayuda' AND DATE(fecha_hora) = CURDATE()")->fetchColumn();
        return $n >= $max;
    } catch (Throwable $e) {
        return false; // sin bitácora no se puede contar: se permite
    }
}

/** Límite por sesión: 8 preguntas cada 10 minutos. Devuelve true si hay que frenar. */
function ayudaIALimiteSesion(): bool
{
    $ahora = time();
    $marcas = array_values(array_filter((array) ($_SESSION['ayuda_ia_marcas'] ?? []), fn($t) => $t > $ahora - AYUDA_IA_VENTANA_S));
    if (count($marcas) >= AYUDA_IA_MAX_POR_VENTANA) {
        $_SESSION['ayuda_ia_marcas'] = $marcas;
        return true;
    }
    $marcas[] = $ahora;
    $_SESSION['ayuda_ia_marcas'] = $marcas;
    return false;
}

/**
 * Responde una pregunta. @return array{respuesta:string,fuentes:array<int,array{titulo:string,ancla:string}>,ia:bool,crisis:bool}
 */
function ayudaIAResponder(PDO $pdo, string $pregunta, string $rol): array
{
    $pregunta = trim(preg_replace('/\s+/u', ' ', $pregunta) ?? '');
    $pregunta = mb_substr($pregunta, 0, AYUDA_IA_MAX_PREGUNTA, 'UTF-8');

    if (ayudaIAEsCrisis($pregunta)) {
        return ['respuesta' => ayudaIAMensajeCrisis(), 'fuentes' => [], 'ia' => false, 'crisis' => true];
    }

    $fragmentos = ayudaIABuscar($pregunta, $rol, 3);
    $fuentes = [];
    foreach ($fragmentos as $f) {
        if ($f['ancla'] !== '') {
            $fuentes[] = ['titulo' => $f['titulo'], 'ancla' => $f['ancla']];
        }
    }
    $base = ayudaIARespuestaBase($fragmentos);

    // Sin coincidencias, sin IA configurada o con el cupo del día agotado: respuesta del manual.
    if (!$fragmentos || !iaDisponible() || ayudaIACupoAgotado($pdo)) {
        return ['respuesta' => $base, 'fuentes' => $fuentes, 'ia' => false, 'crisis' => false];
    }

    $anonima = iaAnonimizar($pregunta);
    $usoIA = false;
    $clave = 'ayuda_ia_' . ($rol !== '' ? $rol : 'publico') . '_' . md5(implode(' ', ayudaIAPalabras($pregunta)));

    $texto = obtenerTextoIACacheado(
        $pdo,
        $clave,
        fn() => $base,
        function (string $textoBase) use ($anonima, $fragmentos, &$usoIA): ?string {
            $r = iaLlamar(ayudaIAPrompt($anonima, $fragmentos), ['tarea' => 'ayuda', 'temperatura' => 0.3, 'max_tokens' => 500]);
            $usoIA = $r !== null;
            return $r;
        }
    );

    return ['respuesta' => $texto, 'fuentes' => $fuentes, 'ia' => $usoIA || $texto !== $base, 'crisis' => false];
}
