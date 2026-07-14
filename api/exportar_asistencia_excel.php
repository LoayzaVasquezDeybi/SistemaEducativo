<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Error 403: Acceso Prohibido. Debe iniciar sesión.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="asistencia_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['FECHA', 'ESTUDIANTE', 'CURSO', 'ESTADO', 'OBSERVACION']);

$query = "SELECT 
            DATE_FORMAT(a.fecha, '%d/%m/%Y') as fecha,
            UPPER(CONCAT(e.apellido, ', ', e.nombre)) as estudiante,
            UPPER(c.nombre) as curso,
            UPPER(a.estado_asistencia) as estado_asistencia,
            a.observacion
          FROM asistencia a
          INNER JOIN estudiante e ON a.id_estudiante = e.id_estudiante
          INNER JOIN curso_docente cd ON a.id_curso_docente = cd.id_curso_docente
          INNER JOIN curso c ON cd.id_curso = c.id_curso
          ORDER BY a.fecha DESC, e.apellido, e.nombre, c.nombre";

try {
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    error_log("Error al generar el reporte CSV de asistencia: " . $e->getMessage());
    fclose($output);
    die("Error al generar el reporte CSV.");
}

fclose($output);
exit;
