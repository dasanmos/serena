<?php
declare(strict_types=1);

require __DIR__ . '/../src/config/conexion.php';
require_once __DIR__ . '/../src/helpers/auth.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/auditor.php';
require_once __DIR__ . '/../src/helpers/colegio.php';
require_once __DIR__ . '/../src/helpers/captura.php';
require_once __DIR__ . '/../src/modelos/Simpade.php';

// SIMPADE: permanencia escolar. El sistema SUGIERE estudiantes en riesgo; una persona decide, hace el seguimiento y registra el retiro.
requiereRol('administrador', 'rector', 'coordinador', 'orientador', 'psicologo', 'secretaria_academica');
$rol = (string) $_SESSION['rol'];
$usuarioId = (int) $_SESSION['usuario_id'];
$colegioId = colegioParaCrear();
$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

require_once __DIR__ . '/parciales/sm_error.php';
// Todo lo que lee la base va protegido: si algo falla, la página se dibuja igual (con el error a la vista) y el menú sigue vivo.
$falla = null;
$listo = false;
$anios = [];
$anio = null;
$anioSel = (int) ($_GET['anio'] ?? $_POST['anio'] ?? 0);
try {
$listo = Simat::tablaExiste($pdo, 'simpade_riesgos') && Simat::tablaExiste($pdo, 'simpade_seguimientos') && Simat::tablaExiste($pdo, 'simpade_retiros');
$anios = Academico::anios($pdo, $colegioId);
// Sin año elegido: el actual si tiene matrículas activas; si no, el más reciente que sí las tenga.
$anio = $anioSel > 0 ? Academico::anio($pdo, $anioSel, $colegioId) : null;
if ($anio === null) {
    $anio = Simat::anioPredeterminado($pdo, $colegioId);
}
if ($anio === null && $anios) {
    $anio = $anios[0];
}
} catch (Throwable $ex) {
    $falla = $ex;
}
$anioId = $anio ? (int) $anio['id'] : 0;

