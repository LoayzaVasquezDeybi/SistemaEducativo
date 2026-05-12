<?php
session_start();
require_once '../config/conexion.php';

// Validar que el usuario tenga sesión activa
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Error 403: Acceso Prohibido. Debe iniciar sesión.");
}

// Configurar cabeceras para descarga de archivo CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="padron_usuarios_' . date('Ymd_His') . '.csv"');

// Crear un archivo de salida
$output = fopen('php://output', 'w');

// Escribir la cabecera del CSV
fputcsv($output, ['APELLIDOS Y NOMBRES', 'DNI', 'EMAIL', 'ROL', 'ESTADO']);

// Consulta para obtener los datos de usuarios
$query = "SELECT
            UPPER(CONCAT(u.apellidos, ', ', u.nombres)) as nombres_completos,
            u.dni,
            UPPER(u.email) as email,
            UPPER(r.nombre) as rol,
            UPPER(IF(u.id_estado_usuario = 1, 'activo', 'inactivo')) as estado
          FROM usuario u
          LEFT JOIN rol r ON u.id_rol = r.id_rol
          ORDER BY u.apellidos, u.nombres";

try {
    $stmt = $conn->query($query);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    error_log("Error al generar el reporte CSV de usuarios: " . $e->getMessage());
    fclose($output);
    die("Error al generar el reporte CSV.");
}

fclose($output);
exit;