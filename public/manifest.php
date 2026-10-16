<?php

declare(strict_types=1);

require __DIR__ . '/../src/config/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$base = rtrim(BASE_URL, '');

$manifest = [

    'name' => 'Serena - Bienestar emocional',

    'short_name' => 'Serena',

    'description' =>
        'Registro emocional, sueño, hábitos, metas y apoyo de psicología y orientación para el colegio.',

    'start_url' => $base . '/login.php',

    'scope' => $base . '/',

    'display' => 'standalone',

    'background_color' => '#F5F3EC',

    'theme_color' => '#E8B923',

    'orientation' => 'portrait-primary',

    'lang' => 'es',

    'icons' => [

        [
            'src' => $base . '/assets/img/logo/icon-192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any'
        ],

        [
            'src' => $base . '/assets/img/logo/icon-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any'
        ],

        [
            'src' => $base . '/assets/img/logo/icon-maskable-512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable'
        ]

    ]

];

echo json_encode(
    $manifest,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE |
    JSON_PRETTY_PRINT
);

exit;