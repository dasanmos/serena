<?php
declare(strict_types=1);

// Fotos de estudiantes (columna usuarios.foto). Quedan en src/uploads/fotos_estudiantes (fuera de public)
// y se muestran solo a través de public/docente/foto_estudiante.php, que revisa permisos.
// Pensado para hosting con poco espacio: si hay GD, cada foto se reduce a máx. 480 px y se guarda en JPG (~20-50 KB).

const FOTO_EST_BYTES_SUBIDA_MAX = 5 * 1024 * 1024; // lo máximo que se acepta al subir
const FOTO_EST_BYTES_SIN_GD_MAX = 600 * 1024;       // si no hay GD no se puede reducir: se exige que ya sea liviana
const FOTO_EST_LADO_MAX = 480;                       // px del lado mayor tras reducir
const FOTO_EST_PIXELES_MAX = 25000000;               // evita imágenes gigantes que agoten la memoria

function carpetaFotosEstudiantes(): string
{
    return __DIR__ . '/../uploads/fotos_estudiantes';
}

/** Ruta absoluta de la foto si el nombre es válido y el archivo existe; null en caso contrario. */
function rutaFotoEstudiante(?string $nombreArchivo): ?string
{
    if ($nombreArchivo === null || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $nombreArchivo)) {
        return null;
    }
    $ruta = carpetaFotosEstudiantes() . '/' . $nombreArchivo;
    return is_file($ruta) ? $ruta : null;
}

/**
 * Guarda (o reemplaza) la foto de un estudiante. $archivo es una entrada de $_FILES.
 * Devuelve el nombre de archivo guardado. Lanza RuntimeException con un mensaje apto para el usuario.
 */
function guardarFotoEstudiante(PDO $pdo, int $estudianteId, array $archivo): string
{
    $codigo = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($codigo === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Elige una foto primero.');
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la foto (código ' . $codigo . ').');
    }
    $tmp = (string) $archivo['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Archivo inválido.');
    }
    if ((int) $archivo['size'] > FOTO_EST_BYTES_SUBIDA_MAX) {
        throw new RuntimeException('La foto pesa más de 5 MB.');
    }

    // Se valida por el contenido real del archivo, nunca por el nombre ni el Content-Type.
    $info = @getimagesize($tmp);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if ($info === false || !isset($tipos[$info[2]])) {
        throw new RuntimeException('El archivo no es una imagen válida (jpg, png, webp o gif).');
    }
    if ($info[0] * $info[1] > FOTO_EST_PIXELES_MAX) {
        throw new RuntimeException('La imagen es demasiado grande en píxeles.');
    }

    $carpeta = carpetaFotosEstudiantes();
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }
    $base = bin2hex(random_bytes(16));
    $puedeReducir = function_exists('imagecreatefromstring') && function_exists('imagejpeg') && $info[2] !== IMAGETYPE_GIF;

    if ($puedeReducir) {
        $origen = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($origen === false) {
            throw new RuntimeException('No se pudo procesar la imagen.');
        }
        // Respeta la orientación de las fotos tomadas con celular (EXIF), si la extensión está disponible.
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') && function_exists('imagerotate')) {
            $exif = @exif_read_data($tmp);
            $giro = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
            if ($giro !== 0) {
                $rotada = @imagerotate($origen, $giro, 0);
                if ($rotada !== false) {
                    $origen = $rotada;
                }
            }
        }
        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1.0, FOTO_EST_LADO_MAX / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));
        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255)); // fondo blanco si la imagen era transparente
        imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        $nombre = $base . '.jpg';
        $ok = imagejpeg($lienzo, $carpeta . '/' . $nombre, 82);
        imagedestroy($origen);
        imagedestroy($lienzo);
        if (!$ok) {
            throw new RuntimeException('No se pudo guardar la foto en el servidor.');
        }
    } else {
        if ((int) $archivo['size'] > FOTO_EST_BYTES_SIN_GD_MAX) {
            throw new RuntimeException('La foto pesa más de 600 KB. Redúcela antes de subirla.');
        }
        $nombre = $base . '.' . $tipos[$info[2]];
        if (!move_uploaded_file($tmp, $carpeta . '/' . $nombre)) {
            throw new RuntimeException('No se pudo guardar la foto en el servidor.');
        }
    }

    $anterior = $pdo->prepare('SELECT foto FROM usuarios WHERE id = ?');
    $anterior->execute([$estudianteId]);
    $viejo = $anterior->fetchColumn();
    $pdo->prepare('UPDATE usuarios SET foto = ? WHERE id = ?')->execute([$nombre, $estudianteId]);
    $rutaVieja = rutaFotoEstudiante(is_string($viejo) ? $viejo : null);
    if ($rutaVieja !== null) {
        @unlink($rutaVieja);
    }
    return $nombre;
}

/** Quita la foto del estudiante (borra el archivo y vacía usuarios.foto). */
function eliminarFotoEstudiante(PDO $pdo, int $estudianteId): void
{
    $q = $pdo->prepare('SELECT foto FROM usuarios WHERE id = ?');
    $q->execute([$estudianteId]);
    $actual = $q->fetchColumn();
    $pdo->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?')->execute([$estudianteId]);
    $ruta = rutaFotoEstudiante(is_string($actual) ? $actual : null);
    if ($ruta !== null) {
        @unlink($ruta);
    }
}
