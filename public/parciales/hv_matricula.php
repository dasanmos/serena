<?php
// Pestaña «Matrícula»: Datos del estudiante · Familia · Características · Salud (como en Master2000).
if (!defined('HV_CARGADO')) { http_response_code(403); exit; }

$contactos = Expediente::contactos($pdo, $estId);

/** Un campo del catálogo: editable (input) o de solo lectura (texto). Devuelve '' si esta persona no puede verlo. */
$campo = static function (string $clave, array $c) use ($vals, $perm, $ctx, $e): string {
    if (!HojaVida::puedeVerCampo($c, $perm)) { return ''; }
    $ancho = !empty($c['full']) || $c['t'] === 'a' ? ' hv-ancho' : '';
    $actual = (string) ($vals[$clave] ?? '');
    if ($c['t'] === 'r') {
        $src = ['nombre' => $ctx['nombre'], 'correo' => $ctx['correo'] ?: '—', 'grupo' => $ctx['grupo'], 'matriculado' => $ctx['matriculado'], 'nacimiento' => $ctx['nacimiento']][$c['src']] ?? '—';
        return '<div class="hv-campo hv-ro' . $ancho . '"><span class="hv-etq">' . $e($c['l']) . '</span><span class="hv-val">' . $e($src) . '</span></div>';
    }
    if (!HojaVida::puedeEditarCampo($c, $perm)) {
        $txt = $c['t'] === 's' ? (($c['op'][$actual] ?? $actual) ?: '') : $actual;
        return '<div class="hv-campo hv-ro' . $ancho . '"><span class="hv-etq">' . $e($c['l']) . '</span><span class="hv-val">' . ($txt !== '' ? $e($txt) : '—') . '</span></div>';
    }
    $etq = $e($c['l']) . (!empty($c['req']) ? ' <abbr title="Obligatorio">*</abbr>' : '');
    $sinVoz = ($c['sens'] ?? '') === 'salud' ? ' data-sin-voz' : '';
    $max = (int) ($c['max'] ?? ($c['t'] === 'n' ? 3 : 120));
    $h = '<label class="hv-campo' . $ancho . '"><span class="hv-etq">' . $etq . '</span>';
    if ($c['t'] === 's') {
        $h .= '<select name="' . $e($clave) . '"><option value="">—</option>';
        $op = $c['op'];
        if ($actual !== '' && !isset($op[$actual])) { $op = [$actual => $actual] + $op; } // valor anterior que no está en la lista: se conserva
        foreach ($op as $k => $t) { $h .= '<option value="' . $e($k) . '"' . ($actual === (string) $k ? ' selected' : '') . '>' . $e($t) . '</option>'; }
        $h .= '</select>';
    } elseif ($c['t'] === 'a') {
        $h .= '<textarea name="' . $e($clave) . '" rows="3" maxlength="' . $max . '"' . $sinVoz . '>' . $e($actual) . '</textarea>';
    } elseif ($c['t'] === 'd') {
        $h .= '<input type="date" name="' . $e($clave) . '" value="' . $e($actual) . '">';
    } elseif ($c['t'] === 'n') {
        $h .= '<input type="number" name="' . $e($clave) . '" min="0" max="' . str_repeat('9', $max) . '" value="' . $e($actual) . '">';
    } else {
        $tipo = ($c['fmt'] ?? '') === 'email' ? 'email' : (($c['fmt'] ?? '') === 'tel' ? 'tel' : 'text');
        $h .= '<input type="' . $tipo . '" name="' . $e($clave) . '" maxlength="' . $max . '" value="' . $e($actual) . '"' . (!empty($c['req']) ? ' required' : '') . $sinVoz . '>';
    }
    return $h . '</label>';
};

