<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

$user = usuarioActual($conn);
if (!$user['id_usuario']) {
    http_response_code(401);
    exit('Debe iniciar sesión para acceder a este documento.');
}
if (!esDocente($user) || empty($user['id_docente'])) {
    http_response_code(403);
    exit('Acceso denegado: solo los profesores pueden descargar libretas de notas.');
}

$idEstudiante = filter_input(INPUT_GET, 'id_estudiante', FILTER_VALIDATE_INT);
if (!$idEstudiante) {
    http_response_code(400);
    exit('Debe seleccionar un estudiante válido.');
}

$stmtPermiso = $conn->prepare("SELECT COUNT(*) FROM estudiante e
    INNER JOIN tutor_seccion ts ON ts.id_grado=e.id_grado AND ts.id_seccion=e.id_seccion
    INNER JOIN periodo_academico pa ON ts.id_periodo=pa.id_periodo
    WHERE e.id_estudiante=? AND ts.id_docente=? AND LOWER(pa.estado)='activo'");
$stmtPermiso->execute([$idEstudiante, $user['id_docente']]);
if (!(int) $stmtPermiso->fetchColumn()) {
    http_response_code(403);
    exit('No tiene permiso: solo el tutor asignado puede descargar la libreta de este estudiante.');
}

$stmtEstudiante = $conn->prepare("SELECT e.codigo_estudiante, e.nombre, e.apellido, e.dni,
        g.nombre AS grado, s.nombre AS seccion
    FROM estudiante e
    LEFT JOIN grado g ON e.id_grado = g.id_grado
    LEFT JOIN seccion s ON e.id_seccion = s.id_seccion
    WHERE e.id_estudiante = ?");
$stmtEstudiante->execute([$idEstudiante]);
$estudiante = $stmtEstudiante->fetch(PDO::FETCH_ASSOC);
if (!$estudiante) {
    http_response_code(404);
    exit('El estudiante no existe.');
}

$stmtPeriodo = $conn->query("SELECT id_periodo, anio, nombre FROM periodo_academico WHERE LOWER(estado)='activo' ORDER BY id_periodo DESC LIMIT 1");
$periodoAcademico = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
if (!$periodoAcademico) {
    $periodoAcademico = $conn->query("SELECT id_periodo, anio, nombre FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
}

$stmtPeriodos = $conn->prepare("SELECT id_periodo_evaluacion, nombre FROM periodo_evaluacion
    WHERE id_periodo = ? ORDER BY id_periodo_evaluacion");
$stmtPeriodos->execute([$periodoAcademico['id_periodo'] ?? 0]);
$periodos = $stmtPeriodos->fetchAll(PDO::FETCH_ASSOC);

$stmtNotas = $conn->prepare("SELECT c.id_curso, c.nombre AS curso, pe.id_periodo_evaluacion,
        pe.nombre AS periodo, ROUND(AVG(n.calificacion), 2) AS promedio,
        GROUP_CONCAT(CONCAT(n.evaluacion, ': ', FORMAT(n.calificacion, 2)) ORDER BY n.fecha_registro SEPARATOR ' | ') AS detalle
    FROM nota n
    INNER JOIN curso_docente cd ON n.id_curso_docente = cd.id_curso_docente
    INNER JOIN curso c ON cd.id_curso = c.id_curso
    INNER JOIN periodo_evaluacion pe ON n.id_periodo_evaluacion = pe.id_periodo_evaluacion
    WHERE n.id_estudiante = ? AND pe.id_periodo = ?
    GROUP BY c.id_curso, c.nombre, pe.id_periodo_evaluacion, pe.nombre
    ORDER BY c.nombre, pe.id_periodo_evaluacion");
$stmtNotas->execute([$idEstudiante, $periodoAcademico['id_periodo'] ?? 0]);
$notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);

$cursos = [];
foreach ($notas as $nota) {
    $idCurso = $nota['id_curso'];
    if (!isset($cursos[$idCurso])) $cursos[$idCurso] = ['nombre' => $nota['curso'], 'periodos' => []];
    $cursos[$idCurso]['periodos'][$nota['id_periodo_evaluacion']] = $nota;
}

$h = static fn($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$nombreCompleto = trim(($estudiante['apellido'] ?? '') . ', ' . ($estudiante['nombre'] ?? ''));
$nombreDocente = trim(($_SESSION['nombres'] ?? '') . ' ' . ($_SESSION['apellidos'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Libreta de notas - <?= $h($nombreCompleto) ?></title>
<style>
    :root { --azul:#174f8f; --azul-claro:#eaf2fb; --texto:#172033; --gris:#617087; --borde:#cbd5e1; --verde:#08734f; --rojo:#b42318; }
    * { box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    @page { size:A4 portrait; margin:12mm; }
    body { margin:0; padding:24px; background:#eef2f7; color:var(--texto); font-family:Arial,sans-serif; }
    .aviso { max-width:210mm; margin:0 auto 16px; padding:11px 16px; border-radius:8px; background:#172033; color:#fff; text-align:center; font-size:13px; }
    .libreta { width:210mm; min-height:273mm; margin:auto; padding:15mm; background:#fff; box-shadow:0 8px 28px #1e293b20; }
    header { display:grid; grid-template-columns:58px 1fr auto; align-items:center; gap:14px; padding-bottom:14px; border-bottom:3px solid var(--azul); }
    .logo { width:56px; height:56px; display:grid; place-items:center; border-radius:12px; background:var(--azul); color:#fff; font-size:20px; font-weight:800; }
    header h1 { margin:2px 0; font-size:19px; } header p { margin:0; color:var(--gris); font-size:10px; }
    .titulo { text-align:right; } .titulo strong { display:block; color:var(--azul); font-size:16px; text-transform:uppercase; } .titulo span { font-size:10px; color:var(--gris); }
    .estudiante { display:grid; grid-template-columns:2fr 1fr 1fr; margin:16px 0; border:1px solid var(--borde); border-radius:8px; overflow:hidden; }
    .dato { min-height:53px; padding:9px 11px; border-right:1px solid var(--borde); border-bottom:1px solid var(--borde); }
    .dato:nth-child(3n) { border-right:0; } .dato span { display:block; color:var(--gris); font-size:9px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; } .dato strong { display:block; margin-top:5px; font-size:12px; }
    h2 { margin:20px 0 8px; color:var(--azul); font-size:13px; text-transform:uppercase; letter-spacing:.5px; }
    table { width:100%; border-collapse:collapse; font-size:10px; } th { padding:8px 6px; border:1px solid var(--borde); background:var(--azul-claro); color:#29445f; text-transform:uppercase; } td { padding:8px 6px; border:1px solid var(--borde); text-align:center; } td:first-child { text-align:left; font-weight:700; }
    .aprobado { color:var(--verde); font-weight:700; } .desaprobado { color:var(--rojo); font-weight:700; } .sin-nota { color:#94a3b8; }
    .leyenda { margin-top:8px; color:var(--gris); font-size:9px; } .vacio { padding:22px; color:var(--gris); text-align:center; }
    .detalle { margin-top:14px; } .detalle-item { padding:7px 0; border-bottom:1px solid #e5eaf0; font-size:9px; } .detalle-item strong { color:var(--azul); }
    .firmas { display:grid; grid-template-columns:1fr 1fr; gap:65px; margin:60px 24px 20px; text-align:center; } .firma { padding-top:7px; border-top:1px solid #64748b; } .firma strong,.firma span { display:block; font-size:10px; } .firma span { margin-top:3px; color:var(--gris); font-size:9px; }
    footer { margin-top:28px; padding-top:8px; border-top:1px solid var(--borde); color:var(--gris); text-align:center; font-size:8px; }
    @media print { body { padding:0; background:#fff; } .aviso { display:none; } .libreta { width:auto; min-height:auto; padding:0; box-shadow:none; } }
</style>
</head>
<body onload="window.print()">
<div class="aviso">Seleccione “Guardar como PDF” para descargar la libreta.</div>
<main class="libreta">
    <header>
        <div class="logo">IE</div>
        <div><p>INSTITUCIÓN EDUCATIVA N.° 22237</p><h1>José Yataco Pachas</h1><p>Chincha Baja · Sistema de Gestión Escolar</p></div>
        <div class="titulo"><strong>Libreta de notas</strong><span>Periodo lectivo <?= $h($periodoAcademico['anio'] ?? date('Y')) ?></span></div>
    </header>
    <section class="estudiante">
        <div class="dato"><span>Apellidos y nombres</span><strong><?= $h($nombreCompleto) ?></strong></div>
        <div class="dato"><span>Código</span><strong><?= $h($estudiante['codigo_estudiante'] ?: '-') ?></strong></div>
        <div class="dato"><span>DNI</span><strong><?= $h($estudiante['dni'] ?: 'S/D') ?></strong></div>
        <div class="dato"><span>Grado</span><strong><?= $h($estudiante['grado'] ?: '-') ?></strong></div>
        <div class="dato"><span>Sección</span><strong><?= $h($estudiante['seccion'] ?: '-') ?></strong></div>
        <div class="dato"><span>Periodo académico</span><strong><?= $h($periodoAcademico['nombre'] ?? '-') ?></strong></div>
    </section>
    <h2>Resumen de calificaciones</h2>
    <?php if (!$cursos): ?>
        <div class="vacio">El estudiante todavía no tiene calificaciones registradas en este periodo.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Área / curso</th><?php foreach ($periodos as $periodo): ?><th><?= $h($periodo['nombre']) ?></th><?php endforeach; ?><th>Promedio final</th><th>Situación</th></tr></thead>
        <tbody>
        <?php foreach ($cursos as $curso): $suma=0; $cantidad=0; ?>
            <tr><td><?= $h($curso['nombre']) ?></td>
            <?php foreach ($periodos as $periodo): $nota=$curso['periodos'][$periodo['id_periodo_evaluacion']] ?? null; if ($nota) { $suma+=(float)$nota['promedio']; $cantidad++; } ?>
                <td class="<?= $nota ? ((float)$nota['promedio'] >= 11 ? 'aprobado' : 'desaprobado') : 'sin-nota' ?>"><?= $nota ? number_format((float)$nota['promedio'], 2) : '—' ?></td>
            <?php endforeach; $final=$cantidad ? $suma/$cantidad : null; ?>
                <td class="<?= $final !== null ? ($final >= 11 ? 'aprobado' : 'desaprobado') : 'sin-nota' ?>"><?= $final !== null ? number_format($final, 2) : '—' ?></td>
                <td class="<?= $final !== null ? ($final >= 11 ? 'aprobado' : 'desaprobado') : 'sin-nota' ?>"><?= $final !== null ? ($final >= 11 ? 'Aprobado' : 'En proceso') : 'Sin nota' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="leyenda">Escala vigesimal: nota mínima aprobatoria 11. Los promedios se calculan con las evaluaciones registradas en cada periodo.</p>
    <div class="detalle"><h2>Detalle de evaluaciones</h2><?php foreach ($notas as $nota): ?><div class="detalle-item"><strong><?= $h($nota['curso']) ?> · <?= $h($nota['periodo']) ?>:</strong> <?= $h($nota['detalle']) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <section class="firmas"><div class="firma"><strong>Padre, madre o apoderado</strong><span>Firma y DNI</span></div><div class="firma"><strong><?= $h($nombreDocente) ?></strong><span>Docente responsable</span></div></section>
    <footer>Documento generado el <?= date('d/m/Y H:i') ?> · IE N.° 22237 “José Yataco Pachas”</footer>
</main>
</body>
</html>
