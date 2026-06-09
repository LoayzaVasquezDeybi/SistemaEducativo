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
            $stmt = $conn->query("SELECT u.id_usuario, u.nombres, u.apellidos, u.dni, u.email, u.id_rol, u.id_estado_usuario, r.nombre as nombre_rol FROM usuario u LEFT JOIN rol r ON u.id_rol = r.id_rol ORDER BY u.nombres");
            $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $usuarios]);
            break;

        // NUEVO: OBTENER COMBOS (Roles)
        case 'combo':
            $stmt = $conn->query("SELECT id_rol, nombre FROM rol ORDER BY nombre");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'roles' => $roles]);
            break;

        // 2. CREAR USUARIO
        case 'crear':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                throw new Exception('No se recibieron datos válidos');
            }
            
            // Iniciamos transacción para guardar en múltiples tablas de forma segura
            $conn->beginTransaction();

            $sql = "INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            $pass_hash = password_hash($data['contrasena'], PASSWORD_BCRYPT);
            
            $stmt->execute([
                $data['nombre'], 
                $data['apellido'], 
                $data['dni'],
                $data['email'], 
                $pass_hash, 
                $data['rol'], 
                1
            ]);
            
            $id_usuario = $conn->lastInsertId();

            // Verificar si el rol es Docente o Apoderado para insertarlo en su respectiva tabla
            $stmtRol = $conn->prepare("SELECT nombre FROM rol WHERE id_rol = ?");
            $stmtRol->execute([$data['rol']]);
            $nombreRol = strtolower($stmtRol->fetchColumn());

            if (strpos($nombreRol, 'docente') !== false) {
                $codigo_docente = 'DOC-' . date('Y') . '-' . str_pad($id_usuario, 4, '0', STR_PAD_LEFT);
                $stmtDoc = $conn->prepare("INSERT INTO docente (codigo_docente, id_usuario) VALUES (?, ?)");
                $stmtDoc->execute([$codigo_docente, $id_usuario]);
            } elseif (strpos($nombreRol, 'apoderado') !== false) {
                $stmtApo = $conn->prepare("INSERT INTO apoderado (id_usuario) VALUES (?)");
                $stmtApo->execute([$id_usuario]);
            } elseif (strpos($nombreRol, 'alumno') !== false || strpos($nombreRol, 'estudiante') !== false) {
                $codigo_est = 'EST-' . date('Y') . '-' . str_pad($id_usuario, 4, '0', STR_PAD_LEFT);
                $stmtEst = $conn->prepare("INSERT INTO estudiante (nombre, apellido, dni, email, codigo_estudiante, id_usuario, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");
                $stmtEst->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $codigo_est, $id_usuario]);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Usuario creado y sincronizado exitosamente']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $conn->beginTransaction();

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

            // Revisar si le cambiaron el rol a Docente o Apoderado y si no existe en la tabla, agregarlo
            $stmtRol = $conn->prepare("SELECT nombre FROM rol WHERE id_rol = ?");
            $stmtRol->execute([$data['rol']]);
            $nombreRol = strtolower($stmtRol->fetchColumn());

            if (strpos($nombreRol, 'docente') !== false) {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM docente WHERE id_usuario = ?");
                $stmtCheck->execute([$data['id_usuario']]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $codigo_docente = 'DOC-' . date('Y') . '-' . str_pad($data['id_usuario'], 4, '0', STR_PAD_LEFT);
                    $stmtDoc = $conn->prepare("INSERT INTO docente (codigo_docente, id_usuario) VALUES (?, ?)");
                    $stmtDoc->execute([$codigo_docente, $data['id_usuario']]);
                }
            } elseif (strpos($nombreRol, 'apoderado') !== false) {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM apoderado WHERE id_usuario = ?");
                $stmtCheck->execute([$data['id_usuario']]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $stmtApo = $conn->prepare("INSERT INTO apoderado (id_usuario) VALUES (?)");
                    $stmtApo->execute([$data['id_usuario']]);
                }
            } elseif (strpos($nombreRol, 'alumno') !== false || strpos($nombreRol, 'estudiante') !== false) {
                $stmtCheck = $conn->prepare("SELECT COUNT(*) FROM estudiante WHERE id_usuario = ?");
                $stmtCheck->execute([$data['id_usuario']]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $codigo_est = 'EST-' . date('Y') . '-' . str_pad($data['id_usuario'], 4, '0', STR_PAD_LEFT);
                    $stmtEst = $conn->prepare("INSERT INTO estudiante (nombre, apellido, dni, email, codigo_estudiante, id_usuario, estado) VALUES (?, ?, ?, ?, ?, ?, 'activo')");
                    $stmtEst->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $codigo_est, $data['id_usuario']]);
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => '¡Usuario actualizado y sincronizado con éxito!']);
            break;

        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_usuario'] ?? $_POST['id_usuario'];
            
            $conn->beginTransaction();
            
            // Eliminar de las tablas dependientes primero para evitar errores de Foreign Keys
            
            // Buscar si es docente y liberar sus cursos
            $stmtGetDoc = $conn->prepare("SELECT id_docente FROM docente WHERE id_usuario=?");
            $stmtGetDoc->execute([$id]);
            $doc = $stmtGetDoc->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                $stmtCd = $conn->prepare("DELETE FROM curso_docente WHERE id_docente=?");
                $stmtCd->execute([$doc['id_docente']]);
            }
            
            $stmtDoc = $conn->prepare("DELETE FROM docente WHERE id_usuario=?");
            $stmtDoc->execute([$id]);
            
            $stmtApo = $conn->prepare("DELETE FROM apoderado WHERE id_usuario=?");
            $stmtApo->execute([$id]);
            
            $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario=?");
            $stmt->execute([$id]);
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Usuario y sus perfiles enlazados han sido eliminados']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>