$vistas = ['panel' => 'Panel', 'deteccion' => 'Detección', 'casos' => 'Casos', 'retiros' => 'Retiros'];
$vista = (string) ($_GET['vista'] ?? $_POST['vista'] ?? 'panel');
if (!isset($vistas[$vista])) {
    $vista = 'panel';
}
$smUrl = static fn (string $v, array $extra = []): string => 'simpade.php?' . http_build_query(array_merge(['anio' => $anioId, 'vista' => $v], $extra));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $volver = static fn (string $v, string $ok, string $err = '', array $extra = []) => capVolver('flash_simpade', $smUrl($v, $extra), $ok, $err);
    if (!$listo || !$anio) {
        $volver($vista, '', 'Falta ejecutar la migración sql/migraciones/041_simat_simpade.sql o crear un año lectivo.');
    }
    $resp = (int) ($_POST['responsable_id'] ?? 0) ?: null;

    if ($accion === 'caso_abrir') {
        $factores = array_map('strval', (array) ($_POST['factores'] ?? []));
        $err = Simpade::abrirCaso($pdo, $colegioId, $anioId, (int) ($_POST['estudiante_id'] ?? 0), (string) ($_POST['nivel'] ?? ''), $factores, (string) ($_POST['observacion'] ?? ''),
            $resp, ($_POST['origen'] ?? '') === 'sugerido' ? 'sugerido' : 'manual', (int) ($_POST['puntaje'] ?? 0), $usuarioId);
        if ($err === '') {
            auditarAccion($pdo, 'simpade_caso_abrir', 'simpade_riesgos', 0, [], ['estudiante_id' => (int) ($_POST['estudiante_id'] ?? 0), 'nivel' => (string) ($_POST['nivel'] ?? '')]);
        }
        $volver($err === '' && ($_POST['origen'] ?? '') === 'sugerido' ? 'deteccion' : 'casos', 'Caso abierto.', $err);
    }
    if ($accion === 'caso_actualizar') {
        $id = (int) ($_POST['caso_id'] ?? 0);
        $err = Simpade::actualizarCaso($pdo, $id, $colegioId, (string) ($_POST['estado'] ?? ''), (string) ($_POST['nivel'] ?? ''), $resp);
        if ($err === '') {
            auditarAccion($pdo, 'simpade_caso_actualizar', 'simpade_riesgos', $id, [], ['estado' => (string) ($_POST['estado'] ?? ''), 'nivel' => (string) ($_POST['nivel'] ?? '')]);
        }
        $volver('casos', 'Caso actualizado.', $err, ['caso' => $id]);
    }
    if ($accion === 'seguimiento_agregar') {
        $id = (int) ($_POST['caso_id'] ?? 0);
        $err = Simpade::agregarSeguimiento($pdo, $id, $colegioId, (string) ($_POST['tipo'] ?? ''), (string) ($_POST['fecha'] ?? ''), (string) ($_POST['nota'] ?? ''), (string) ($_POST['proxima_fecha'] ?? ''), $usuarioId);
        if ($err === '') {
            auditarAccion($pdo, 'simpade_seguimiento', 'simpade_riesgos', $id, [], ['tipo' => (string) ($_POST['tipo'] ?? '')]);
        }
        $volver('casos', 'Seguimiento registrado.', $err, ['caso' => $id]);
    }
    if ($accion === 'retiro_registrar') {
        $sid = (int) ($_POST['estudiante_id'] ?? 0);
        $err = Simpade::registrarRetiro($pdo, $colegioId, $anioId, $sid, (string) ($_POST['fecha'] ?? ''), (string) ($_POST['causa'] ?? ''), (string) ($_POST['detalle'] ?? ''),
            (string) ($_POST['destino'] ?? ''), ($_POST['familia_contactada'] ?? '') === '1', (string) ($_POST['estrategia'] ?? ''), ($_POST['retirar_matricula'] ?? '') === '1', $usuarioId);
        if ($err === '') {
            auditarAccion($pdo, 'simpade_retiro_registrar', 'simpade_retiros', 0, [], ['estudiante_id' => $sid, 'causa' => (string) ($_POST['causa'] ?? ''), 'retira_matricula' => ($_POST['retirar_matricula'] ?? '') === '1']);
        }
        $volver('retiros', 'Retiro registrado.', $err);
    }
    if ($accion === 'retiro_reportado') {
        $id = (int) ($_POST['retiro_id'] ?? 0);
        Simpade::marcarRetiroReportado($pdo, $id, $colegioId, ($_POST['valor'] ?? '1') === '1', $usuarioId);
        auditarAccion($pdo, 'simpade_retiro_reportado', 'simpade_retiros', $id, [], []);
        $volver('retiros', 'Listo.');
    }
    $volver($vista, '');
}

$flash = capFlash('flash_simpade');
$ind = null;
$responsables = [];
try {
    $ind = $anio && $listo ? Simpade::indicadores($pdo, $colegioId, $anioId) : null;
    $responsables = $anio && $listo && in_array($vista, ['deteccion', 'casos'], true) ? Simpade::posiblesResponsables($pdo, $colegioId) : [];
} catch (Throwable $ex) {
    $falla = $falla ?? $ex;
}
$casoId = (int) ($_GET['caso'] ?? 0);

$tituloPagina = 'SIMPADE';
$cssExtra = ['/assets/css/academico.css', '/assets/css/simat.css'];
$jsExtra = ['/assets/js/simat.js'];
require __DIR__ . '/parciales/encabezado.php';

$selResp = static function (?int $actual) use ($responsables, $e): string {
    $h = '<select name="responsable_id"><option value="0">Sin asignar</option>';
    foreach ($responsables as $r) {
        $h .= '<option value="' . (int) $r['id'] . '"' . ($actual === (int) $r['id'] ? ' selected' : '') . '>' . $e($r['nombre']) . ' (' . $e($r['rol']) . ')</option>';
    }
    return $h . '</select>';
};
$pillNivel = static fn (string $n): string => '<span class="estado-pill ' . ($n === 'alto' ? 'estado-rechazada' : ($n === 'medio' ? 'estado-en_revision' : 'estado-pendiente')) . '">' . $e(Simpade::NIVELES[$n] ?? $n) . '</span>';
?>
<div class="sm-modulo">
<h1>SIMPADE · Permanencia escolar</h1>
<p class="texto-suave">Detecta a tiempo a quien puede abandonar el colegio, documenta el seguimiento y registra la causa de cada retiro. <strong>Serena sugiere; una persona decide.</strong> No se conecta al SIMPADE: deja los datos listos para reportar.</p>

