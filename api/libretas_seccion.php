<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

$user=usuarioActual($conn);
if (!$user['id_usuario']) { http_response_code(401); exit('Debe iniciar sesión.'); }
if (!esDocente($user) || !$user['id_docente']) { http_response_code(403); exit('Solo los tutores pueden descargar libretas.'); }

$stmt=$conn->prepare("SELECT ts.id_grado,ts.id_seccion,ts.id_periodo,g.nombre AS grado,s.nombre AS seccion,pa.anio,pa.nombre AS periodo
    FROM tutor_seccion ts INNER JOIN grado g ON ts.id_grado=g.id_grado INNER JOIN seccion s ON ts.id_seccion=s.id_seccion
    INNER JOIN periodo_academico pa ON ts.id_periodo=pa.id_periodo
    WHERE ts.id_docente=? AND LOWER(pa.estado)='activo' LIMIT 1");
$stmt->execute([$user['id_docente']]);
$tutoria=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$tutoria) { http_response_code(403); exit('No tiene una sección asignada como tutor.'); }

$stmt=$conn->prepare("SELECT id_estudiante,codigo_estudiante,nombre,apellido,dni FROM estudiante
    WHERE id_grado=? AND id_seccion=? AND (estado IS NULL OR LOWER(estado)='activo')
    ORDER BY TRIM(UPPER(apellido)) ASC, TRIM(UPPER(nombre)) ASC");
$stmt->execute([$tutoria['id_grado'],$tutoria['id_seccion']]);
$estudiantes=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$conn->prepare("SELECT id_periodo_evaluacion,nombre FROM periodo_evaluacion WHERE id_periodo=? ORDER BY id_periodo_evaluacion");
$stmt->execute([$tutoria['id_periodo']]);
$periodos=$stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt=$conn->prepare("SELECT n.id_estudiante,c.id_curso,c.nombre AS curso,pe.id_periodo_evaluacion,ROUND(AVG(n.calificacion),2) AS promedio
    FROM nota n INNER JOIN curso_docente cd ON n.id_curso_docente=cd.id_curso_docente INNER JOIN curso c ON cd.id_curso=c.id_curso
    INNER JOIN periodo_evaluacion pe ON n.id_periodo_evaluacion=pe.id_periodo_evaluacion
    INNER JOIN estudiante e ON n.id_estudiante=e.id_estudiante
    WHERE e.id_grado=? AND e.id_seccion=? AND pe.id_periodo=?
    GROUP BY n.id_estudiante,c.id_curso,c.nombre,pe.id_periodo_evaluacion ORDER BY c.nombre,pe.id_periodo_evaluacion");
$stmt->execute([$tutoria['id_grado'],$tutoria['id_seccion'],$tutoria['id_periodo']]);
$promedios=[];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $n) {
    $id=$n['id_estudiante']; $curso=$n['id_curso'];
    if (!isset($promedios[$id][$curso])) $promedios[$id][$curso]=['nombre'=>$n['curso'],'notas'=>[]];
    $promedios[$id][$curso]['notas'][$n['id_periodo_evaluacion']]=$n['promedio'];
}
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$docente=trim(($_SESSION['nombres']??'').' '.($_SESSION['apellidos']??''));
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Libretas - <?= $h($tutoria['grado'].' '.$tutoria['seccion']) ?></title>
<style>
:root{--azul:#174f8f;--claro:#eaf2fb;--texto:#172033;--gris:#617087;--borde:#cbd5e1;--verde:#08734f;--rojo:#b42318}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{size:A4 portrait;margin:12mm}body{margin:0;padding:24px;background:#eef2f7;color:var(--texto);font-family:Arial,sans-serif}.aviso{max-width:210mm;margin:0 auto 16px;padding:11px;background:#172033;color:#fff;text-align:center;border-radius:8px;font-size:13px}.libreta{width:210mm;min-height:273mm;margin:0 auto 24px;padding:15mm;background:#fff;box-shadow:0 8px 28px #1e293b20;position:relative;page-break-after:always}.libreta:last-child{page-break-after:auto}header{display:grid;grid-template-columns:56px 1fr auto;gap:14px;align-items:center;padding-bottom:13px;border-bottom:3px solid var(--azul)}.logo{width:54px;height:54px;display:grid;place-items:center;border-radius:11px;background:var(--azul);color:#fff;font-weight:800;font-size:19px}header h1{margin:2px 0;font-size:18px}header p{margin:0;color:var(--gris);font-size:9px}.titulo{text-align:right}.titulo strong{display:block;color:var(--azul);font-size:15px;text-transform:uppercase}.titulo span{color:var(--gris);font-size:9px}.datos{display:grid;grid-template-columns:2fr 1fr 1fr;margin:15px 0;border:1px solid var(--borde);border-radius:7px;overflow:hidden}.dato{padding:8px 10px;min-height:48px;border-right:1px solid var(--borde);border-bottom:1px solid var(--borde)}.dato:nth-child(3n){border-right:0}.dato span{display:block;color:var(--gris);font-size:8px;font-weight:700;text-transform:uppercase}.dato strong{display:block;margin-top:5px;font-size:11px}h2{margin:18px 0 7px;color:var(--azul);font-size:12px;text-transform:uppercase}table{width:100%;border-collapse:collapse;font-size:9px}th,td{padding:7px 5px;border:1px solid var(--borde);text-align:center}th{background:var(--claro);color:#29445f;text-transform:uppercase}td:first-child{text-align:left;font-weight:700}.ok{color:var(--verde);font-weight:700}.mal{color:var(--rojo);font-weight:700}.vacio{padding:22px;text-align:center;color:var(--gris);font-size:11px}.leyenda{color:var(--gris);font-size:8px}.firmas{display:grid;grid-template-columns:1fr 1fr;gap:65px;margin:70px 25px 0;text-align:center}.firma{padding-top:7px;border-top:1px solid #64748b;font-size:9px}.firma span{display:block;margin-top:3px;color:var(--gris)}footer{position:absolute;bottom:11mm;left:15mm;right:15mm;padding-top:7px;border-top:1px solid var(--borde);color:var(--gris);text-align:center;font-size:7px}@media print{body{padding:0;background:#fff}.aviso{display:none}.libreta{width:auto;min-height:273mm;margin:0;padding:0;box-shadow:none}footer{left:0;right:0}}
</style></head><body onload="window.print()"><div class="aviso">Se generaron <?= count($estudiantes) ?> libretas. Seleccione “Guardar como PDF” para descargarlas en un solo archivo.</div>
<?php if (!$estudiantes): ?><main class="libreta"><div class="vacio">La sección no tiene estudiantes activos.</div></main><?php endif; ?>
<?php foreach($estudiantes as $e): $cursos=$promedios[$e['id_estudiante']]??[]; ?>
<main class="libreta"><header><div class="logo">IE</div><div><p>INSTITUCIÓN EDUCATIVA N.° 22237</p><h1>José Yataco Pachas</h1><p>Chincha Baja · Sistema de Gestión Escolar</p></div><div class="titulo"><strong>Libreta de notas</strong><span>Periodo lectivo <?= $h($tutoria['anio']) ?></span></div></header>
<section class="datos"><div class="dato"><span>Apellidos y nombres</span><strong><?= $h($e['apellido'].', '.$e['nombre']) ?></strong></div><div class="dato"><span>Código</span><strong><?= $h($e['codigo_estudiante']) ?></strong></div><div class="dato"><span>DNI</span><strong><?= $h($e['dni']?:'S/D') ?></strong></div><div class="dato"><span>Grado</span><strong><?= $h($tutoria['grado']) ?></strong></div><div class="dato"><span>Sección</span><strong><?= $h($tutoria['seccion']) ?></strong></div><div class="dato"><span>Periodo</span><strong><?= $h($tutoria['periodo']) ?></strong></div></section>
<h2>Resumen de calificaciones</h2><?php if(!$cursos): ?><div class="vacio">Todavía no tiene calificaciones registradas.</div><?php else: ?><table><thead><tr><th>Área / curso</th><?php foreach($periodos as $p): ?><th><?= $h($p['nombre']) ?></th><?php endforeach; ?><th>Promedio</th><th>Situación</th></tr></thead><tbody><?php foreach($cursos as $c):$suma=0;$cant=0;?><tr><td><?= $h($c['nombre']) ?></td><?php foreach($periodos as $p):$n=$c['notas'][$p['id_periodo_evaluacion']]??null;if($n!==null){$suma+=(float)$n;$cant++;}?><td class="<?= $n!==null?((float)$n>=11?'ok':'mal'):'' ?>"><?= $n!==null?number_format((float)$n,2):'—' ?></td><?php endforeach;$final=$cant?$suma/$cant:null;?><td class="<?= $final!==null?($final>=11?'ok':'mal'):'' ?>"><?= $final!==null?number_format($final,2):'—' ?></td><td class="<?= $final!==null?($final>=11?'ok':'mal'):'' ?>"><?= $final!==null?($final>=11?'Aprobado':'En proceso'):'Sin nota' ?></td></tr><?php endforeach;?></tbody></table><p class="leyenda">Escala vigesimal. Nota mínima aprobatoria: 11.</p><?php endif; ?>
<section class="firmas"><div class="firma">Padre, madre o apoderado<span>Firma y DNI</span></div><div class="firma"><?= $h($docente) ?><span>Docente tutor</span></div></section><footer>Generado el <?= date('d/m/Y H:i') ?> · IE N.° 22237 “José Yataco Pachas”</footer></main>
<?php endforeach; ?></body></html>
