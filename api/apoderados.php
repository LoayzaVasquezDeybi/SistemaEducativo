<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER APODERADOS
        case 'obtener':
            $query = "SELECT a.id_apoderado, a.id_usuario, u.nombres as nombre, u.apellidos as apellido, u.dni, u.email, 
                             IF(u.id_estado_usuario = 1, 'activo', 'inactivo') as estado,
                             GROUP_CONCAT(CONCAT(e.nombre, ' ', e.apellido, ' (', ea.parentesco, ')') SEPARATOR ', ') as estudiantes
                      FROM apoderado a
                      INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                      LEFT JOIN estudiante_apoderado ea ON a.id_apoderado = ea.id_apoderado
                      LEFT JOIN estudiante e ON ea.id_estudiante = e.id_estudiante
                      GROUP BY a.id_apoderado, a.id_usuario, u.nombres, u.apellidos, u.dni, u.email, u.id_estado_usuario
                      ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->query($query);
            $apoderados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $apoderados]);
            break;

        // 2. OBTENER ALUMNOS PARA ASIGNACIÓN
        case 'combo_estudiantes':
            $stmt = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE estado = 'activo' ORDER BY apellido, nombre");
            $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estudiantes' => $estudiantes]);
            break;

        // 3. CREAR APODERADO Y VINCULARLO
        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $conn->beginTransaction();
            
            // Buscar si existe el rol Apoderado, sino crearlo (asumimos id_rol=4)
            $stmtRol = $conn->query("SELECT id_rol FROM rol WHERE nombre LIKE '%Apoderado%' LIMIT 1");
            $rol = $stmtRol->fetch(PDO::FETCH_ASSOC);
            $id_rol = $rol ? $rol['id_rol'] : 4; 
            if (!$rol) $conn->exec("INSERT IGNORE INTO rol (id_rol, nombre, descripcion) VALUES (4, 'Apoderado', 'Padre, madre o tutor del estudiante')");
            
            // 1. Crear Usuario
            $stmtUser = $conn->prepare("INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, ?, 1)");
            $pass_hash = password_hash($data['dni'], PASSWORD_BCRYPT); // DNI como clave por defecto
            $stmtUser->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $pass_hash, $id_rol]);
            $id_usuario = $conn->lastInsertId();
            
            // 2. Crear Apoderado
            $stmtApo = $conn->prepare("INSERT INTO apoderado (id_usuario) VALUES (?)");
            $stmtApo->execute([$id_usuario]);
            $id_apoderado = $conn->lastInsertId();

            // 3. Vincular con estudiante si se seleccionó uno
            if (!empty($data['id_estudiante']) && !empty($data['parentesco'])) {
                $stmtEa = $conn->prepare("INSERT INTO estudiante_apoderado (id_estudiante, id_apoderado, parentesco) VALUES (?, ?, ?)");
                $stmtEa->execute([$data['id_estudiante'], $id_apoderado, $data['parentesco']]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apoderado creado y vinculado exitosamente. Su contraseña es su DNI.']);
            break;

        // 4. ELIMINAR APODERADO
        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_apoderado'] ?? $_POST['id_apoderado'];
            
            $conn->beginTransaction();
            $stmtGet = $conn->prepare("SELECT id_usuario FROM apoderado WHERE id_apoderado=?");
            $stmtGet->execute([$id]);
            $apo = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $conn->prepare("DELETE FROM apoderado WHERE id_apoderado=?");
            $stmt->execute([$id]);
            
            if ($apo) {
                $stmtUser = $conn->prepare("DELETE FROM usuario WHERE id_usuario=?");
                $stmtUser->execute([$apo['id_usuario']]);
            }
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apoderado y su usuario han sido eliminados']);
            break;
    }
} catch(Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>