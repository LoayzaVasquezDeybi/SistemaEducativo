<?php
session_start();
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0);

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['email']) || empty($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Por favor, ingrese correo y contraseña.']);
        exit;
    }

    $email = $data['email'];
    $password = $data['password'];

    // Buscar al usuario por su correo
    $stmt = $conn->prepare("SELECT id_usuario, nombres, apellidos, password_hash, id_rol, id_estado_usuario FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        // Verificar si el usuario está inactivo
        if ($usuario['id_estado_usuario'] != 1) {
            echo json_encode(['success' => false, 'message' => 'Su cuenta se encuentra inactiva. Contacte al administrador.']);
            exit;
        }

        // Verificar la contraseña encriptada (BCRYPT)
        if (password_verify($password, $usuario['password_hash'])) {
            // Contraseña correcta: Guardamos sus datos en variables de sesión
            $_SESSION['usuario_id'] = $usuario['id_usuario'];
            $_SESSION['nombres'] = $usuario['nombres'];
            $_SESSION['apellidos'] = $usuario['apellidos'];
            $_SESSION['rol'] = $usuario['id_rol'];

            // Si es Alumno (Rol 3), buscamos su ID de estudiante para la sesión
            
            if ($usuario['id_rol'] == 3) {
                $stmtEst = $conn->prepare("SELECT id_estudiante FROM estudiante WHERE id_usuario = ?");
                $stmtEst->execute([$usuario['id_usuario']]);
                $est = $stmtEst->fetch(PDO::FETCH_ASSOC);
                if ($est) $_SESSION['id_estudiante'] = $est['id_estudiante'];
            }

            echo json_encode(['success' => true, 'message' => 'Login exitoso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ingresado no está registrado.']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>
