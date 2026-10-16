<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/auditor.php';
require_once __DIR__ . '/../../src/helpers/respaldo.php';
require_once __DIR__ . '/../../src/modelos/Grado.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('administrador');

/**
 * Promoción de año: para cada grupo, el administrador elige una de tres acciones:
 *   - No cambiar (por defecto: el grupo se deja tal cual).
 *   - Pasar a otro grado (ej. Sexto A -> Séptimo A): el grupo SIGUE SIENDO el mismo
 *     registro (mismo id, mismo docente asignado, misma historia) — solo cambia su
 *     grado_id. Los estudiantes no se mueven de grupo_id porque no hace falta:
 *     siguen "en su salón", que ahora representa el grado siguiente.
 *   - Egresar (grado de salida, ej. Once): desactiva la cuenta de todos sus
 *     estudiantes activos (usuarios.activo = 0) — igual que "Desactivar" en
 *     Usuarios, así que pueden reactivarse a mano si algo salió mal. NO se borra
 *     nada. El grupo queda vacío, listo para reutilizarse el año siguiente
 *     (reasignándole un grado y docente nuevos) o para dejarlo sin uso.
 *
 * Es una operación masiva e IRREVERSIBLE en la práctica (aunque cada cambio
 * individual se puede deshacer a mano, deshacer cientos a la vez no es realista),
 * así que: exige escribir una palabra de confirmación, y crea una copia de
 * seguridad justo antes de ejecutar — si algo sale mal, queda un respaldo de
 * hace segundos para restaurar.
 */

$grados = Grado::listarTodos($pdo);
$nombrePorGradoId = array_column($grados, 'nombre', 'id');
$grupos = Grupo::listarConGradoYConteo($pdo);
$error = '';
$resultado = null;

// Construye la lista de cambios a partir de $_POST['destino'][grupoId] = 'nocambiar' | 'egresar' | '<gradoId>'
$leerCambios = function () use ($grupos): array {
    $cambios = [];
    $destinos = $_POST['destino'] ?? [];
    foreach ($grupos as $g) {
        $valor = $destinos[$g['id']] ?? 'nocambiar';
        if ($valor === 'nocambiar') {
            continue;
        }
        if ($valor === 'egresar') {
            $cambios[] = ['grupo' => $g, 'tipo' => 'egresar'];
        } elseif (ctype_digit((string) $valor) && (int) $valor !== (int) $g['grado_id']) {
            $cambios[] = ['grupo' => $g, 'tipo' => 'grado', 'grado_destino_id' => (int) $valor];
        }
    }
    return $cambios;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'previsualizar') {
        $cambios = $leerCambios();
        if (empty($cambios)) {
            $error = 'No seleccionaste ningún cambio.';
        } else {
            $resultado = ['modo' => 'previa', 'cambios' => $cambios];
        }
    } elseif ($accion === 'ejecutar') {
        if (trim((string) ($_POST['confirmacion'] ?? '')) !== 'PROMOVER') {
            $error = 'Escribe PROMOVER (en mayúsculas) para confirmar.';
        } else {
            $cambios = $leerCambios();
            if (empty($cambios)) {
                $error = 'No hay ningún cambio para aplicar.';
            } else {
                // Respaldo de seguridad justo antes — si algo sale mal, queda un punto
                // de restauración de hace segundos. Si el respaldo falla, se aborta
                // toda la promoción: mejor no tocar nada que tocarlo sin red de apoyo.
                $respaldo = crearRespaldoSeguro($pdo, 'antes de promoción de año');
                if (!$respaldo['exito']) {
                    $error = 'No se aplicó ningún cambio: no se pudo crear la copia de seguridad previa (' . $respaldo['mensaje'] . ').';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $resumen = ['grados_cambiados' => [], 'egresados' => []];
                        foreach ($cambios as $cambio) {
                            if ($cambio['tipo'] === 'grado') {
                                Grupo::cambiarGrado($pdo, (int) $cambio['grupo']['id'], $cambio['grado_destino_id']);
                                $resumen['grados_cambiados'][] = $cambio['grupo']['grado'] . ' ' . $cambio['grupo']['nombre'];
                            } else {
                                $n = Grupo::egresarEstudiantes($pdo, (int) $cambio['grupo']['id']);
                                if ($n > 0) {
                                    $resumen['egresados'][] = $cambio['grupo']['grado'] . ' ' . $cambio['grupo']['nombre'] . ' (' . $n . ')';
                                }
                            }
                        }
                        $pdo->commit();
                        auditarAccion($pdo, 'promocion_anio', 'grupos', 0, [], $resumen);
                        $resultado = ['modo' => 'hecho', 'resumen' => $resumen, 'respaldo' => $respaldo['ruta'] ?? null];
                        $grupos = Grupo::listarConGradoYConteo($pdo); // refrescar la tabla de abajo
                    } catch (Throwable $e) {
                        $pdo->rollBack();
                        error_log('promocion.php: ' . $e->getMessage());
                        $error = 'Ocurrió un error y no se aplicó ningún cambio. La copia de seguridad de justo antes está disponible en Configuración.';
                    }
                }
            }
        }
    }
}

