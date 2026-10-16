<?php
declare(strict_types=1);
require __DIR__ . '/../../src/config/conexion.php';
require_once __DIR__ . '/../../src/helpers/auth.php';
require_once __DIR__ . '/../../src/modelos/EstadoEmocional.php';
require_once __DIR__ . '/../../src/modelos/Habito.php';
require_once __DIR__ . '/../../src/modelos/Meta.php';
requiereRol('estudiante');
$uid = (int)$_SESSION['usuario_id'];
$mes = (int)($_GET['mes'] ?? date('n'));
$anio = (int)($_GET['anio'] ?? date('Y'));
$primerDia = mktime(0,0,0,$mes,1,$anio);
$diasEnMes = (int)date('t', $primerDia);
$iniciaSemana = (int)date('N', $primerDia); // 1=lun

$emociones = $pdo->prepare("SELECT DATE(fecha_hora) AS dia, AVG(nivel) AS prom FROM estado_emocional WHERE usuario_id=:uid AND MONTH(fecha_hora)=:m AND YEAR(fecha_hora)=:y GROUP BY dia");
$emociones->execute(['uid'=>$uid,'m'=>$mes,'y'=>$anio]);
$emoMap = [];
foreach ($emociones->fetchAll() as $e) $emoMap[$e['dia']] = round((float)$e['prom'],1);

$habitos = $pdo->prepare("SELECT sh.fecha FROM seguimiento_habitos sh INNER JOIN habitos h ON h.id=sh.habito_id WHERE h.usuario_id=:uid AND sh.completado=1 AND MONTH(sh.fecha)=:m AND YEAR(sh.fecha)=:y GROUP BY sh.fecha");
$habitos->execute(['uid'=>$uid,'m'=>$mes,'y'=>$anio]);
$habitoMap = array_flip(array_column($habitos->fetchAll(), 'fecha'));

// Nunca usar strftime()/date('F') para nombres de mes: strftime() está obsoleta desde
// PHP 8.1 y ambas dependen del idioma configurado en el sistema operativo — en Windows
// por defecto sale en inglés sin importar el idioma de la app. Arreglo fijo, sin depender
// de locale ni del sistema operativo.
$mesesEspanol = ['', 'enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$nivelEtiqueta = [1=>'Muy bajo', 2=>'Bajo', 3=>'Regular', 4=>'Bueno', 5=>'Muy bueno'];
$tituloPagina = 'Calendario';
require __DIR__ . '/../parciales/encabezado.php';
?>
<h1>Calendario</h1>
<div class="recuadro-calendario">
  <div class="cabecera-calendario">
    <a href="?mes=<?= $mes==1?12:$mes-1 ?>&anio=<?= $mes==1?$anio-1:$anio ?>" class="boton-mes" data-sonido="clic">‹</a>
    <strong class="titulo-mes"><?= $mesesEspanol[$mes] ?> <?= $anio ?></strong>
    <a href="?mes=<?= $mes==12?1:$mes+1 ?>&anio=<?= $mes==12?$anio+1:$anio ?>" class="boton-mes" data-sonido="clic">›</a>
  </div>
  <div class="grilla-calendario">
    <?php foreach (['L','M','X','J','V','S','D'] as $d): ?>
      <div class="dia-semana-calendario"><?= $d ?></div>
    <?php endforeach; ?>
    <?php for ($i=1; $i<$iniciaSemana; $i++): ?><div></div><?php endfor; ?>
    <?php for ($d=1; $d<=$diasEnMes; $d++):
      $fecha = date('Y-m-d', mktime(0,0,0,$mes,$d,$anio));
      $nivelRedondeado = isset($emoMap[$fecha]) ? (int)round($emoMap[$fecha]) : null;
      $tieneHabito = isset($habitoMap[$fecha]);
      $esHoy = $fecha === date('Y-m-d');
    ?>
    <div class="dia-calendario <?= $nivelRedondeado ? 'dia-nivel-'.$nivelRedondeado : '' ?> <?= $esHoy ? 'dia-hoy' : '' ?>">
      <span class="numero-dia"><?= $d ?></span>
      <?php if ($tieneHabito): ?><span class="marca-habito-dia" title="Hábito completado">🔥</span><?php endif; ?>
      <?php if (isset($emoMap[$fecha])): ?><span class="nivel-dia"><?= $emoMap[$fecha] ?></span><?php endif; ?>
    </div>
    <?php endfor; ?>
  </div>
  <div class="leyenda-calendario">
    <?php foreach ($nivelEtiqueta as $n=>$etq): ?>
    <span class="item-leyenda"><span class="muestra-color dia-nivel-<?= $n ?>"></span><?= $etq ?></span>
    <?php endforeach; ?>
    <span class="item-leyenda">🔥 Hábito completado</span>
  </div>
</div>
<?php require __DIR__ . '/../parciales/pie.php'; ?>
