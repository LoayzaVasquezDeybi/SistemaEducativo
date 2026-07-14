<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

$user=usuarioActual($conn);
if (!$user['id_usuario']) { http_response_code(401); exit('Debe iniciar sesión.'); }
if (!esDocente($user) || !$user['id_docente']) { http_response_code(403); exit('Solo los profesores pueden generar este reporte.'); }

$idCurso=filter_input(INPUT_GET,'id_curso_docente',FILTER_VALIDATE_INT);
$idGrado=filter_input(INPUT_GET,'id_grado',FILTER_VALIDATE_INT);
$idSeccion=filter_input(INPUT_GET,'id_seccion',FILTER_VALIDATE_INT);
$idEstudiante=filter_input(INPUT_GET,'id_estudiante',FILTER_VALIDATE_INT) ?: null;
$desde=$_GET['desde']??''; $hasta=$_GET['hasta']??'';

function fechaReporteValida(string $fecha): bool { $d=DateTime::createFromFormat('Y-m-d',$fecha); return $d && $d->format('Y-m-d')===$fecha; }
if (!$idCurso || !$idGrado || !$idSeccion || !fechaReporteValida($desde) || !fechaReporteValida($hasta) || $desde>$hasta) {
    http_response_code(400); exit('Los parámetros del reporte no son válidos.');
}

$stmt=$conn->prepare("SELECT DISTINCT c.nombre AS curso,g.nombre AS grado,s.nombre AS seccion,CONCAT(u.nombres,' ',u.apellidos) AS docente
    FROM horario h INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente INNER JOIN curso c ON cd.id_curso=c.id_curso
    INNER JOIN grado g ON h.id_grado=g.id_grado INNER JOIN seccion s ON h.id_seccion=s.id_seccion
    INNER JOIN docente d ON cd.id_docente=d.id_docente INNER JOIN usuario u ON d.id_usuario=u.id_usuario
    WHERE h.id_curso_docente=? AND h.id_grado=? AND h.id_seccion=? AND cd.id_docente=? LIMIT 1");
$stmt->execute([$idCurso,$idGrado,$idSeccion,$user['id_docente']]);
$asignacion=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$asignacion) { http_response_code(403); exit('No tiene permiso para generar reportes de este curso, grado y sección.'); }

$sqlEst="SELECT id_estudiante,codigo_estudiante,nombre,apellido,dni FROM estudiante WHERE id_grado=? AND id_seccion=? AND (estado IS NULL OR LOWER(estado)='activo')";
$paramsEst=[$idGrado,$idSeccion];
if ($idEstudiante) { $sqlEst.=" AND id_estudiante=?"; $paramsEst[]=$idEstudiante; }
$sqlEst.=" ORDER BY TRIM(UPPER(apellido)),TRIM(UPPER(nombre))";
$stmt=$conn->prepare($sqlEst); $stmt->execute($paramsEst); $estudiantes=$stmt->fetchAll(PDO::FETCH_ASSOC);
if ($idEstudiante && !$estudiantes) { http_response_code(403); exit('El estudiante no pertenece al grado y sección asignados.'); }

