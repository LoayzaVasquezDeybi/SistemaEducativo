<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

try {
    // Contar estudiantes activos
    $stmtEst = $conn->query("SELECT COUNT(*) as total FROM estudiante WHERE estado = 'activo'");
    $total_estudiantes = $stmtEst->fetch(PDO::FETCH_ASSOC)['total'];

    // Contar docentes
    $stmtDoc = $conn->query("SELECT COUNT(*) as total FROM docente");
    $total_docentes = $stmtDoc->fetch(PDO::FETCH_ASSOC)['total'];

    // Contar cursos activos
    $stmtCur = $conn->query("SELECT COUNT(*) as total FROM curso WHERE estado = 'activo'");
    $total_cursos = $stmtCur->fetch(PDO::FETCH_ASSOC)['total'];

    // Contar usuarios activos
    $stmtUsu = $conn->query("SELECT COUNT(*) as total FROM usuario WHERE id_estado_usuario = 1");
    $total_usuarios = $stmtUsu->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true, 
        'data' => [
            'estudiantes' => $total_estudiantes,
            'docentes' => $total_docentes,
            'cursos' => $total_cursos,
            'usuarios' => $total_usuarios
        ]
    ]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>