/** ¿Hay al menos un campo editable en esta subpestaña? */
$hayEditable = static function (string $sk) use ($perm): bool {
    foreach (HojaVida::estructura()[$sk] as $campos) {
        foreach ($campos as $c) { if (HojaVida::puedeEditarCampo($c, $perm)) { return true; } }
    }
    return false;
};
$bloqueSub = static function (string $sk) use ($campo, $hayEditable, $ocultos, $e): void {
    $editable = $hayEditable($sk);
    if ($editable) { echo '<form action="estudiante_ficha.php" method="POST" novalidate>'; $ocultos('hv_guardar'); }
    foreach (HojaVida::estructura()[$sk] as $titulo => $campos) {
        $html = '';
        foreach ($campos as $clave => $c) { $html .= $campo($clave, $c); }
        if ($html === '') { continue; }
        echo '<fieldset class="hv-seccion"><legend>' . $e($titulo) . '</legend><div class="hv-rejilla">' . $html . '</div></fieldset>';
    }
    if ($editable) { echo '<p class="hv-acciones"><button type="submit">Guardar</button> <span class="texto-suave">Los campos con * son obligatorios.</span></p></form>'; }
};
?>
<?php if ($rol === 'docente'): ?>
    <?php /* El docente ve solo lo necesario para atender al estudiante en clase. */ ?>
    <h2 class="hv-titulo">Datos para atender al estudiante</h2>
    <dl class="acad-detalle">
        <div><dt>EPS</dt><dd><?= $e($vals['eps'] ?? '') ?: '—' ?></dd></div>
        <div><dt>Grupo sanguíneo</dt><dd><?= $e($vals['grupo_sanguineo'] ?? '') ?: '—' ?></dd></div>
        <div><dt>Alergias</dt><dd><?= $e($vals['alergias'] ?? '') ?: '—' ?></dd></div>
    </dl>
    <h3>Contactos de emergencia</h3>
    <?php if (!$contactos): ?><p class="texto-suave">Sin contactos registrados.</p><?php endif; ?>
    <?php foreach ($contactos as $c): ?>
        <p><strong><?= $e($c['nombre']) ?></strong> (<?= $e($c['parentesco']) ?>) · <a href="tel:<?= $e($c['telefono']) ?>"><?= $e($c['telefono']) ?></a><?= (int) $c['autorizado_recoger'] === 1 ? ' · autorizado para recogerlo' : '' ?></p>
    <?php endforeach; ?>
<?php else: ?>

<nav class="hv-subtabs no-print" aria-label="Datos de la matrícula">
    <?php foreach (HojaVida::SUBTABS as $k => $t): ?>
        <a href="<?= $e($url('matricula', ['sub' => $k])) ?>" <?= $k === $sub ? 'class="activa" aria-current="page"' : '' ?>><?= $e($t) ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($perm['acudiente']): ?>
    <?php if ($perm['ventana']['desde'] !== '' && $perm['ventana']['hasta'] !== ''): ?>
        <p class="mensaje <?= $perm['ventana']['abierta'] ? 'mensaje-info' : 'mensaje-aviso' ?>">La fecha de modificación de datos es desde <?= $e(date('d/m/Y', strtotime($perm['ventana']['desde']))) ?> hasta <?= $e(date('d/m/Y', strtotime($perm['ventana']['hasta']))) ?><?= $perm['ventana']['abierta'] ? '.' : '; por lo tanto la información está en solo lectura.' ?></p>
    <?php else: ?>
        <p class="mensaje mensaje-aviso">La modificación de datos por parte de la familia no está habilitada: la información está en solo lectura. Si hay un error, avísalo en secretaría.</p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($sub === 'datos' && $perm['ventana_admin']): ?>
<details class="hv-ventana no-print">
    <summary>Ventana de modificación de datos por la familia</summary>
    <form action="estudiante_ficha.php" method="POST" class="acad-filtros" novalidate>
        <?php $ocultos('hv_ventana'); ?>
        <label>Desde <input type="date" name="desde" value="<?= $e(HojaVida::ventana($pdo, $colegioId)['desde']) ?>"></label>
        <label>Hasta <input type="date" name="hasta" value="<?= $e(HojaVida::ventana($pdo, $colegioId)['hasta']) ?>"></label>
        <button type="submit" class="boton-secundario">Guardar ventana</button>
        <span class="texto-suave">Fuera de estas fechas (o sin fechas) los acudientes ven los datos en solo lectura. Vale para todo el colegio. Déjalas vacías para cerrarla.</span>
    </form>
</details>
<?php endif; ?>

<?php if ($sub === 'datos'): ?>
    <?php $bloqueSub('datos'); ?>

