<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';
// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER ESTUDIANTES
        case 'obtener':
            $query = "SELECT e.id_estudiante, e.codigo_estudiante as codigo, e.nombre, e.apellido, e.dni, e.fecha_nacimiento, e.id_grado, e.id_seccion,
                             COALESCE(g.nombre, 'Sin grado') as grado, COALESCE(s.nombre, 'Sin sección') as seccion, 
                             IFNULL(e.estado, 'activo') as estado 
                      FROM estudiante e 
                      LEFT JOIN grado g ON e.id_grado = g.id_grado 
                      LEFT JOIN seccion s ON e.id_seccion = s.id_seccion 
                      ORDER BY e.apellido, e.nombre";
            $stmt = $conn->query($query);
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $estudiantes]);
            break;

        // 2. OBTENER COMBOS (Grados y Secciones)
        case 'combo':
            $stmtGrados = $conn->query("SELECT id_grado, nombre FROM grado ORDER BY id_grado");
            $grados = $stmtGrados->fetchAll(PDO::FETCH_ASSOC);
            
            $stmtSecciones = $conn->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre");
            $secciones = $stmtSecciones->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'grados' => $grados, 'secciones' => $secciones]);
            break;

        // 3. CREAR ESTUDIANTE
        case 'crear':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                throw new Exception('No se recibieron datos válidos');
            }
            
            $sql = "INSERT INTO estudiante (codigo_estudiante, nombre, apellido, dni, fecha_nacimiento, id_grado, id_seccion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')";
            $stmt = $conn->prepare($sql);
            
            $result = $stmt->execute([
                $data['codigo'], 
                $data['nombre'], 
                $data['apellido'], 
                $data['dni'],
                $data['fecha_nacimiento'], 
                $data['id_grado'], 
                $data['id_seccion'] ?? 1
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Estudiante creado exitosamente']);
            } else {
                throw new Exception('Error al insertar en la base de datos');
            }
            break;

        // 4. ACTUALIZAR ESTUDIANTE
        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE estudiante SET codigo_estudiante=?, nombre=?, apellido=?, dni=?, id_grado=?, id_seccion=?, estado=? WHERE id_estudiante=?");
            $stmt->execute([
                $data['codigo'], 
                $data['nombre'], 
                $data['apellido'], 
                $data['dni'],
                $data['id_grado'], 
                $data['id_seccion'], 
                $data['estado'], 
                $data['id_estudiante']
            ]);
            echo json_encode(['success' => true, 'message' => '¡Estudiante actualizado con éxito!']);
            break;

        // 5. ELIMINAR ESTUDIANTE
        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_estudiante'] ?? $_POST['id_estudiante'];
            $stmt = $conn->prepare("DELETE FROM estudiante WHERE id_estudiante=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Estudiante eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>