$ids=array_column($estudiantes,'id_estudiante'); $registros=[];
if ($ids) {
    $marcas=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$conn->prepare("SELECT id_estudiante,fecha,estado_asistencia,observacion FROM asistencia
        WHERE id_curso_docente=? AND fecha BETWEEN ? AND ? AND id_estudiante IN ($marcas)
        ORDER BY fecha,id_estudiante");
    $stmt->execute(array_merge([$idCurso,$desde,$hasta],$ids));
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $registros[$r['id_estudiante']][]=$r;
}

$resumen=[];
foreach($estudiantes as $e) {
    $conteo=['Presente'=>0,'Tardanza'=>0,'Falta'=>0];
    foreach($registros[$e['id_estudiante']]??[] as $r) if(isset($conteo[$r['estado_asistencia']])) $conteo[$r['estado_asistencia']]++;
    $total=array_sum($conteo); $conteo['total']=$total; $conteo['porcentaje']=$total?round((($conteo['Presente']+$conteo['Tardanza'])/$total)*100,1):0;
    $resumen[$e['id_estudiante']]=$conteo;
}
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$fechaVista=static fn($v)=>date('d/m/Y',strtotime($v));
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Reporte de asistencia - <?= $h($asignacion['curso']) ?></title>
<style>
:root{--azul:#174f8f;--claro:#eaf2fb;--texto:#172033;--gris:#617087;--borde:#cbd5e1;--verde:#08734f;--rojo:#b42318;--ambar:#a15c00}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}@page{size:A4 portrait;margin:12mm}body{margin:0;padding:24px;background:#eef2f7;color:var(--texto);font-family:Arial,sans-serif}.aviso{max-width:210mm;margin:0 auto 16px;padding:11px;background:#172033;color:#fff;border-radius:8px;text-align:center;font-size:12px}.pagina{width:210mm;min-height:273mm;margin:auto;padding:14mm;background:#fff;box-shadow:0 8px 28px #1e293b20}header{display:grid;grid-template-columns:54px 1fr auto;gap:13px;align-items:center;padding-bottom:12px;border-bottom:3px solid var(--azul)}.logo{width:52px;height:52px;display:grid;place-items:center;border-radius:11px;background:var(--azul);color:#fff;font-weight:800;font-size:18px}header h1{margin:2px 0;font-size:17px}header p{margin:0;color:var(--gris);font-size:9px}.titulo{text-align:right}.titulo strong{display:block;color:var(--azul);font-size:14px;text-transform:uppercase}.titulo span{display:block;margin-top:3px;color:var(--gris);font-size:9px}.datos{display:grid;grid-template-columns:2fr 1fr 1fr;margin:14px 0;border:1px solid var(--borde);border-radius:7px;overflow:hidden}.dato{padding:8px 10px;border-right:1px solid var(--borde)}.dato:last-child{border:0}.dato span{display:block;color:var(--gris);font-size:8px;font-weight:700;text-transform:uppercase}.dato strong{display:block;margin-top:4px;font-size:10px}h2{margin:18px 0 7px;color:var(--azul);font-size:12px;text-transform:uppercase}table{width:100%;border-collapse:collapse;font-size:9px}th,td{padding:7px 6px;border:1px solid var(--borde);text-align:left}th{background:var(--claro);color:#29445f;text-transform:uppercase}td.numero,th.numero{text-align:center}.presente{color:var(--verde);font-weight:700}.tardanza{color:var(--ambar);font-weight:700}.falta{color:var(--rojo);font-weight:700}.estudiante-detalle{margin-top:16px;break-inside:avoid}.estudiante-detalle h3{margin:0 0 5px;font-size:10px}.sin-datos{padding:18px;text-align:center;color:var(--gris)}footer{margin-top:25px;padding-top:7px;border-top:1px solid var(--borde);color:var(--gris);text-align:center;font-size:8px}@media print{body{padding:0;background:#fff}.aviso{display:none}.pagina{width:auto;min-height:auto;padding:0;box-shadow:none}}
</style></head><body onload="window.print()"><div class="aviso">Seleccione “Guardar como PDF” para descargar el reporte.</div><main class="pagina">
<header><div class="logo">IE</div><div><p>INSTITUCIÓN EDUCATIVA N.° 22237</p><h1>José Yataco Pachas</h1><p>Chincha Baja · Control académico</p></div><div class="titulo"><strong>Reporte de asistencia</strong><span><?= $h($asignacion['curso']) ?></span><span><?= $fechaVista($desde) ?> al <?= $fechaVista($hasta) ?></span></div></header>
<section class="datos"><div class="dato"><span>Docente</span><strong><?= $h($asignacion['docente']) ?></strong></div><div class="dato"><span>Grado</span><strong><?= $h($asignacion['grado']) ?></strong></div><div class="dato"><span>Sección</span><strong><?= $h($asignacion['seccion']) ?></strong></div></section>
<h2>Resumen por estudiante</h2><table><thead><tr><th>Estudiante</th><th class="numero">Presente</th><th class="numero">Tardanza</th><th class="numero">Falta</th><th class="numero">Días registrados</th><th class="numero">Asistencia</th></tr></thead><tbody>
<?php if(!$estudiantes): ?><tr><td colspan="6" class="sin-datos">No hay estudiantes activos en esta sección.</td></tr><?php endif; ?>
<?php foreach($estudiantes as $e):$r=$resumen[$e['id_estudiante']];?><tr><td><strong><?= $h($e['apellido'].', '.$e['nombre']) ?></strong><br><small><?= $h($e['codigo_estudiante']) ?></small></td><td class="numero presente"><?= $r['Presente'] ?></td><td class="numero tardanza"><?= $r['Tardanza'] ?></td><td class="numero falta"><?= $r['Falta'] ?></td><td class="numero"><?= $r['total'] ?></td><td class="numero"><?= number_format($r['porcentaje'],1) ?>%</td></tr><?php endforeach; ?></tbody></table>
<h2>Detalle de días registrados</h2>
<?php foreach($estudiantes as $e):$lista=$registros[$e['id_estudiante']]??[];?><section class="estudiante-detalle"><h3><?= $h($e['apellido'].', '.$e['nombre']) ?></h3><table><thead><tr><th>Fecha</th><th>Estado</th><th>Observación</th></tr></thead><tbody><?php if(!$lista):?><tr><td colspan="3" class="sin-datos">Sin registros en el rango seleccionado.</td></tr><?php endif;?><?php foreach($lista as $r):$clase=strtolower($r['estado_asistencia']);?><tr><td><?= $fechaVista($r['fecha']) ?></td><td class="<?= $h($clase) ?>"><?= $h($r['estado_asistencia']) ?></td><td><?= $h($r['observacion']?:'—') ?></td></tr><?php endforeach;?></tbody></table></section><?php endforeach; ?>
<footer>Documento generado el <?= date('d/m/Y H:i') ?> · Los totales consideran únicamente los días con asistencia registrada para este curso.</footer></main></body></html>
