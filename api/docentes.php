<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER DOCENTES
        case 'obtener':
            // Hacemos un JOIN con la tabla usuario para traer los nombres y correos
            $query = "SELECT d.id_docente, d.codigo_docente, d.especialidad, d.id_usuario,
                             u.nombres as nombre, u.apellidos as apellido, u.dni, u.email,
                             IF(u.id_estado_usuario = 1, 'activo', 'inactivo') as estado
                      FROM docente d
                      INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                      ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->query($query);
            $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $docentes]);
            break;

        // 2. CREAR DOCENTE
        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            // Iniciamos una transacción
            $conn->beginTransaction();
            
            // 1. Crear el usuario asociado al docente (id_rol = 2 para Docente)
            $stmtUser = $conn->prepare("INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, 2, 1)");
            $pass_hash = password_hash($data['dni'], PASSWORD_BCRYPT); // Usamos su DNI como contraseña inicial por defecto
            
            $stmtUser->execute([
                $data['nombre'], 
                $data['apellido'], 
                $data['dni'],
                $data['email'],
                $pass_hash
            ]);
            
            $id_usuario = $conn->lastInsertId();
            
            // Generar código de docente automáticamente si no viene en el formulario
            $codigo_docente = $data['codigo_docente'] ?? 'DOC-' . date('Y') . '-' . str_pad($id_usuario, 4, '0', STR_PAD_LEFT);
            
            // 2. Crear el docente
            $stmtDoc = $conn->prepare("INSERT INTO docente (codigo_docente, especialidad, id_usuario) VALUES (?, ?, ?)");
            $stmtDoc->execute([
                $codigo_docente,
                $data['especialidad'] ?? null,
                $id_usuario
            ]);
            
            $conn->commit(); // Guardamos los cambios
            echo json_encode(['success' => true, 'message' => 'Docente creado exitosamente. La contraseña es su DNI.']);
            break;

        // 3. ACTUALIZAR DOCENTE
        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $conn->beginTransaction();
            
            // Actualizar tabla docente
            $stmtDoc = $conn->prepare("UPDATE docente SET especialidad=? WHERE id_docente=?");
            $stmtDoc->execute([
                $data['especialidad'] ?? null,
                $data['id_docente']
            ]);
            
            // Obtener el id_usuario asociado
            $stmtGet = $conn->prepare("SELECT id_usuario FROM docente WHERE id_docente=?");
            $stmtGet->execute([$data['id_docente']]);
            $doc = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                // Actualizar tabla usuario
                $estado = (isset($data['estado']) && $data['estado'] === 'activo') ? 1 : 2; 
                $stmtUser = $conn->prepare("UPDATE usuario SET nombres=?, apellidos=?, dni=?, email=?, id_estado_usuario=? WHERE id_usuario=?");
                $stmtUser->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $estado, $doc['id_usuario']]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Docente actualizado con éxito']);
            break;

        // 4. ELIMINAR DOCENTE
        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_docente'] ?? $_POST['id_docente'];
            
            $conn->beginTransaction();
            
            $stmtGet = $conn->prepare("SELECT id_usuario FROM docente WHERE id_docente=?");
            $stmtGet->execute([$id]);
            $doc = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            // 1. Eliminar asignaciones de cursos primero
            $stmtCd = $conn->prepare("DELETE FROM curso_docente WHERE id_docente=?");
            $stmtCd->execute([$id]);
            
            $stmt = $conn->prepare("DELETE FROM docente WHERE id_docente=?");
            $stmt->execute([$id]);
            
            if ($doc) {
                $stmtUser = $conn->prepare("DELETE FROM usuario WHERE id_usuario=?");
                $stmtUser->execute([$doc['id_usuario']]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Docente y su usuario han sido eliminados']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack(); // Deshacer cambios si algo sale mal
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>