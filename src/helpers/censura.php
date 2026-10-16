<?php
declare(strict_types=1);

// Filtro de palabras soeces — se aplica SOLO a campos que puede ver personal
// distinto al estudiante que los escribió (títulos de metas y hábitos, visibles
// para el docente en docente/estudiante_detalle.php).
//
// Deliberadamente NO se aplica al chat con psicólogo/orientador ni al diario
// personal: son espacios donde el estudiante necesita poder expresarse sin
// filtro para que el profesional tenga el contexto real, y censurar ahí podría
// tapar una señal de alerta genuina en vez de protegerlo.
//
// Lista corta y de uso común en Colombia — no pretende ser exhaustiva, es un
// filtro de buen gusto para textos que van a un contexto escolar compartido,
// no un sistema de moderación de seguridad.

const CENSURA_PALABRAS = [
    'mierda', 'puto', 'puta', 'gonorrea', 'malparido', 'malparida', 'hijueputa',
    'hijodeputa', 'marica', 'gilipollas', 'pendejo', 'pendeja', 'cabron', 'cabrón',
    'joder', 'coño', 'verga', 'chimba', 'huevon', 'huevón', 'weon', 'culiao', 'culiado',
];

function censurarTexto(string $texto): string
{
    if ($texto === '') return $texto;
    $patron = '/\b(' . implode('|', array_map(
        fn($p) => preg_quote($p, '/'),
        CENSURA_PALABRAS
    )) . ')\b/iu';

    return preg_replace_callback($patron, function ($m) {
        $palabra = $m[0];
        $largo = mb_strlen($palabra);
        return mb_substr($palabra, 0, 1) . str_repeat('*', max(1, $largo - 1));
    }, $texto);
}
