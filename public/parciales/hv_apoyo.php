<?php
// Pestaña «Aula de apoyo» (PIAR): plan + tabla de seguimientos (Fecha, Descripción, Observación, Actualizado el, Por el usuario).
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }
$apoyo = Expediente::apoyo($pdo, $estId);
$segApoyo = ($apoyo && $cap['ver_apoyo_completo']) ? Expediente::seguimientosApoyo($pdo, (int) $apoyo['id']) : [];
?>
<h2 class="hv-titulo">Aula de apoyo (PIAR) <?= $apoyo ? '<span class="estado-pill">' . $e($apoyo['estado']) . '</span>' : '' ?></h2>
<?php if ($cap['ver_apoyo_completo'] && $cap['escribir_apoyo']): ?>
    <form action="estudiante_ficha.php" method="POST" novalidate>
        <?php $ocultos('apoyo_guardar'); ?>
        <div class="hv-rejilla">
            <label class="hv-campo"><span class="hv-etq">Estado</span><select name="estado"><option value="activo" <?= ($apoyo['estado'] ?? 'activo') === 'activo' ? 'selected' : '' ?>>Activo</option><option value="cerrado" <?= ($apoyo['estado'] ?? '') === 'cerrado' ? 'selected' : '' ?>>Cerrado</option></select></label>
            <label class="hv-campo"><span class="hv-etq">Fecha del PIAR</span><input type="date" name="piar_fecha" value="<?= $e($apoyo['piar_fecha'] ?? '') ?>"></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Condición o diagnóstico (reservado: orientación y directivos)</span><input type="text" name="condicion" maxlength="255" value="<?= $e($apoyo['condicion'] ?? '') ?>" data-sin-voz></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Barreras identificadas</span><textarea name="barreras" rows="3" maxlength="5000"><?= $e($apoyo['barreras'] ?? '') ?></textarea></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Ajustes razonables (los ven los docentes del estudiante)</span><textarea name="ajustes" rows="3" maxlength="5000"><?= $e($apoyo['ajustes'] ?? '') ?></textarea></label>
            <label class="hv-campo hv-ancho"><span class="hv-etq">Estrategias de apoyo</span><textarea name="estrategias" rows="3" maxlength="5000"><?= $e($apoyo['estrategias'] ?? '') ?></textarea></label>
        </div>
        <p class="hv-acciones"><button type="submit">Guardar plan de apoyo</button><?= $apoyo && $apoyo['responsable'] ? ' <span class="texto-suave">Última edición por ' . $e($apoyo['responsable']) . '</span>' : '' ?></p>
    </form>
    <?php if ($apoyo): ?>
    <h3>Seguimiento</h3>
    <div class="acad-tabla-envoltura"><table class="acad-tabla">
        <caption class="sr-only">Seguimientos del aula de apoyo</caption>
        <thead><tr><th scope="col">Fecha</th><th scope="col">Descripción</th><th scope="col">Observación</th><th scope="col">Actualizado el</th><th scope="col">Por el usuario</th></tr></thead>
        <tbody>
        <?php foreach ($segApoyo as $s): ?>
            <tr><td><?= $e($s['fecha']) ?></td><td style="white-space:pre-line"><?= $e($s['nota']) ?></td><td><?= $e($s['observacion'] ?? '') ?: '—' ?></td>
                <td><?= $e(date('d/m/Y H:i', strtotime((string) $s['creado_en']))) ?></td><td><?= $e($s['autor'] ?? '') ?: '—' ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$segApoyo): ?><tr><td colspan="5" class="texto-suave">Sin registros.</td></tr><?php endif; ?>
        </tbody></table></div>
    <details class="no-print"><summary>Agregar seguimiento</summary>
        <form action="estudiante_ficha.php" method="POST" novalidate>
            <?php $ocultos('apoyo_seguimiento'); ?>
            <div class="hv-rejilla">
                <label class="hv-campo"><span class="hv-etq">Fecha</span><input type="date" name="fecha" value="<?= $e($hoyIso) ?>"></label>
                <label class="hv-campo hv-ancho"><span class="hv-etq">Descripción *</span><textarea name="nota" rows="3" maxlength="5000" required></textarea></label>
                <label class="hv-campo hv-ancho"><span class="hv-etq">Observación</span><input type="text" name="observacion" maxlength="500"></label>
            </div>
            <p class="hv-acciones"><button type="submit">Registrar seguimiento</button></p>
        </form>
    </details>
    <?php endif; ?>
<?php elseif ($apoyo && $apoyo['estado'] === 'activo'): ?>
    <p class="texto-suave">Estos son los ajustes que el equipo de apoyo definió para este estudiante. Tenlos en cuenta al planear y evaluar.</p>
    <dl class="acad-detalle">
        <div><dt>Ajustes razonables</dt><dd style="white-space:pre-line"><?= $e($apoyo['ajustes'] ?? '') ?: '—' ?></dd></div>
        <div><dt>Estrategias de apoyo</dt><dd style="white-space:pre-line"><?= $e($apoyo['estrategias'] ?? '') ?: '—' ?></dd></div>
    </dl>
<?php else: ?>
    <p class="texto-suave">Este estudiante no tiene un plan de apoyo activo.</p>
<?php endif; ?>
