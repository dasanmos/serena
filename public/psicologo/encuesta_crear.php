<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/Encuesta.php';
require_once __DIR__ . '/../../src/modelos/Grupo.php';
requiereRol('psicologo');
$uid = (int)$_SESSION['usuario_id'];
$error = '';
$grupos = Grupo::listarConGrado($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $titulo = trim($_POST['titulo'] ?? '');
    if (empty($titulo)) { $error = 'El título es obligatorio.'; }
    else {
        $preguntas = [];
        foreach ($_POST['pregunta_texto'] ?? [] as $i => $texto) {
            $texto = trim($texto);
            if (empty($texto)) continue;
            $tipo = $_POST['pregunta_tipo'][$i] ?? 'opcion_multiple';
            $opciones = array_filter(array_map('trim', explode("\n", $_POST['opciones'][$i] ?? '')));
            $preguntas[] = ['texto' => $texto, 'tipo' => $tipo, 'opciones' => array_values($opciones)];
        }
        $datos = ['titulo' => $titulo, 'descripcion' => trim($_POST['descripcion'] ?? ''),
                  'grupo_id' => $_POST['grupo_id'] ?: null, 'fecha_cierre' => $_POST['fecha_cierre'] ?: null,
                  'preguntas' => $preguntas];
        Encuesta::crear($pdo, $uid, $datos);
        header('Location: encuestas.php'); exit;
    }
}
$tituloPagina = 'Crear encuesta';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Crear encuesta</h1>
<?php if ($error): ?><p class="mensaje mensaje-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form action="encuesta_crear.php" method="POST" id="form-encuesta">
    <?php campoCSRF(); ?>
  <section class="panel-seccion" style="margin-bottom:16px">
    <label>Título *</label><input type="text" name="titulo" required>
    <label>Descripción</label><textarea name="descripcion" rows="2"></textarea>
    <label>Dirigida al grupo (dejar en blanco = todos)</label>
    <select name="grupo_id"><option value="">Todos los estudiantes</option>
      <?php foreach ($grupos as $g): ?><option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['grado'].' '.$g['nombre']) ?></option><?php endforeach; ?>
    </select>
    <label>Fecha de cierre (opcional)</label><input type="datetime-local" name="fecha_cierre">
  </section>
  <div id="preguntas"></div>
  <button type="button" onclick="agregarPregunta()" class="boton-secundario" style="margin-bottom:16px">+ Agregar pregunta</button>
  <br><button type="submit">Publicar encuesta</button>
</form>
<script>
let idx = 0;
function agregarPregunta() {
  const div = document.createElement('div');
  div.className = 'constructor-bloque-encuesta';
  div.innerHTML = `<label>Pregunta ${++idx}</label>
    <input type="text" name="pregunta_texto[]" required placeholder="Escribe la pregunta">
    <label>Tipo</label>
    <select name="pregunta_tipo[]" onchange="toggleOpciones(this)">
      <option value="opcion_multiple">Opción múltiple</option>
      <option value="texto_libre">Texto libre</option>
      <option value="escala">Escala 1–10</option>
    </select>
    <div class="opciones-area"><label>Opciones (una por línea)</label>
    <textarea name="opciones[]" rows="4" placeholder="Opción 1&#10;Opción 2&#10;Opción 3"></textarea></div>`;
  document.getElementById('preguntas').appendChild(div);
}
function toggleOpciones(sel) {
  const area = sel.closest('.constructor-bloque-encuesta').querySelector('.opciones-area');
  area.style.display = sel.value === 'opcion_multiple' ? 'block' : 'none';
}
agregarPregunta();
</script>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
