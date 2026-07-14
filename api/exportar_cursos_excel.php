<?php
session_start();
require_once '../config/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Error 403: Acceso Prohibido. Debe iniciar sesión.");
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="cursos_' . date('Ymd_His') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['NOMBRE', 'DESCRIPCION', 'CREDITOS', 'DOCENTE', 'ESTADO']);

$query = "SELECT 
            c.nombre,
            c.descripcion,
            c.creditos,
            CONCAT(COALESCE(u.nombres, ''), ' ', COALESCE(u.apellidos, '')) as docente,
            UPPER(c.estado) as estado
          FROM curso c
          LEFT JOIN curso_docente cd ON c.id_curso = cd.id_curso
          LEFT JOIN docente d ON cd.id_docente = d.id_docente
          LEFT JOIN usuario u ON d.id_usuario = u.id_usuario
          ORDER BY c.nombre";

try {
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    error_log("Error al generar el reporte CSV de cursos: " . $e->getMessage());
    fclose($output);
    die("Error al generar el reporte CSV.");
}

fclose($output);
exit;
