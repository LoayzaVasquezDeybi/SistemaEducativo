<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        case 'obtener':
            $query = "SELECT m.id_matricula, m.id_estudiante, m.id_vacante, m.fecha_matricula, m.id_estado_matricula,
                             CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni
                      FROM matricula m
                      INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                      ORDER BY m.fecha_matricula DESC";
            $stmt = $conn->query($query);
            $matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $matriculas]);
            break;

        case 'combo_estudiantes':
            $stmt = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE estado = 'activo' ORDER BY apellido, nombre");
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estudiantes' => $estudiantes]);
            break;

        case 'combo_vacantes':
            $stmt = $conn->query("SELECT id_vacante FROM vacante");
            $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'vacantes' => $vacantes]);
            break;

        case 'combo_estados':
            $stmt = $conn->query("SELECT * FROM estado_matricula");
            $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estados' => $estados]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $stmt = $conn->prepare("INSERT INTO matricula (id_estudiante, id_vacante, fecha_matricula, id_estado_matricula) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['id_estudiante'],
                $data['id_vacante'],
                $data['fecha_matricula'],
                $data['id_estado_matricula']
            ]);
            echo json_encode(['success' => true, 'message' => 'Matrícula registrada correctamente']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE matricula SET id_estudiante=?, id_vacante=?, fecha_matricula=?, id_estado_matricula=? WHERE id_matricula=?");
            $stmt->execute([
                $data['id_estudiante'], $data['id_vacante'], $data['fecha_matricula'], $data['id_estado_matricula'], $data['id_matricula']
            ]);
            echo json_encode(['success' => true, 'message' => 'Matrícula actualizada con éxito']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            $conn->beginTransaction();
            
            // 1. Liberar los cursos asignados a esta matrícula para evitar errores de restricción
            $stmtCursos = $conn->prepare("DELETE FROM matricula_curso WHERE id_matricula=?");
            $stmtCursos->execute([$data['id_matricula']]);
            
            // 2. Borrar la matrícula
            $stmt = $conn->prepare("DELETE FROM matricula WHERE id_matricula=?");
            $stmt->execute([$data['id_matricula']]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Registro de matrícula eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>