<?php elseif ($sub === 'familia'): ?>
    <?php $bloqueSub('familia'); ?>
    <?php $fams = HojaVida::familiares($pdo, $estId); ?>
    <h2 class="hv-titulo">Información adicional del núcleo familiar</h2>
    <div class="acad-tabla-envoltura"><table class="acad-tabla">
        <caption class="sr-only">Núcleo familiar</caption>
        <thead><tr><th scope="col">Parentesco</th><th scope="col">Documento</th><th scope="col">Nombre</th><th scope="col">Celular</th><th scope="col">Empresa</th></tr></thead>
        <tbody>
        <?php foreach (HojaVida::FAMILIARES as $k => $nombre): $f = $fams[$k] ?? []; ?>
            <tr><td><?= $e($nombre) ?></td>
                <td><?= $e(trim(($f['tipo_documento'] ?? '') . ' ' . ($f['numero_documento'] ?? ''))) ?: '—' ?></td>
                <td><?= $e(trim(($f['nombre1'] ?? '') . ' ' . ($f['nombre2'] ?? '') . ' ' . ($f['apellido1'] ?? '') . ' ' . ($f['apellido2'] ?? ''))) ?: '—' ?></td>
                <td><?= $e($f['celular'] ?? '') ?: '—' ?></td><td><?= $e($f['empresa'] ?? '') ?: '—' ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php foreach (HojaVida::FAMILIARES as $k => $nombre): $f = $fams[$k] ?? []; ?>
    <details class="hv-familiar">
        <summary><strong><?= $e($nombre) ?></strong></summary>
        <?php if ($perm['edita_datos']): ?>
        <form action="estudiante_ficha.php" method="POST" novalidate>
            <?php $ocultos('hv_familiar', ['parentesco' => $k]); ?>
            <div class="hv-rejilla">
            <?php foreach (HojaVida::FAMILIAR_CAMPOS as $col => [$etq, $tipo, $max, $fmt]): $v = (string) ($f[$col] ?? ''); ?>
                <label class="hv-campo"><span class="hv-etq"><?= $e($etq) ?></span>
                <?php if ($tipo === 's'): ?><select name="<?= $e($col) ?>"><option value="">—</option><option value="S" <?= $v === 'S' ? 'selected' : '' ?>>Sí</option><option value="N" <?= $v === 'N' ? 'selected' : '' ?>>No</option></select>
                <?php elseif ($tipo === 'td'): ?><select name="<?= $e($col) ?>"><option value="">—</option><?php foreach (Academico::TIPOS_DOCUMENTO as $tk => $tt): ?><option value="<?= $e($tk) ?>" <?= $v === $tk ? 'selected' : '' ?>><?= $e($tt) ?></option><?php endforeach; ?></select>
                <?php elseif ($tipo === 'd'): ?><input type="date" name="<?= $e($col) ?>" value="<?= $e($v) ?>">
                <?php else: ?><input type="<?= $fmt === 'email' ? 'email' : ($fmt === 'tel' ? 'tel' : 'text') ?>" name="<?= $e($col) ?>" maxlength="<?= (int) $max ?>" value="<?= $e($v) ?>"><?php endif; ?>
                </label>
            <?php endforeach; ?>
            </div>
            <p class="hv-acciones"><button type="submit">Guardar <?= $e(mb_strtolower($nombre)) ?></button></p>
        </form>
        <?php else: ?>
        <dl class="acad-detalle">
            <?php foreach (HojaVida::FAMILIAR_CAMPOS as $col => [$etq]): $v = (string) ($f[$col] ?? ''); ?>
                <div><dt><?= $e($etq) ?></dt><dd><?= $v !== '' ? $e($col === 'vive' ? ($v === 'S' ? 'Sí' : 'No') : $v) : '—' ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php endif; ?>
    </details>
    <?php endforeach; ?>

