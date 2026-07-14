<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

// Activar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en HTML

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user = usuarioActual($conn);

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No se especificó acción (action)']);
    exit;
}

try {
    switch($action) {
        // 1. OBTENER TODAS LAS AULAS
        case 'obtener':
            $params = [];
            $where = '';
            if (esDocente($user) && $user['id_docente']) {
                $where = " WHERE EXISTS (
                    SELECT 1 FROM horario h
                    INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                    WHERE h.id_aula = aula.id_aula AND cd.id_docente = ?
                )";
                $params[] = $user['id_docente'];
            }
            $sql = "SELECT id_aula, nombre_aula, capacidad, ubicacion, estado FROM aula $where ORDER BY nombre_aula ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            if (!$stmt) {
                throw new Exception('Error en la consulta: ' . implode(', ', $conn->errorInfo()));
            }
            $aulas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode(['success' => true, 'data' => $aulas]);
            break;

        // 2. CREAR NUEVA AULA
        case 'crear':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede crear aulas');
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos o JSON inválido');
            
            if(empty($data['nombre_aula']) || empty($data['capacidad'])) {
                throw new Exception('El nombre y la capacidad son obligatorios');
            }
            
            $stmt = $conn->prepare("INSERT INTO aula (nombre_aula, capacidad, ubicacion, estado) VALUES (?, ?, ?, ?)");
            $estado = $data['estado'] ?? 'Activo';
            $resultado = $stmt->execute([
                $data['nombre_aula'],
                (int)$data['capacidad'],
                $data['ubicacion'] ?? '',
                $estado
            ]);
            
            if (!$resultado) {
                throw new Exception('Error al insertar aula: ' . implode(', ', $stmt->errorInfo()));
            }
            
            http_response_code(201);
            echo json_encode(['success' => true, 'message' => 'Aula registrada correctamente']);
            break;

        // 3. ACTUALIZAR AULA EXISTENTE
        case 'actualizar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede actualizar aulas');
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos o JSON inválido');
            if (empty($data['id_aula'])) throw new Exception('ID de aula requerido');

            $stmt = $conn->prepare("UPDATE aula SET nombre_aula=?, capacidad=?, ubicacion=?, estado=? WHERE id_aula=?");
            $resultado = $stmt->execute([
                $data['nombre_aula'], 
                $data['capacidad'], 
                $data['ubicacion'] ?? '', 
                $data['estado'], 
                $data['id_aula']
            ]);
            
            if (!$resultado) {
                throw new Exception('Error al actualizar aula: ' . implode(', ', $stmt->errorInfo()));
            }
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Aula actualizada con éxito']);
            break;

        // 4. ELIMINAR AULA
        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede eliminar aulas');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id_aula'] ?? null;
            
            if (!$id) throw new Exception('ID de aula no proporcionado');

            $stmt = $conn->prepare("DELETE FROM aula WHERE id_aula=?");
            $resultado = $stmt->execute([$id]);
            
            if (!$resultado) {
                throw new Exception('Error al eliminar aula: ' . implode(', ', $stmt->errorInfo()));
            }
            
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Registro de aula eliminado']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
    }
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    error_log('Error en aulas.php: ' . $e->getMessage());
}
?>
