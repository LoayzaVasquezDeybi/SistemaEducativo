<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER ROLES
        case 'obtener':
            $stmt = $conn->query("SELECT id_rol, nombre, descripcion FROM rol ORDER BY id_rol");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $roles]);
            break;

        // 2. CREAR ROL
        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $stmt = $conn->prepare("INSERT INTO rol (nombre, descripcion) VALUES (?, ?)");
            $result = $stmt->execute([$data['nombre'], $data['descripcion'] ?? null]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Rol creado exitosamente']);
            } else {
                throw new Exception('Error al crear el rol');
            }
            break;

        // 3. ACTUALIZAR ROL
        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE rol SET nombre=?, descripcion=? WHERE id_rol=?");
            $stmt->execute([$data['nombre'], $data['descripcion'] ?? null, $data['id_rol']]);
            echo json_encode(['success' => true, 'message' => 'Rol actualizado con éxito']);
            break;

        // 4. ELIMINAR ROL
        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_rol'] ?? $_POST['id_rol'];
            
            // Validar que el rol no esté en uso por algún usuario
            $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE id_rol=?");
            $stmtCheck->execute([$id]);
            $enUso = $stmtCheck->fetchColumn();
            
            if ($enUso > 0) {
                throw new Exception("No se puede eliminar: hay $enUso usuario(s) usando este rol.");
            }
            
            $stmt = $conn->prepare("DELETE FROM rol WHERE id_rol=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Rol eliminado correctamente']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>