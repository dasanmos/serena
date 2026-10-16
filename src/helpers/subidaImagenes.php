<?php
declare(strict_types=1);

// Validación y guardado seguro de imágenes subidas por el usuario.
// Reglas duras, sin excepciones: nunca se confía en el nombre de archivo ni el
// Content-Type que manda el navegador — ambos son controlados por quien sube el
// archivo y se pueden falsificar trivialmente.

const SUBIDA_TAMANO_MAX = 5 * 1024 * 1024; // 5 MB por imagen

const SUBIDA_TIPOS_PERMITIDOS = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_WEBP => 'webp',
    IMAGETYPE_GIF  => 'gif',
];

// Guarda un archivo subido (una entrada de $_FILES) de forma segura en $carpetaDestino.
// Devuelve el nombre de archivo generado (no la ruta completa) si todo salió bien,
// o lanza RuntimeException con un mensaje apto para mostrar al usuario si algo falló.
function guardarImagenSubida(array $archivoSubido, string $carpetaDestino): string
{
    if (($archivoSubido['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('__vacio__'); // señal interna: no se subió nada, no es un error real
    }
    if ($archivoSubido['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen (código ' . $archivoSubido['error'] . ').');
    }
    if (!is_uploaded_file($archivoSubido['tmp_name'])) {
        throw new RuntimeException('Archivo inválido.');
    }
    if ($archivoSubido['size'] > SUBIDA_TAMANO_MAX) {
        throw new RuntimeException('La imagen pesa más de 5 MB.');
    }

    // getimagesize() lee los bytes reales del archivo, no el nombre ni el Content-Type
    // declarado por el navegador. Si esto falla o el tipo no está en la whitelist,
    // el archivo se rechaza — así un .php disfrazado de .jpg nunca pasa.
    $info = @getimagesize($archivoSubido['tmp_name']);
    if ($info === false || !isset(SUBIDA_TIPOS_PERMITIDOS[$info[2]])) {
        throw new RuntimeException('El archivo no es una imagen válida (jpg, png, webp o gif).');
    }
    $extension = SUBIDA_TIPOS_PERMITIDOS[$info[2]];

    if (!is_dir($carpetaDestino)) {
        mkdir($carpetaDestino, 0755, true);
    }

    // Nombre generado al azar — nunca el nombre original. Evita colisiones,
    // path traversal, y filtración de nombres de archivo del dispositivo del usuario.
    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaCompleta = rtrim($carpetaDestino, '/') . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivoSubido['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No se pudo guardar la imagen en el servidor.');
    }

    return $nombreArchivo;
}
