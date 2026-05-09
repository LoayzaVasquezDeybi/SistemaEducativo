<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER USUARIOS
        case 'obtener':
            $stmt = $conn->query("SELECT id_usuario, nombres, apellidos, dni, email, id_rol, id_estado_usuario FROM usuario ORDER BY nombres");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $usuarios]);
            break;

        // 2. CREAR USUARIO
        case 'crear':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                throw new Exception('No se recibieron datos válidos');
            }
            
            $sql = "INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            $pass_hash = password_hash($data['contrasena'], PASSWORD_BCRYPT);
            
            // Aquí es donde estaba el error de la línea 43
            $result = $stmt->execute([
                $data['nombre'], 
                $data['apellido'], 
                $data['dni'],
                $data['email'], 
                $pass_hash, 
                $data['rol'], 
                1
            ]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
            } else {
                throw new Exception('Error al insertar en la base de datos');
            }
            break;

       case 'actualizar':
    $data = json_decode(file_get_contents('php://input'), true);
    // Agregamos el DNI también en la actualización
    $stmt = $conn->prepare("UPDATE usuario SET nombres=?, apellidos=?, dni=?, email=?, id_rol=?, id_estado_usuario=? WHERE id_usuario=?");
    $stmt->execute([
        $data['nombre'], 
        $data['apellido'], 
        $data['dni'],
        $data['email'], 
        $data['rol'], 
        $data['estado'], 
        $data['id_usuario']
    ]);
    echo json_encode(['success' => true, 'message' => '¡Usuario actualizado con éxito!']);
    break;

        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_usuario'] ?? $_POST['id_usuario'];
            $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>