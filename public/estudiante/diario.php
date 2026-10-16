<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/helpers/subidaImagenes.php';
require_once __DIR__ . '/../../src/modelos/Diario.php';
requiereRol('estudiante');

// PIN check - redirige siempre que no esté desbloqueado en esta sesión, exista o no un
// PIN ya guardado: diario_pin.php decide si pide verificar el PIN existente o pide crear
// uno nuevo. Antes esto solo redirigía si $hash existía, así que un estudiante que nunca
// había puesto PIN entraba directo al diario sin ninguna protección.
if (!isset($_SESSION['diario_desbloqueado'])) {
    header('Location: ' . BASE_URL . '/estudiante/diario_pin.php');
    exit;
}

const DIARIO_MAX_IMAGENES = 4;
const DIARIO_CARPETA_IMAGENES = __DIR__ . '/../../src/uploads/diario';

$uid = (int)$_SESSION['usuario_id'];
$error = ''; $exito = '';
$accion = $_GET['accion'] ?? '';
$entradaId = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');

    if (($_POST['eliminar_id'] ?? '')) {
        $idEliminar = (int)$_POST['eliminar_id'];
        $ent = Diario::buscarPorId($pdo, $idEliminar, $uid);
        if ($ent) {
            // Las filas de diario_imagenes se borran solas (ON DELETE CASCADE), pero los
            // archivos en disco no — hay que borrarlos a mano antes de borrar la entrada.
            foreach (Diario::imagenesDeEntrada($pdo, $idEliminar) as $img) {
                @unlink(DIARIO_CARPETA_IMAGENES . '/' . $img['ruta_imagen']);
            }
            Diario::eliminar($pdo, $idEliminar, $uid);
        }
        header('Location: diario.php'); exit;
    }

    if (($_POST['eliminar_imagen_id'] ?? '')) {
        $imgId = (int)$_POST['eliminar_imagen_id'];
        $img = Diario::buscarImagenDeUsuario($pdo, $imgId, $uid);
        if ($img) {
            @unlink(DIARIO_CARPETA_IMAGENES . '/' . $img['ruta_imagen']);
            Diario::eliminarImagen($pdo, $imgId, $uid);
        }
        header('Location: diario.php?id=' . (int)($_POST['volver_a_id'] ?? 0)); exit;
    }

    $datos = ['titulo' => trim($_POST['titulo'] ?? ''), 'contenido' => trim($_POST['contenido'] ?? ''),
              'etiquetas' => array_filter(array_map('trim', explode(',', $_POST['etiquetas'] ?? '')))];

    if (empty($datos['contenido'])) {
        $error = 'El contenido no puede estar vacío.';
    } else {
        $idEntrada = null;
        if (isset($_POST['id']) && $_POST['id']) {
            $idPropuesto = (int)$_POST['id'];
            $ent = Diario::buscarPorId($pdo, $idPropuesto, $uid);
            if ($ent) {
                Diario::actualizar($pdo, $idPropuesto, $datos);
                $exito = 'Entrada actualizada.';
                $idEntrada = $idPropuesto; // solo se confirma tras verificar que la entrada es del usuario
            } else {
                $error = 'Esa entrada no existe o no te pertenece.';
            }
        } else {
            $idEntrada = Diario::crear($pdo, $uid, $datos);
            $exito = 'Entrada guardada.';
        }

        // Imágenes adjuntas (opcional). Se procesan solo si la entrada se guardó bien arriba.
        if (!empty($idEntrada) && !empty($_FILES['imagenes']['name'][0] ?? '')) {
            $yaTiene = Diario::contarImagenes($pdo, $idEntrada);
            $cantidadArchivos = count($_FILES['imagenes']['name']);
            for ($i = 0; $i < $cantidadArchivos; $i++) {
                if ($yaTiene >= DIARIO_MAX_IMAGENES) {
                    $error = 'Máximo ' . DIARIO_MAX_IMAGENES . ' imágenes por entrada. El resto no se subió.';
                    break;
                }
                $archivo = [
                    'name' => $_FILES['imagenes']['name'][$i], 'type' => $_FILES['imagenes']['type'][$i],
                    'tmp_name' => $_FILES['imagenes']['tmp_name'][$i], 'error' => $_FILES['imagenes']['error'][$i],
                    'size' => $_FILES['imagenes']['size'][$i],
                ];
                try {
                    $nombreGuardado = guardarImagenSubida($archivo, DIARIO_CARPETA_IMAGENES);
                    Diario::agregarImagen($pdo, $idEntrada, $nombreGuardado);
                    $yaTiene++;
                } catch (RuntimeException $e) {
                    if ($e->getMessage() !== '__vacio__') $error = $e->getMessage();
                }
            }
        }
    }
}

$busqueda = trim($_GET['busqueda'] ?? '');
$etqFiltro = trim($_GET['etiqueta'] ?? '');
$entradas = Diario::listarDeUsuario($pdo, $uid, $busqueda, $etqFiltro);
foreach ($entradas as &$ent) { $ent['imagenes'] = Diario::imagenesDeEntrada($pdo, $ent['id']); }
unset($ent);
$etiquetas = Diario::etiquetasDeUsuario($pdo, $uid);
$editando = $entradaId ? Diario::buscarPorId($pdo, $entradaId, $uid) : null;
$imagenesEditando = $editando ? Diario::imagenesDeEntrada($pdo, $editando['id']) : [];

