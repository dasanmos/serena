<?php
// Visor de cámara para el reconocimiento facial (activación en seguridad.php y
// verificación en verificacion_2pasos.php). Debe ir DENTRO de un <form>.
// Variables: $rostroModo = 'registro' | 'verificar'
// La lógica vive en assets/js/rostro.js; la librería (face-api.js) se descarga
// solo cuando la persona toca "Encender cámara".
if (!class_exists('VerificacionDosPasosRostro')) { http_response_code(403); exit; } // no se abre directo
$rostroModo = ($rostroModo ?? 'verificar') === 'registro' ? 'registro' : 'verificar';
$rostroMuestras = $rostroModo === 'registro' ? VerificacionDosPasosRostro::MUESTRAS : 1;
$rostroCampo = $rostroModo === 'registro' ? 'muestras' : 'descriptor';
?>
<div class="rostro" data-rostro data-modo="<?= $rostroModo ?>" data-muestras="<?= $rostroMuestras ?>"
     data-modelos="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/model"
     data-libreria="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.js"
     data-libreria-sri="<?= FACEAPI_SRI_HASH ?>">
    <div class="rostro-visor">
        <div class="rostro-reposo" aria-hidden="true">🙂</div>
        <video class="rostro-video" data-rostro-video autoplay muted playsinline
               aria-label="Vista previa de tu cámara"></video>
        <div class="rostro-guia" aria-hidden="true"></div>
    </div>
    <p class="rostro-estado" data-rostro-estado role="status" aria-live="polite">
        Usaremos la cámara solo para comparar tu rostro. No se guardan fotos ni video.
    </p>
    <?php if ($rostroModo === 'registro'): ?>
        <div class="puntos-codigos rostro-progreso" data-rostro-progreso role="img"
             aria-label="Capturas completadas: 0 de <?= $rostroMuestras ?>">
            <?php for ($i = 0; $i < $rostroMuestras; $i++): ?><span class="punto-codigo"></span><?php endfor; ?>
        </div>
    <?php endif; ?>
    <input type="hidden" name="<?= $rostroCampo ?>" data-rostro-campo>
    <noscript><p class="mensaje mensaje-aviso">Para usar el reconocimiento facial activa JavaScript.</p></noscript>
    <div class="rostro-acciones">
        <button type="button" data-rostro-iniciar>Encender cámara</button>
    </div>
</div>
