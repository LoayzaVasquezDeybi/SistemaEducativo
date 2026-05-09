<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // OBTENER TODOS LOS ESTUDIANTES
        case 'obtener':
            $stmt = $conn->query("
                SELECT e.id_estudiante, e.codigo, e.nombre, e.apellido, e.dni, e.fecha_nacimiento, 
                       g.nombre as grado, s.nombre as seccion, e.estado
                FROM estudiante e
                LEFT JOIN grado g ON e.id_grado = g.id_grado
                LEFT JOIN seccion s ON e.id_seccion = s.id_seccion
                ORDER BY e.nombre
            ");
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $estudiantes]);
            break;

        // CREAR NUEVO ESTUDIANTE
        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("
                INSERT INTO estudiante (codigo, nombre, apellido, dni, fecha_nacimiento, id_grado, id_seccion, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['codigo'], $data['nombre'], $data['apellido'], $data['dni'],
                $data['fecha_nacimiento'], $data['id_grado'], $data['id_seccion'], 'activo'
            ]);
            echo json_encode(['success' => true, 'message' => 'Estudiante registrado']);
            break;

        // OBTENER GRADOS Y SECCIONES
        case 'combo':
            $grados = $conn->query("SELECT id_grado, nombre FROM grado")->fetchAll(PDO::FETCH_ASSOC);
            $secciones = $conn->query("SELECT id_seccion, nombre FROM seccion")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'grados' => $grados, 'secciones' => $secciones]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