$tituloPagina = 'Diario';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Mi diario</h1>
<div class="u-mb-md"><a href="diario_exportar.php" class="boton-secundario" data-sonido="clic">📄 Exportar mi diario a PDF</a></div>
<a href="diario_exportar.php?qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exito): ?><p class="mensaje mensaje-exito"><?= htmlspecialchars($exito) ?></p><?php endif; ?>
<div class="columnas">
<section class="panel-seccion">
  <h2><?= $editando ? 'Editar entrada' : 'Nueva entrada' ?></h2>
  <form action="diario.php" method="POST" enctype="multipart/form-data" novalidate>
    <?php campoCSRF(); ?>
    <?php if ($editando): ?><input type="hidden" name="id" value="<?= $editando['id'] ?>"><?php endif; ?>
    <label for="titulo">Título (opcional)</label>
    <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($editando['titulo'] ?? '') ?>">
    <label for="contenido">¿Cómo estuvo tu día?</label>
    <textarea id="contenido" name="contenido" rows="8" required><?= htmlspecialchars($editando['contenido'] ?? '') ?></textarea>
    <label for="etiquetas">Etiquetas (separadas por coma)</label>
    <input type="text" id="etiquetas" name="etiquetas" placeholder="familia, estudio, amigos" value="<?= $editando ? htmlspecialchars(implode(', ', Diario::etiquetasDeEntrada($pdo, $editando['id']))) : '' ?>">

    <?php if ($editando && !empty($imagenesEditando)): ?>
    <label>Imágenes ya adjuntas</label>
    <div class="galeria-diario">
      <?php foreach ($imagenesEditando as $img): ?>
        <div class="miniatura-diario">
          <img src="diario_imagen.php?id=<?= $img['id'] ?>" alt="">
          <form action="diario.php" method="POST">
            <?php campoCSRF(); ?>
            <input type="hidden" name="eliminar_imagen_id" value="<?= $img['id'] ?>">
            <input type="hidden" name="volver_a_id" value="<?= $editando['id'] ?>">
            <button type="submit" class="boton-quitar-imagen" title="Quitar imagen" onclick="return confirm('¿Quitar esta imagen?')">✕</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!$editando || count($imagenesEditando) < 4): ?>
    <label for="imagenes">Agregar imágenes (opcional, máx. <?= DIARIO_MAX_IMAGENES ?>, 5 MB c/u)</label>
    <input type="file" id="imagenes" name="imagenes[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
    <?php endif; ?>

    <button type="submit"><?= $editando ? 'Actualizar' : 'Guardar entrada' ?></button>
    <?php if ($editando): ?><a href="diario.php" class="boton-acceso" style="margin-left:8px">Cancelar</a><?php endif; ?>
  </form>
</section>
<section class="panel-seccion">
  <h2>Mis entradas</h2>
  <form action="diario.php" method="GET" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <input type="text" name="busqueda" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>" style="flex:1;min-width:120px">
    <select name="etiqueta"><option value="">Todas las etiquetas</option>
      <?php foreach ($etiquetas as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>" <?= $etqFiltro === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Filtrar</button>
  </form>
  <?php if (empty($entradas)): ?>
    <div class="estado-vacio">
      <img src="<?= BASE_URL ?>/assets/img/recursos/estado_vacio_diario.png" alt="">
      <p>No hay entradas aún. Escribe la primera cuando quieras.</p>
    </div>
  <?php else: ?>
  <?php foreach ($entradas as $ent): ?>
    <div class="entrada-diario">
      <small><?= date('d/m/Y H:i', strtotime($ent['fecha'])) ?></small>
      <?php if ($ent['titulo']): ?><strong style="display:block"><?= htmlspecialchars($ent['titulo']) ?></strong><?php endif; ?>
      <p><?= nl2br(htmlspecialchars(mb_substr($ent['contenido'], 0, 200))) ?><?= mb_strlen($ent['contenido']) > 200 ? '…' : '' ?></p>
      <?php if (!empty($ent['imagenes'])): ?>
      <div class="galeria-diario galeria-diario-mini">
        <?php foreach ($ent['imagenes'] as $img): ?>
          <a href="diario_imagen.php?id=<?= $img['id'] ?>" target="_blank" rel="noopener">
            <img src="diario_imagen.php?id=<?= $img['id'] ?>" alt="" loading="lazy">
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <a href="diario.php?id=<?= $ent['id'] ?>">Editar</a> &nbsp;
      <form action="diario.php" method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta entrada y sus imágenes?')">
        <?php campoCSRF(); ?>
        <input type="hidden" name="eliminar_id" value="<?= $ent['id'] ?>">
        <button type="submit" style="background:none;border:none;color:#b33;cursor:pointer;padding:0;font:inherit">Eliminar</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
