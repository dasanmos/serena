<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/helpers/csrf.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Sueno.php';
require_once __DIR__ . '/../../src/modelos/Usuario.php';
require_once __DIR__ . '/../../src/modelos/PlanSeguimiento.php';
require_once __DIR__ . '/../../src/modelos/AccesoFicha.php';
requiereRol('psicologo','orientador');
$id = (int)($_GET['id'] ?? 0);
exigirUsuarioDeMiColegio($pdo, $id); // multi-colegio: 404 si es de otro colegio
$estudiante = Usuario::buscarPorId($pdo, $id);
if (!$estudiante || $estudiante['rol'] !== 'estudiante') { http_response_code(404); exit('Estudiante no encontrado.'); }

// El psicólogo tiene alcance institucional completo (por diseño). El orientador solo
// debe ver estudiantes de sus propios grupos/grados — sin este chequeo, cambiar el "id"
// en la URL permitía a cualquier orientador ver el detalle emocional de cualquier
// estudiante de cualquier grupo, no solo los suyos.
if ($_SESSION['rol'] === 'orientador') {
    $gruposOrientador = $_SESSION['grupos_ids'] ?? [];
    if (empty($gruposOrientador) || !in_array((int) $estudiante['grupo_id'], $gruposOrientador, true)) {
        http_response_code(404); exit('Estudiante no encontrado.');
    }
}

$autorId = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nota_contenido'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $contenido = trim($_POST['nota_contenido']);
    if ($contenido !== '') {
        $ins = $pdo->prepare('INSERT INTO notas_sesion (autor_id, estudiante_id, contenido) VALUES (:autor, :est, :contenido)');
        $ins->execute(['autor' => $autorId, 'est' => $id, 'contenido' => $contenido]);
    }
    header('Location: estudiante_detalle.php?id=' . $id . '#notas');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_plan'])) {
    verificarTokenCSRF($_POST['csrf_token'] ?? '');
    $accionPlan = $_POST['accion_plan'];
    if ($accionPlan === 'crear') {
        $tituloTarea = trim((string) ($_POST['titulo'] ?? ''));
        if ($tituloTarea !== '') {
            $fechaLimite = trim((string) ($_POST['fecha_limite'] ?? ''));
            PlanSeguimiento::crear(
                $pdo, $autorId, $id,
                mb_substr($tituloTarea, 0, 150),
                trim((string) ($_POST['descripcion'] ?? '')) ?: null,
                $fechaLimite !== '' ? $fechaLimite : null
            );
        }
    } elseif ($accionPlan === 'estado' && in_array($_POST['estado'] ?? '', ['pendiente', 'en_curso', 'completado'], true)) {
        PlanSeguimiento::actualizarEstado($pdo, (int) ($_POST['tarea_id'] ?? 0), $autorId, $_POST['estado']);
    } elseif ($accionPlan === 'eliminar') {
        PlanSeguimiento::eliminar($pdo, (int) ($_POST['tarea_id'] ?? 0), $autorId);
    }
    header('Location: estudiante_detalle.php?id=' . $id . '#seguimiento');
    exit;
}

// Solo las notas de QUIEN LAS ESCRIBIÓ — son privadas incluso entre psicólogo y
// orientador, cada quien ve únicamente las suyas sobre este estudiante.
$notasSesion = $pdo->prepare('SELECT * FROM notas_sesion WHERE estudiante_id = :est AND autor_id = :autor ORDER BY fecha_creacion DESC');
$notasSesion->execute(['est' => $id, 'autor' => $autorId]);
$notasSesion = $notasSesion->fetchAll();

// Se registra aquí (ya pasaron los `exit` de las ramas POST): cualquier carga
// real de la página cuenta como un acceso a la ficha, deduplicado por
// AccesoFicha::registrar() para no acumular una fila por cada redirección.
AccesoFicha::registrar($pdo, $autorId, $id);

