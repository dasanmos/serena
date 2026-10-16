<?php
declare(strict_types=1);

/**
 * Blindaje de SIMAT/SIMPADE: si algo falla al cargar datos o dibujar una vista, el error se muestra DENTRO del módulo
 * (con la causa para administración) en vez de cortar la página. Así el pie y los scripts siempre se cargan:
 * el menú y los botones siguen funcionando y se ve qué hay que corregir.
 */
if (!function_exists('smPanelError')) {
    function smPanelError(Throwable $ex, string $donde): string
    {
        error_log('[' . $donde . '] ' . get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
        $rol = (string) ($_SESSION['rol'] ?? '');
        $verDetalle = in_array($rol, ['administrador', 'superadmin', 'secretaria', 'rector'], true);
        $h = '<div class="mensaje mensaje-error sm-falla" role="alert"><div><strong>No se pudo cargar esta sección.</strong> ';
        if ($verDetalle) {
            $h .= 'Causa: <code>' . htmlspecialchars(mb_substr($ex->getMessage(), 0, 400), ENT_QUOTES) . '</code>'
                . '<br><small>' . htmlspecialchars(basename($ex->getFile()) . ':' . $ex->getLine(), ENT_QUOTES) . '</small>'
                . '<br>Suele deberse a una migración sin ejecutar (revisa sql/migraciones, sobre todo 034, 036, 038, 040 y 041_simat_simpade) o a una tabla incompleta.';
        } else {
            $h .= 'Avisa a la administración del colegio para que lo revise.';
        }
        return $h . '</div></div>';
    }
}