<?php elseif ($sub === 'caracteristicas'): ?>
    <?php $bloqueSub('caracteristicas'); ?>
    <h2 class="hv-titulo">Cambio de estado</h2>
    <?php $mats = Academico::matriculasDeEstudiante($pdo, $estId); ?>
    <?php if (!$mats): ?><p class="texto-suave">Sin matrículas registradas.</p><?php else: ?>
    <div class="acad-tabla-envoltura"><table class="acad-tabla">
        <caption class="sr-only">Matrículas del estudiante</caption>
        <thead><tr><th scope="col">Año</th><th scope="col">Grado</th><th scope="col">Grupo</th><th scope="col">Tipo</th><th scope="col">Estado</th></tr></thead>
        <tbody><?php foreach ($mats as $m): ?>
            <tr><td><?= (int) $m['anio'] ?></td><td><?= $e($m['grado']) ?></td><td><?= $e($m['grupo'] ?? '—') ?></td><td><?= $e($m['tipo']) ?></td><td><span class="estado-pill estado-<?= $e($m['estado']) ?>"><?= $e($m['estado']) ?></span></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <p class="texto-suave">Los traslados, retiros y la promoción de año se gestionan desde Matrículas y Promoción de año.</p>

    <h2 class="hv-titulo">Relación de establecimientos donde ha estudiado</h2>
    <?php $previos = HojaVida::previos($pdo, $estId); ?>
    <?php if (!$previos): ?><p class="texto-suave">Sin registros.</p><?php else: ?>
    <div class="acad-tabla-envoltura"><table class="acad-tabla">
        <caption class="sr-only">Establecimientos anteriores</caption>
        <thead><tr><th scope="col">Grado</th><th scope="col">Año</th><th scope="col">Municipio</th><th scope="col">DANE</th><th scope="col">Establecimiento</th><th scope="col">Director de grupo</th><th scope="col">Causa del cambio</th><th scope="col">Causa de reprobación</th><th></th></tr></thead>
        <tbody><?php foreach ($previos as $p): ?>
            <tr><td><?= $e($p['grado']) ?></td><td><?= $e($p['anio']) ?></td><td><?= $e($p['municipio']) ?></td><td><?= $e($p['dane']) ?></td><td><?= $e($p['nombre']) ?></td><td><?= $e($p['director_grupo']) ?></td><td><?= $e($p['causa_cambio']) ?></td><td><?= $e($p['causa_reprobacion']) ?></td>
                <td><?php if ($perm['edita_datos']): ?><form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar este registro?');"><?php $ocultos('hv_previo_eliminar', ['previo_id' => (int) $p['id']]); ?><button type="submit" class="boton-peligro">Quitar</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
    <?php if ($perm['edita_datos']): ?>
    <details class="no-print"><summary>Agregar un establecimiento</summary>
        <form action="estudiante_ficha.php" method="POST" novalidate>
            <?php $ocultos('hv_previo_agregar'); ?>
            <div class="hv-rejilla">
                <label class="hv-campo"><span class="hv-etq">Establecimiento *</span><input type="text" name="p_nombre" maxlength="150" required></label>
                <label class="hv-campo"><span class="hv-etq">Grado</span><input type="text" name="p_grado" maxlength="30"></label>
                <label class="hv-campo"><span class="hv-etq">Año</span><input type="number" name="p_anio" min="1980" max="<?= (int) date('Y') ?>"></label>
                <label class="hv-campo"><span class="hv-etq">Municipio</span><input type="text" name="p_municipio" maxlength="100"></label>
                <label class="hv-campo"><span class="hv-etq">DANE</span><input type="text" name="p_dane" maxlength="20" inputmode="numeric"></label>
                <label class="hv-campo"><span class="hv-etq">Director de grupo</span><input type="text" name="p_director" maxlength="120"></label>
                <label class="hv-campo"><span class="hv-etq">Causa del cambio</span><input type="text" name="p_causa_cambio" maxlength="150"></label>
                <label class="hv-campo"><span class="hv-etq">Causa de reprobación</span><input type="text" name="p_causa_reprobacion" maxlength="150"></label>
            </div>
            <p class="hv-acciones"><button type="submit">Agregar</button></p>
        </form>
    </details>
    <?php endif; ?>

<?php else: /* salud */ ?>
    <?php $bloqueSub('salud'); ?>
    <h2 class="hv-titulo">Contactos de emergencia</h2>
    <?php if (!$contactos): ?><p class="texto-suave">Sin contactos registrados.</p><?php endif; ?>
    <?php foreach ($contactos as $c): ?>
        <div class="acad-acciones">
            <span><strong><?= $e($c['nombre']) ?></strong> (<?= $e($c['parentesco']) ?>) · <a href="tel:<?= $e($c['telefono']) ?>"><?= $e($c['telefono']) ?></a><?= (int) $c['autorizado_recoger'] === 1 ? ' · autorizado para recogerlo' : '' ?></span>
            <?php if ($perm['edita_datos']): ?>
            <form action="estudiante_ficha.php" method="POST" onsubmit="return confirm('¿Eliminar este contacto?');">
                <?php $ocultos('contacto_eliminar', ['contacto_id' => (int) $c['id']]); ?>
                <button type="submit" class="boton-peligro">Quitar</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($perm['edita_datos']): ?>
    <details class="no-print"><summary>Agregar un contacto</summary>
        <form action="estudiante_ficha.php" method="POST" class="acad-filtros" novalidate>
            <?php $ocultos('contacto_agregar'); ?>
            <label>Nombre <input type="text" name="c_nombre" maxlength="120" required></label>
            <label>Parentesco <input type="text" name="c_parentesco" maxlength="40" required></label>
            <label>Teléfono <input type="tel" name="c_telefono" maxlength="20" required></label>
            <label><input type="checkbox" name="c_recoger"> Autorizado para recogerlo</label>
            <button type="submit">Agregar</button>
        </form>
    </details>
    <?php endif; ?>
<?php endif; ?>
<?php endif; ?>
