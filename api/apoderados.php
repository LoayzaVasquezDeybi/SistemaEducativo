<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/conexion.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'obtener':
            $query = "SELECT a.id_apoderado, a.id_usuario, u.nombres as nombre, u.apellidos as apellido,
                             u.dni, u.email, u.id_estado_usuario, eu.nombre as estado,
                             MIN(ea.id_estudiante) as id_estudiante,
                             MIN(ea.parentesco) as parentesco,
                             GROUP_CONCAT(CONCAT(e.nombre, ' ', e.apellido, ' (', ea.parentesco, ')') SEPARATOR ', ') as estudiantes
                      FROM apoderado a
                      INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                      INNER JOIN estado_usuario eu ON u.id_estado_usuario = eu.id_estado_usuario
                      LEFT JOIN estudiante_apoderado ea ON a.id_apoderado = ea.id_apoderado
                      LEFT JOIN estudiante e ON ea.id_estudiante = e.id_estudiante
                      GROUP BY a.id_apoderado, a.id_usuario, u.nombres, u.apellidos, u.dni, u.email, u.id_estado_usuario, eu.nombre
                      ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->query($query);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_estudiantes':
            $stmt = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE estado = 'activo' ORDER BY apellido, nombre");
            echo json_encode(['success' => true, 'estudiantes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_usuarios':
            $stmt = $conn->query("SELECT id_usuario, CONCAT(nombres, ' ', apellidos) as nombre FROM usuario ORDER BY apellidos, nombres");
            echo json_encode(['success' => true, 'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_estados':
            $stmt = $conn->query("SELECT id_estado_usuario, nombre FROM estado_usuario ORDER BY nombre");
            echo json_encode(['success' => true, 'estados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('No se recibieron datos validos');
            }
            validarApoderado($data);

            $conn->beginTransaction();
            validarUsuarioUnico($conn, $data['dni'], $data['email']);

            $idRol = obtenerRolApoderado($conn);
            $idEstado = $data['id_estado_usuario'] ?? 1;
            $passHash = password_hash($data['dni'], PASSWORD_BCRYPT);

            $stmtUser = $conn->prepare("INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtUser->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $passHash, $idRol, $idEstado]);
            $idUsuario = $conn->lastInsertId();

            $stmtApo = $conn->prepare("INSERT INTO apoderado (id_usuario) VALUES (?)");
            $stmtApo->execute([$idUsuario]);
            $idApoderado = $conn->lastInsertId();

            guardarVinculoEstudiante($conn, $idApoderado, $data);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apoderado creado y vinculado exitosamente. Su contrasena inicial es su DNI.']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id_apoderado'])) {
                throw new Exception('No se recibieron datos validos');
            }
            validarApoderado($data);

            $conn->beginTransaction();

            $stmtGet = $conn->prepare("SELECT id_usuario FROM apoderado WHERE id_apoderado = ?");
            $stmtGet->execute([$data['id_apoderado']]);
            $apo = $stmtGet->fetch(PDO::FETCH_ASSOC);
            if (!$apo) {
                throw new Exception('No se encontro el apoderado');
            }

            validarUsuarioUnico($conn, $data['dni'], $data['email'], (int) $apo['id_usuario']);

            $idEstado = $data['id_estado_usuario'] ?? 1;
            $stmtUser = $conn->prepare("UPDATE usuario SET nombres = ?, apellidos = ?, dni = ?, email = ?, id_estado_usuario = ? WHERE id_usuario = ?");
            $stmtUser->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $idEstado, $apo['id_usuario']]);

            $stmtRel = $conn->prepare("DELETE FROM estudiante_apoderado WHERE id_apoderado = ?");
            $stmtRel->execute([$data['id_apoderado']]);
            guardarVinculoEstudiante($conn, $data['id_apoderado'], $data);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apoderado actualizado correctamente']);
            break;

        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_apoderado'] ?? $_POST['id_apoderado'] ?? null;
            if (!$id) {
                throw new Exception('No se recibio el apoderado a eliminar');
            }

            $conn->beginTransaction();

            $stmtGet = $conn->prepare("SELECT id_usuario FROM apoderado WHERE id_apoderado = ?");
            $stmtGet->execute([$id]);
            $apo = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $stmtRel = $conn->prepare("DELETE FROM estudiante_apoderado WHERE id_apoderado = ?");
            $stmtRel->execute([$id]);

            $stmt = $conn->prepare("DELETE FROM apoderado WHERE id_apoderado = ?");
            $stmt->execute([$id]);

            if ($apo) {
                $stmtUser = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
                $stmtUser->execute([$apo['id_usuario']]);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Apoderado y su usuario han sido eliminados']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarApoderado(array $data): void
{
    foreach (['nombre', 'apellido', 'dni', 'email'] as $campo) {
        if (empty(trim($data[$campo] ?? ''))) {
            throw new Exception("El campo $campo es obligatorio");
        }
    }

    if (!preg_match('/^[0-9]{8}$/', $data['dni'])) {
        throw new Exception('El DNI debe tener 8 digitos');
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('El email no tiene un formato valido');
    }

    if (!empty($data['id_estudiante']) && empty($data['parentesco'])) {
        throw new Exception('Debe indicar el parentesco del estudiante seleccionado');
    }
}

function validarUsuarioUnico(PDO $conn, string $dni, string $email, ?int $idUsuarioActual = null): void
{
    $sql = "SELECT COUNT(*) FROM usuario WHERE (dni = ? OR email = ?)";
    $params = [$dni, $email];

    if ($idUsuarioActual) {
        $sql .= " AND id_usuario <> ?";
        $params[] = $idUsuarioActual;
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Ya existe un usuario con ese DNI o email');
    }
}

function obtenerRolApoderado(PDO $conn): int
{
    $stmtRol = $conn->query("SELECT id_rol FROM rol WHERE nombre LIKE '%Apoderado%' LIMIT 1");
    $idRol = $stmtRol->fetchColumn();
    if ($idRol) {
        return (int) $idRol;
    }

    $conn->exec("INSERT IGNORE INTO rol (id_rol, nombre, descripcion) VALUES (4, 'Apoderado', 'Padre, madre o tutor del estudiante')");
    $stmtRol = $conn->query("SELECT id_rol FROM rol WHERE nombre LIKE '%Apoderado%' LIMIT 1");
    return (int) ($stmtRol->fetchColumn() ?: 4);
}

function guardarVinculoEstudiante(PDO $conn, int|string $idApoderado, array $data): void
{
    if (empty($data['id_estudiante']) || empty($data['parentesco'])) {
        return;
    }

    $stmtEa = $conn->prepare("INSERT INTO estudiante_apoderado (id_estudiante, id_apoderado, parentesco) VALUES (?, ?, ?)");
    $stmtEa->execute([$data['id_estudiante'], $idApoderado, $data['parentesco']]);
}
?>
