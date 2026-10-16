<?php
// Piezas compartidas por Anecdotario, Hoja de vida (observador) y Procesos disciplinarios.
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }

/** Celda «Evidencias»: miniaturas (servidas con control de acceso) + adjuntar/quitar. */
$celdaEvidencias = static function (string $origen, int $origenId, array $lista, bool $puedeAdjuntar, bool $puedeQuitar, string $grupo) use ($e, $ocultos): void {
    foreach ($lista as $i => $ev) {
        $href = BASE_URL . '/evidencia_ver.php?id=' . (int) $ev['id'];
        echo '<span class="hv-evidencia"><a href="' . $e($href) . '" target="_blank" rel="noopener"><img class="hv-miniatura" src="' . $e($href) . '" alt="Evidencia ' . ($i + 1) . '" loading="lazy"></a>';
        if ($puedeQuitar) {
            echo '<form action="estudiante_ficha.php" method="POST" onsubmit="return confirm(\'¿Eliminar esta evidencia?\');" class="no-print">';
            $ocultos('evid_eliminar', ['origen' => $origen, 'origen_id' => $origenId, 'evid_id' => (int) $ev['id'], 'grupo' => $grupo]);
            echo '<button type="submit" class="hv-quitar" aria-label="Eliminar evidencia ' . ($i + 1) . '">✕</button></form>';
        }
        echo '</span> ';
    }
    if (!$lista) { echo '<span class="texto-suave">—</span>'; }
    if ($puedeAdjuntar && count($lista) < HojaVida::EVIDENCIAS_MAX) {
        echo '<form action="estudiante_ficha.php" method="POST" enctype="multipart/form-data" class="hv-adjuntar no-print">';
        $ocultos('evid_agregar', ['origen' => $origen, 'origen_id' => $origenId, 'grupo' => $grupo]);
        echo '<input type="file" name="evidencia" accept="image/jpeg,image/png,image/webp,image/gif" required aria-label="Imagen de evidencia"> <button type="submit" class="boton-secundario">Adjuntar</button></form>';
    }
};

$etiquetaSituacion = static fn (?string $s): string => $s ? 'Tipo ' . $s : '—';