$emociones = EstadoEmocional::listarDeUsuario($pdo, $id, date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
$sueno = Sueno::listarDeUsuario($pdo, $id, 14);
$plan = PlanSeguimiento::listarDeEstudiante($pdo, $autorId, $id);
$accesos = AccesoFicha::listarDeEstudiante($pdo, $id, 10);
$tituloPagina = 'Detalle estudiante';
require __DIR__ . '/../parciales/encabezado.php';
?>
<div class="cabecera-grupo">
  <div class="info-grupo">
    <h1><?= htmlspecialchars($estudiante['nombre']) ?></h1>
    <span class="texto-suave"><?= htmlspecialchars($estudiante['correo']) ?></span>
  </div>
  <a href="caso_pdf.php?id=<?= $id ?>" class="boton-acceso" data-sonido="clic">📄 Exportar caso a PDF</a>
  <a href="caso_pdf.php?id=<?= $id ?>&qr=1" class="boton-secundario" data-sonido="clic" title="PDF con código QR para verificar su autenticidad">PDF con QR</a>
</div>
<div class="columnas">
<section class="panel-seccion">
  <h2>Emociones (últimos 30 días)</h2>
  <?php if (empty($emociones)): ?>
    <div class="estado-vacio"><p>Sin registros.</p></div>
  <?php else: ?>
  <ul class="lista-simple">
    <?php foreach (array_slice($emociones,0,10) as $e): ?>
      <li><?= htmlspecialchars(ucfirst($e['emocion'])) ?> — nivel <?= $e['nivel'] ?> — <?= date('d/m H:i', strtotime($e['fecha_hora'])) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<section class="panel-seccion">
  <h2>Sueño (últimas 2 semanas)</h2>
  <?php if (empty($sueno)): ?>
    <div class="estado-vacio"><p>Sin registros.</p></div>
  <?php else: ?>
  <div class="tabla-wrapper">
  <table class="tabla-datos tabla-adaptable">
    <thead><tr><th>Fecha</th><th>Horas</th><th>Calidad</th></tr></thead>
    <tbody>
    <?php foreach ($sueno as $s): ?>
      <tr>
        <td data-etiqueta="Fecha"><?= date('d/m', strtotime($s['fecha'])) ?></td>
        <td data-etiqueta="Horas"><?= $s['horas'] ?>h</td>
        <td data-etiqueta="Calidad"><?= ucfirst($s['calidad']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
</div>

<section class="panel-seccion" id="seguimiento">
  <h2>Plan de seguimiento</h2>
  <p class="texto-suave">Tareas de control que te propones para este estudiante — privadas, solo tú las ves.</p>
  <form action="estudiante_detalle.php?id=<?= $id ?>#seguimiento" method="POST" class="form-nueva-tarea-seguimiento u-mb-md">
    <?php campoCSRF(); ?>
    <input type="hidden" name="accion_plan" value="crear">
    <label for="tarea-titulo">Qué hay que hacer</label>
    <input type="text" id="tarea-titulo" name="titulo" maxlength="150" placeholder="Ej.: Citar a acudiente, revisar en dos semanas..." required>
    <div class="fila-tarea-seguimiento">
      <div>
        <label for="tarea-fecha">Fecha límite (opcional)</label>
        <input type="date" id="tarea-fecha" name="fecha_limite">
      </div>
    </div>
    <label for="tarea-descripcion">Notas (opcional)</label>
    <textarea id="tarea-descripcion" name="descripcion" rows="2" maxlength="500"></textarea>
    <button type="submit" class="boton-acceso u-mt-sm" data-sonido="clic">Agregar tarea</button>
  </form>

  <?php if (empty($plan)): ?>
    <div class="estado-vacio"><p>Todavía no tienes tareas de seguimiento para este estudiante.</p></div>
  <?php else: ?>
    <ul class="lista-plan-seguimiento">
      <?php foreach ($plan as $tarea):
        $vencida = $tarea['estado'] !== 'completado' && $tarea['fecha_limite'] !== null && $tarea['fecha_limite'] < date('Y-m-d');
      ?>
      <li class="tarea-seguimiento tarea-<?= $tarea['estado'] ?><?= $vencida ? ' tarea-vencida' : '' ?>">
        <div class="info-tarea-seguimiento">
          <strong><?= htmlspecialchars($tarea['titulo']) ?></strong>
          <span class="pill pill-estado-<?= $tarea['estado'] ?>">
            <?= ['pendiente' => 'Pendiente', 'en_curso' => 'En curso', 'completado' => 'Completada'][$tarea['estado']] ?>
          </span>
          <?php if ($vencida): ?><span class="pill pill-severidad-alta">Vencida</span><?php endif; ?>
          <?php if ($tarea['fecha_limite']): ?>
            <span class="texto-suave">Fecha límite: <?= date('d/m/Y', strtotime($tarea['fecha_limite'])) ?></span>
          <?php endif; ?>
          <?php if ($tarea['descripcion']): ?><p><?= nl2br(htmlspecialchars($tarea['descripcion'])) ?></p><?php endif; ?>
        </div>
        <div class="acciones-tarea-seguimiento">
          <?php if ($tarea['estado'] !== 'en_curso' && $tarea['estado'] !== 'completado'): ?>
          <form action="estudiante_detalle.php?id=<?= $id ?>#seguimiento" method="POST">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion_plan" value="estado">
            <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
            <input type="hidden" name="estado" value="en_curso">
            <button type="submit" class="boton-secundario">En curso</button>
          </form>
          <?php endif; ?>
          <?php if ($tarea['estado'] !== 'completado'): ?>
          <form action="estudiante_detalle.php?id=<?= $id ?>#seguimiento" method="POST">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion_plan" value="estado">
            <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
            <input type="hidden" name="estado" value="completado">
            <button type="submit" class="boton-acceso">Completar</button>
          </form>
          <?php else: ?>
          <form action="estudiante_detalle.php?id=<?= $id ?>#seguimiento" method="POST">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion_plan" value="estado">
            <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
            <input type="hidden" name="estado" value="pendiente">
            <button type="submit" class="boton-secundario">Reabrir</button>
          </form>
          <?php endif; ?>
          <form action="estudiante_detalle.php?id=<?= $id ?>#seguimiento" method="POST" onsubmit="return confirm('¿Eliminar esta tarea de seguimiento?')">
            <?php campoCSRF(); ?>
            <input type="hidden" name="accion_plan" value="eliminar">
            <input type="hidden" name="tarea_id" value="<?= $tarea['id'] ?>">
            <button type="submit" class="boton-peligro">Eliminar</button>
          </form>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel-seccion" id="notas">
  <h2>Mis notas de sesión</h2>
  <p class="texto-suave">Privadas — solo tú puedes verlas. No se comparten con el estudiante ni con otros roles.</p>
  <form action="estudiante_detalle.php?id=<?= $id ?>" method="POST" class="u-mb-md">
    <?php campoCSRF(); ?>
    <textarea name="nota_contenido" rows="3" placeholder="Escribe una nota sobre esta sesión..." required></textarea>
    <button type="submit" class="boton-acceso u-mt-sm" data-sonido="clic">Guardar nota</button>
  </form>
  <?php if (empty($notasSesion)): ?>
    <div class="estado-vacio"><p>Todavía no has escrito notas sobre este estudiante.</p></div>
  <?php else: ?>
    <ul class="lista-simple">
      <?php foreach ($notasSesion as $nota): ?>
        <li>
          <span class="texto-suave"><?= date('d/m/Y H:i', strtotime($nota['fecha_creacion'])) ?></span><br>
          <?= nl2br(htmlspecialchars($nota['contenido'])) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="panel-seccion" id="accesos">
  <h2>Quién ha visto esta ficha</h2>
  <p class="texto-suave">
    Registro de trazabilidad: a diferencia de tus notas y tu plan de seguimiento (privados),
    esto lo ven todos los profesionales con acceso a esta ficha — es el punto de la lista.
  </p>
  <?php if (empty($accesos)): ?>
    <p class="texto-suave">Este es el primer acceso registrado.</p>
  <?php else: ?>
    <ul class="lista-accesos-ficha">
      <?php foreach ($accesos as $a): ?>
        <li>
          <span><?= htmlspecialchars($a['nombre']) ?> <span class="texto-suave">(<?= ucfirst($a['rol']) ?>)</span></span>
          <span class="texto-suave"><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../parciales/pie.php'; ?>