<?php if ($flash && $flash['error']): ?><p class="mensaje mensaje-error"><?= $e($flash['error']) ?></p><?php endif; ?>
<?php if ($flash && $flash['exito']): ?><p class="mensaje mensaje-exito"><?= $e($flash['exito']) ?></p><?php endif; ?>
<?php if (!$listo): ?><p class="mensaje mensaje-aviso">Falta ejecutar la migración <code>sql/migraciones/041_simat_simpade.sql</code>.</p><?php endif; ?>

<?php if ($falla !== null) { echo smPanelError($falla, 'simpade'); } ?>

<?php if ($falla === null) { ob_start(); try { ?>
<?php if (!$anio): ?>
    <p class="mensaje mensaje-aviso">No hay un año lectivo configurado.</p>
<?php else: ?>
<form action="simpade.php" method="GET" class="acad-filtros">
    <input type="hidden" name="vista" value="<?= $e($vista) ?>">
    <label>Año lectivo
        <select name="anio" onchange="this.form.submit()"><?php foreach ($anios as $a): ?><option value="<?= (int) $a['id'] ?>" <?= $anioId === (int) $a['id'] ? 'selected' : '' ?>><?= (int) $a['anio'] ?></option><?php endforeach; ?></select>
    </label>
</form>
<?php require __DIR__ . '/parciales/sm_diagnostico.php'; ?>

<nav class="sm-tabs" aria-label="Secciones de SIMPADE">
    <?php foreach ($vistas as $k => $n): ?><a href="<?= $e($smUrl($k)) ?>" class="<?= $vista === $k ? 'activa' : '' ?>"><?= $e($n) ?></a><?php endforeach; ?>
</nav>

<?php if ($vista === 'panel' && $ind): ?>
    <div class="sm-tarjetas">
        <div class="sm-tarjeta"><strong><?= $ind['matriculados'] ?></strong><span>Matriculados en el año</span></div>
        <div class="sm-tarjeta sm-error"><strong><?= $ind['retirados'] ?></strong><span>Retirados (<?= $e(number_format($ind['tasa_retiro'], 1, ',', '.')) ?> %)</span></div>
        <div class="sm-tarjeta sm-aviso"><strong><?= $ind['niveles']['alto'] ?></strong><span>Casos activos en riesgo alto</span></div>
        <div class="sm-tarjeta"><strong><?= $ind['casos']['activo'] + $ind['casos']['en_seguimiento'] ?></strong><span>Casos abiertos</span></div>
        <div class="sm-tarjeta <?= $ind['sin_seguimiento'] > 0 ? 'sm-error' : 'sm-ok' ?>"><strong><?= $ind['sin_seguimiento'] ?></strong><span>Sin seguimiento hace +<?= Simpade::DIAS_SIN_SEGUIMIENTO ?> días</span></div>
        <div class="sm-tarjeta <?= $ind['vencidos'] > 0 ? 'sm-error' : 'sm-ok' ?>"><strong><?= $ind['vencidos'] ?></strong><span>Con próximo contacto vencido</span></div>
        <div class="sm-tarjeta"><strong><?= $ind['efectividad'] !== null ? $ind['efectividad'] . ' %' : '—' ?></strong><span>Casos cerrados como superados</span></div>
    </div>
    <section class="panel-seccion">
        <h2>Retiros por causa</h2>
        <?php if (!$ind['por_causa']): ?><p class="texto-suave">Aún no hay retiros registrados con causa.</p><?php else: ?>
            <?php $maxCausa = max(1, (int) $ind['por_causa'][0]['n']); ?>
            <ul class="sm-causas"><?php foreach ($ind['por_causa'] as $c): ?><li><span><?= $e(Simpade::CAUSAS_RETIRO[$c['causa']] ?? $c['causa']) ?></span><strong><?= (int) $c['n'] ?></strong><span class="sm-barra" aria-hidden="true"><i style="width:<?= (int) round((int) $c['n'] * 100 / $maxCausa) ?>%"></i></span></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($ind['sin_reportar'] > 0): ?><p class="mensaje mensaje-aviso"><?= $ind['sin_reportar'] ?> retiro(s) sin marcar como reportados. <a href="<?= $e($smUrl('retiros')) ?>">Ver retiros</a>.</p><?php endif; ?>
    </section>

<?php elseif ($vista === 'deteccion'): ?>
    <?php $sug = $listo ? Simpade::sugerencias($pdo, $colegioId, $anioId) : []; ?>
    <p class="texto-suave">Estudiantes con matrícula activa cuyo conjunto de señales (inasistencia, notas perdidas, repitencia, extraedad, convivencia, trabajo, entorno) alcanza el umbral. Revisa el detalle, ajusta y <strong>confirma</strong> solo los casos que tú consideres reales. Nada se marca en riesgo automáticamente.</p>
    <?php if (!$sug): ?>
        <div class="estado-vacio"><p>No hay sugerencias nuevas. Los estudiantes con caso abierto no se vuelven a sugerir.</p></div>
    <?php endif; ?>
    <?php foreach ($sug as $s): ?>
        <details class="sm-sugerencia">
            <summary><strong><?= $e($s['nombre']) ?></strong> · <?= $e($s['grado'] . ' ' . $s['grupo']) ?> · <?= $pillNivel($s['nivel']) ?> · <?= (int) $s['puntaje'] ?> pts</summary>
            <form action="simpade.php" method="POST" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="caso_abrir"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="deteccion">
                <input type="hidden" name="origen" value="sugerido"><input type="hidden" name="estudiante_id" value="<?= (int) $s['estudiante_id'] ?>"><input type="hidden" name="puntaje" value="<?= (int) $s['puntaje'] ?>">
                <ul class="sm-problemas sm-p-aviso">
                    <?php foreach ($s['factores'] as $k => $detalle): ?>
                        <li><label class="sm-factor"><input type="checkbox" name="factores[]" value="<?= $e($k) ?>" checked> <strong><?= $e(Simpade::FACTORES[$k][1] ?? $k) ?></strong>: <?= $e($detalle) ?></label></li>
                    <?php endforeach; ?>
                </ul>
                <div class="acad-filtros">
                    <label>Nivel <select name="nivel"><?php foreach (Simpade::NIVELES as $k => $t): ?><option value="<?= $e($k) ?>" <?= $s['nivel'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
                    <label>Responsable <?= $selResp(null) ?></label>
                    <label class="sm-ancho">Observación <input type="text" name="observacion" maxlength="500"></label>
                    <button type="submit">Abrir caso</button>
                    <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $s['estudiante_id'] ?>">Hoja de vida</a>
                </div>
            </form>
        </details>
    <?php endforeach; ?>

    <section class="panel-seccion">
        <h2>Abrir un caso manualmente</h2>
        <?php $activos = $listo ? Simpade::estudiantesActivos($pdo, $colegioId, $anioId) : []; ?>
        <form action="simpade.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="caso_abrir"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="casos"><input type="hidden" name="origen" value="manual">
            <div class="acad-filtros">
                <label>Estudiante <select name="estudiante_id" required><option value="">Elige…</option><?php foreach ($activos as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $e($a['nombre'] . ' · ' . $a['grado'] . ' ' . $a['grupo']) ?></option><?php endforeach; ?></select></label>
                <label>Nivel <select name="nivel"><?php foreach (Simpade::NIVELES as $k => $t): ?><option value="<?= $e($k) ?>" <?= $k === 'medio' ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <label>Responsable <?= $selResp(null) ?></label>
            </div>
            <p class="texto-suave">Factores observados:
                <button type="button" class="boton-secundario" data-sm-marcar="#sm-factores-manual" data-sm-valor="0">Limpiar</button></p>
            <div class="sm-columnas" id="sm-factores-manual">
                <?php foreach (Simpade::FACTORES as $k => $f): ?><label class="sm-check"><input type="checkbox" name="factores[]" value="<?= $e($k) ?>"> <?= $e($f[1]) ?> <small class="texto-suave">(<?= $e($f[0]) ?>)</small></label><?php endforeach; ?>
            </div>
            <label>Observación <textarea name="observacion" rows="2" maxlength="2000"></textarea></label>
            <div class="acad-acciones"><button type="submit" <?= $listo ? '' : 'disabled' ?>>Abrir caso</button></div>
        </form>
    </section>

<?php elseif ($vista === 'casos'): ?>
    <?php
    $detalle = $casoId > 0 && $listo ? Simpade::caso($pdo, $casoId, $colegioId) : null;
    if ($detalle !== null):
        $segs = Simpade::seguimientos($pdo, $casoId);
    ?>
        <p><a href="<?= $e($smUrl('casos')) ?>">← Todos los casos</a></p>
        <section class="panel-seccion">
            <h2><?= $e($detalle['estudiante']) ?> · <?= $pillNivel((string) $detalle['nivel']) ?> <span class="estado-pill estado-pendiente"><?= $e(Simpade::ESTADOS[$detalle['estado']] ?? $detalle['estado']) ?></span></h2>
            <ul class="sm-problemas sm-p-aviso">
                <?php foreach (array_filter(explode(',', (string) $detalle['factores'])) as $k): ?><li><?= $e(Simpade::FACTORES[$k][1] ?? $k) ?> <small class="texto-suave">(<?= $e(Simpade::FACTORES[$k][0] ?? '') ?>)</small></li><?php endforeach; ?>
            </ul>
            <?php if (!empty($detalle['observacion'])): ?><p><?= nl2br($e($detalle['observacion'])) ?></p><?php endif; ?>
            <form action="simpade.php" method="POST" class="acad-filtros" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="caso_actualizar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="casos"><input type="hidden" name="caso_id" value="<?= $casoId ?>">
                <label>Estado <select name="estado"><?php foreach (Simpade::ESTADOS as $k => $t): ?><option value="<?= $e($k) ?>" <?= $detalle['estado'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <label>Nivel <select name="nivel"><?php foreach (Simpade::NIVELES as $k => $t): ?><option value="<?= $e($k) ?>" <?= $detalle['nivel'] === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <label>Responsable <?= $selResp($detalle['responsable_id'] !== null ? (int) $detalle['responsable_id'] : null) ?></label>
                <button type="submit">Guardar</button>
                <a class="boton-secundario" href="<?= $e(BASE_URL) ?>/estudiante_ficha.php?est=<?= (int) $detalle['estudiante_id'] ?>">Hoja de vida</a>
            </form>
        </section>
        <section class="panel-seccion">
            <h3>Acciones de permanencia</h3>
            <form action="simpade.php" method="POST" class="acad-filtros" novalidate>
                <?php campoCSRF(); ?>
                <input type="hidden" name="accion" value="seguimiento_agregar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="casos"><input type="hidden" name="caso_id" value="<?= $casoId ?>">
                <label>Tipo <select name="tipo"><?php foreach (Simpade::TIPOS_SEGUIMIENTO as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <label>Fecha <input type="date" name="fecha" value="<?= $e(date('Y-m-d')) ?>" required></label>
                <label>Próximo contacto <input type="date" name="proxima_fecha"></label>
                <label class="sm-ancho">Qué se hizo / acordó <textarea name="nota" rows="2" maxlength="3000" required></textarea></label>
                <button type="submit">Registrar</button>
            </form>
            <?php if (!$segs): ?><p class="texto-suave">Aún no hay acciones registradas.</p><?php else: ?>
                <ul class="sm-linea">
                    <?php foreach ($segs as $s): ?><li><strong><?= $e(date('d/m/Y', strtotime((string) $s['fecha']))) ?></strong> · <?= $e(Simpade::TIPOS_SEGUIMIENTO[$s['tipo']] ?? $s['tipo']) ?> · <?= $e($s['autor'] ?? '') ?><br><?= nl2br($e($s['nota'])) ?><?= $s['proxima_fecha'] ? '<br><small class="texto-suave">Próximo contacto: ' . $e(date('d/m/Y', strtotime((string) $s['proxima_fecha']))) . '</small>' : '' ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php else:
        $fe = (string) ($_GET['estado'] ?? '');
        $fn = (string) ($_GET['nivel'] ?? '');
        $casos = $listo ? Simpade::casos($pdo, $colegioId, $anioId, $fe, $fn) : [];
    ?>
        <form action="simpade.php" method="GET" class="acad-filtros">
            <input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="casos">
            <label>Estado <select name="estado" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Simpade::ESTADOS as $k => $t): ?><option value="<?= $e($k) ?>" <?= $fe === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <label>Nivel <select name="nivel" onchange="this.form.submit()"><option value="">Todos</option><?php foreach (Simpade::NIVELES as $k => $t): ?><option value="<?= $e($k) ?>" <?= $fn === $k ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
            <a class="boton-secundario" href="simpade_exportar.php?tipo=casos&amp;anio=<?= $anioId ?>">Descargar CSV</a>
        </form>
        <?php if (!$casos): ?><div class="estado-vacio"><p>No hay casos con este filtro. Empieza por la pestaña Detección.</p></div><?php else: ?>
        <div class="acad-filtros">
            <label>Buscar en la lista <input type="search" data-sm-filtro="#sm-tabla-casos" placeholder="Nombre del estudiante"></label>
            <span class="texto-suave" data-sm-contador="#sm-tabla-casos" aria-live="polite"></span>
        </div>
        <div class="acad-tabla-envoltura">
            <table class="acad-tabla" id="sm-tabla-casos">
                <thead><tr><th>Estudiante</th><th>Nivel</th><th>Estado</th><th>Responsable</th><th>Último seguimiento</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($casos as $c): ?>
                    <tr>
                        <td><?= $e($c['estudiante']) ?><br><small class="texto-suave"><?= $e(trim(($c['grado'] ?? '') . ' ' . ($c['grupo'] ?? ''))) ?></small></td>
                        <td><?= $pillNivel((string) $c['nivel']) ?></td>
                        <td><?= $e(Simpade::ESTADOS[$c['estado']] ?? $c['estado']) ?></td>
                        <td><?= $e($c['responsable'] ?? '—') ?></td>
                        <td><?= $c['ultimo_seguimiento'] ? $e(date('d/m/Y', strtotime((string) $c['ultimo_seguimiento']))) : '<span class="texto-suave">Ninguno</span>' ?> <small class="texto-suave">(<?= (int) $c['n_seguimientos'] ?>)</small>
                            <?php if (!empty($c['proxima']) && in_array($c['estado'], ['activo', 'en_seguimiento'], true)): $vence = (string) $c['proxima'] < date('Y-m-d'); ?>
                                <span class="<?= $vence ? 'sm-vencido' : 'sm-proximo' ?>"><?= $vence ? 'Contacto vencido' : 'Próximo contacto' ?> · <?= $e(date('d/m/Y', strtotime((string) $c['proxima']))) ?></span>
                            <?php endif; ?></td>
                        <td><a class="boton-secundario" href="<?= $e($smUrl('casos', ['caso' => (int) $c['id']])) ?>">Abrir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($vista === 'retiros'): ?>
    <?php
    $retiros = $listo ? Simpade::retiros($pdo, $colegioId, $anioId) : [];
    $activos = $listo ? Simpade::estudiantesActivos($pdo, $colegioId, $anioId) : [];
    ?>
    <p class="texto-suave">El rector informa por escrito y con motivo cada retiro (la causa se reporta en SIMPADE); guarda el soporte en la carpeta del estudiante. Registra aquí la causa y lo que se intentó para que permaneciera. Si marcas «Retirar la matrícula», Serena la pasa a retirada y deja la novedad de retiro pendiente en SIMAT.</p>
    <section class="panel-seccion">
        <h2>Registrar un retiro</h2>
        <form action="simpade.php" method="POST" novalidate>
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion" value="retiro_registrar"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="retiros">
            <div class="acad-filtros">
                <label>Estudiante <select name="estudiante_id" required><option value="">Elige…</option><?php foreach ($activos as $a): ?><option value="<?= (int) $a['id'] ?>"><?= $e($a['nombre'] . ' · ' . $a['grado'] . ' ' . $a['grupo']) ?></option><?php endforeach; ?></select></label>
                <label>Fecha <input type="date" name="fecha" value="<?= $e(date('Y-m-d')) ?>" required></label>
                <label>Causa <select name="causa" required><option value="">Elige…</option><?php foreach (Simpade::CAUSAS_RETIRO as $k => $t): ?><option value="<?= $e($k) ?>"><?= $e($t) ?></option><?php endforeach; ?></select></label>
                <label>Destino <select name="destino"><?php foreach (Simpade::DESTINOS as $k => $t): ?><option value="<?= $e($k) ?>" <?= $k === 'desconocido' ? 'selected' : '' ?>><?= $e($t) ?></option><?php endforeach; ?></select></label>
            </div>
            <div class="acad-filtros">
                <label class="sm-ancho">Detalle <input type="text" name="detalle" maxlength="300"></label>
                <label class="sm-ancho">Qué se intentó para que permaneciera <input type="text" name="estrategia" maxlength="300"></label>
            </div>
            <label class="sm-check"><input type="checkbox" name="familia_contactada" value="1"> Se contactó a la familia</label>
            <label class="sm-check"><input type="checkbox" name="retirar_matricula" value="1" checked> Retirar la matrícula del estudiante en Serena</label>
            <div class="acad-acciones"><button type="submit" <?= $listo ? '' : 'disabled' ?>>Registrar retiro</button></div>
        </form>
    </section>
    <div class="acad-acciones"><a class="boton-secundario" href="simpade_exportar.php?tipo=retiros&amp;anio=<?= $anioId ?>">Descargar CSV de retiros</a></div>
    <?php if (!$retiros): ?><div class="estado-vacio"><p>No hay retiros registrados con causa en este año.</p></div><?php else: ?>
    <div class="acad-filtros">
        <label>Buscar en la lista <input type="search" data-sm-filtro="#sm-tabla-ret" placeholder="Nombre o causa"></label>
        <span class="texto-suave" data-sm-contador="#sm-tabla-ret" aria-live="polite"></span>
    </div>
    <div class="acad-tabla-envoltura">
        <table class="acad-tabla" id="sm-tabla-ret">
            <thead><tr><th>Fecha</th><th>Estudiante</th><th>Causa</th><th>Destino</th><th>Reportado</th></tr></thead>
            <tbody>
            <?php foreach ($retiros as $t): ?>
                <tr>
                    <td><?= $e(date('d/m/Y', strtotime((string) $t['fecha']))) ?></td>
                    <td><?= $e($t['estudiante']) ?><br><small class="texto-suave"><?= $e(trim(($t['grado'] ?? '') . ' ' . ($t['grupo'] ?? ''))) ?></small></td>
                    <td><?= $e(Simpade::CAUSAS_RETIRO[$t['causa']] ?? $t['causa']) ?><?= !empty($t['detalle']) ? '<br><small class="texto-suave">' . $e($t['detalle']) . '</small>' : '' ?></td>
                    <td><?= $e(Simpade::DESTINOS[$t['destino']] ?? $t['destino']) ?></td>
                    <td>
                        <form action="simpade.php" method="POST">
                            <?php campoCSRF(); ?>
                            <input type="hidden" name="accion" value="retiro_reportado"><input type="hidden" name="anio" value="<?= $anioId ?>"><input type="hidden" name="vista" value="retiros"><input type="hidden" name="retiro_id" value="<?= (int) $t['id'] ?>">
                            <?php if ((int) $t['reportado'] === 1): ?><input type="hidden" name="valor" value="0"><span class="estado-pill estado-activa">Sí</span> <button type="submit" class="boton-secundario">Reabrir</button>
                            <?php else: ?><input type="hidden" name="valor" value="1"><button type="submit">Marcar reportado</button><?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php ob_end_flush(); } catch (Throwable $exv) { ob_end_clean(); echo smPanelError($exv, 'simpade:' . $vista); } } ?>
</div>

<?php require __DIR__ . '/parciales/pie.php'; ?>
