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
        $stmt = $conn->prepare("SELECT u.id_usuario, u.nombres, u.apellidos, u.password_hash, u.id_rol, u.id_estado_usuario, r.nombre as rol_nombre
                                FROM usuario u
                                INNER JOIN rol r ON u.id_rol = r.id_rol
                                WHERE u.email = ?");
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
                $_SESSION['rol_nombre'] = $usuario['rol_nombre'];

                // Si es alumno o estudiante, buscamos su ID de estudiante para la sesión
                $rolNombreLogin = strtolower($usuario['rol_nombre'] ?? '');
                $esAlumnoLogin = str_contains($rolNombreLogin, 'alumno') || str_contains($rolNombreLogin, 'estudiante');
                if ($esAlumnoLogin) {
                    $stmtEst = $conn->prepare("SELECT id_estudiante FROM estudiante WHERE id_usuario = ?");
                    $stmtEst->execute([$usuario['id_usuario']]);
                    $est = $stmtEst->fetch(PDO::FETCH_ASSOC);
                    if (!$est) {
                        $stmtEst = $conn->prepare("SELECT id_estudiante FROM estudiante WHERE dni = (SELECT dni FROM usuario WHERE id_usuario = ?)");
                        $stmtEst->execute([$usuario['id_usuario']]);
                        $est = $stmtEst->fetch(PDO::FETCH_ASSOC);
                    }
                    if ($est) $_SESSION['id_estudiante'] = $est['id_estudiante'];
                }

                if (str_contains($rolNombreLogin, 'docente')) {
                    $stmtDoc = $conn->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
                    $stmtDoc->execute([$usuario['id_usuario']]);
                    $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
                    if ($doc) $_SESSION['id_docente'] = $doc['id_docente'];
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