$tituloPagina = 'Promoción de año';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Promoción de año</h1>
<p class="texto-suave">
    Sube de grado a los grupos en bloque al terminar el año, o egresa a los estudiantes
    del grado de salida. Revisa bien antes de confirmar: es una operación masiva.
</p>

<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if ($resultado && $resultado['modo'] === 'hecho'): ?>
<section class="panel-seccion tarjeta-acento-exito">
    <h2>Promoción aplicada</h2>
    <?php if (!empty($resultado['respaldo'])): ?>
        <p class="texto-suave">Se creó una copia de seguridad justo antes de aplicar los cambios (visible en Configuración).</p>
    <?php endif; ?>
    <?php if (!empty($resultado['resumen']['grados_cambiados'])): ?>
        <p><strong>Grupos que cambiaron de grado:</strong> <?= htmlspecialchars(implode(', ', $resultado['resumen']['grados_cambiados'])) ?></p>
    <?php endif; ?>
    <?php if (!empty($resultado['resumen']['egresados'])): ?>
        <p><strong>Estudiantes egresados:</strong> <?= htmlspecialchars(implode(', ', $resultado['resumen']['egresados'])) ?></p>
    <?php endif; ?>
    <p class="texto-suave">Los estudiantes egresados no se borraron: quedaron con la cuenta desactivada. Se pueden reactivar desde Usuarios si algo salió mal.</p>
    <a href="grupos.php" class="boton-secundario">Ir a Grupos</a>
</section>

<?php elseif ($resultado && $resultado['modo'] === 'previa'): ?>
<section class="panel-seccion tarjeta-acento-aviso">
    <h2>Confirma la promoción</h2>
    <p>Esto es lo que va a pasar:</p>
    <ul class="lista-simple">
        <?php foreach ($resultado['cambios'] as $c): ?>
            <li>
                <?php if ($c['tipo'] === 'grado'): ?>
                    <strong><?= htmlspecialchars($c['grupo']['grado'] . ' ' . $c['grupo']['nombre']) ?></strong>
                    (<?= (int) $c['grupo']['total_estudiantes'] ?> estudiantes) pasa a
                    <strong><?= htmlspecialchars($nombrePorGradoId[$c['grado_destino_id']] ?? '?') ?></strong>
                <?php else: ?>
                    Se egresan (desactivan) los <strong><?= (int) $c['grupo']['total_estudiantes'] ?> estudiantes</strong> de
                    <strong><?= htmlspecialchars($c['grupo']['grado'] . ' ' . $c['grupo']['nombre']) ?></strong>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="texto-suave">Antes de aplicar esto, Serena crea automáticamente una copia de seguridad.</p>
    <form action="promocion.php" method="POST">
        <?php campoCSRF(); ?>
        <input type="hidden" name="accion" value="ejecutar">
        <?php foreach ($resultado['cambios'] as $c): ?>
            <input type="hidden" name="destino[<?= $c['grupo']['id'] ?>]" value="<?= $c['tipo'] === 'egresar' ? 'egresar' : $c['grado_destino_id'] ?>">
        <?php endforeach; ?>
        <label for="confirmacion">Escribe <strong>PROMOVER</strong> para confirmar</label>
        <input type="text" id="confirmacion" name="confirmacion" autocomplete="off" required>
        <div class="u-flex u-gap-md u-mt-sm">
            <button type="submit" class="boton-peligro">Sí, aplicar la promoción</button>
            <a href="promocion.php" class="boton-secundario">Cancelar</a>
        </div>
    </form>
</section>

<?php else: ?>
<form action="promocion.php" method="POST">
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion" value="previsualizar">
    <div class="tabla-wrapper">
    <table class="tabla-datos tabla-adaptable">
        <thead><tr><th>Grupo</th><th>Estudiantes activos</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($grupos as $g): ?>
            <tr>
                <td data-etiqueta="Grupo"><?= htmlspecialchars($g['grado'] . ' ' . $g['nombre']) ?></td>
                <td data-etiqueta="Estudiantes"><?= (int) $g['total_estudiantes'] ?></td>
                <td data-etiqueta="Acción">
                    <select name="destino[<?= $g['id'] ?>]">
                        <option value="nocambiar">No cambiar</option>
                        <?php foreach ($grados as $gr): if ((int) $gr['id'] === (int) $g['grado_id']) continue; ?>
                            <option value="<?= $gr['id'] ?>">Pasar a: <?= htmlspecialchars($gr['nombre']) ?></option>
                        <?php endforeach; ?>
                        <option value="egresar">🎓 Egresar (grado de salida)</option>
                    </select>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <button type="submit" class="boton-acceso u-mt-md">Previsualizar cambios</button>
</form>
<?php endif; ?>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
