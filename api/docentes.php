<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config/conexion.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'obtener':
            $query = "SELECT d.id_docente, d.codigo_docente, d.especialidad, d.id_usuario,
                             u.nombres as nombre, u.apellidos as apellido, u.dni, u.email,
                             IF(u.id_estado_usuario = 1, 'activo', 'inactivo') as estado,
                             MIN(cd.id_curso) as id_curso,
                             GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', ') as cursos_asignados
                      FROM docente d
                      INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                      LEFT JOIN curso_docente cd ON d.id_docente = cd.id_docente
                      LEFT JOIN curso c ON cd.id_curso = c.id_curso
                      GROUP BY d.id_docente, d.codigo_docente, d.especialidad, d.id_usuario,
                               u.nombres, u.apellidos, u.dni, u.email, u.id_estado_usuario
                      ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->query($query);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_cursos':
            $stmt = $conn->query("SELECT id_curso, nombre FROM curso WHERE estado = 'activo' ORDER BY nombre");
            echo json_encode(['success' => true, 'cursos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('No se recibieron datos validos');
            }
            validarDocente($data);

            $conn->beginTransaction();
            validarUsuarioUnico($conn, $data['dni'], $data['email']);

            $stmtUser = $conn->prepare("INSERT INTO usuario (nombres, apellidos, dni, email, password_hash, id_rol, id_estado_usuario) VALUES (?, ?, ?, ?, ?, 2, 1)");
            $stmtUser->execute([
                $data['nombre'],
                $data['apellido'],
                $data['dni'],
                $data['email'],
                password_hash($data['dni'], PASSWORD_BCRYPT),
            ]);

            $idUsuario = $conn->lastInsertId();
            $codigoDocente = $data['codigo_docente'] ?? 'DOC-' . date('Y') . '-' . str_pad($idUsuario, 4, '0', STR_PAD_LEFT);

            $stmtDoc = $conn->prepare("INSERT INTO docente (codigo_docente, especialidad, id_usuario) VALUES (?, ?, ?)");
            $stmtDoc->execute([$codigoDocente, $data['especialidad'] ?? null, $idUsuario]);
            $idDocente = $conn->lastInsertId();

            guardarCursoDocente($conn, $idDocente, $data['id_curso'] ?? null);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Docente creado exitosamente. La contrasena inicial es su DNI.']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id_docente'])) {
                throw new Exception('No se recibieron datos validos');
            }
            validarDocente($data);

            $conn->beginTransaction();

            $stmtGet = $conn->prepare("SELECT id_usuario FROM docente WHERE id_docente = ?");
            $stmtGet->execute([$data['id_docente']]);
            $doc = $stmtGet->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('No se encontro el docente');
            }

            validarUsuarioUnico($conn, $data['dni'], $data['email'], (int) $doc['id_usuario']);

            $stmtDoc = $conn->prepare("UPDATE docente SET especialidad = ? WHERE id_docente = ?");
            $stmtDoc->execute([$data['especialidad'] ?? null, $data['id_docente']]);

            $estado = (isset($data['estado']) && $data['estado'] === 'inactivo') ? 2 : 1;
            $stmtUser = $conn->prepare("UPDATE usuario SET nombres = ?, apellidos = ?, dni = ?, email = ?, id_estado_usuario = ? WHERE id_usuario = ?");
            $stmtUser->execute([$data['nombre'], $data['apellido'], $data['dni'], $data['email'], $estado, $doc['id_usuario']]);

            $stmtDelCursos = $conn->prepare("DELETE FROM curso_docente WHERE id_docente = ?");
            $stmtDelCursos->execute([$data['id_docente']]);
            guardarCursoDocente($conn, $data['id_docente'], $data['id_curso'] ?? null);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Docente actualizado con exito']);
            break;

        case 'eliminar':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id_docente'] ?? $_POST['id_docente'] ?? null;
            if (!$id) {
                throw new Exception('No se recibio el docente a eliminar');
            }

            $conn->beginTransaction();

            $stmtGet = $conn->prepare("SELECT id_usuario FROM docente WHERE id_docente = ?");
            $stmtGet->execute([$id]);
            $doc = $stmtGet->fetch(PDO::FETCH_ASSOC);

            $stmtCd = $conn->prepare("DELETE FROM curso_docente WHERE id_docente = ?");
            $stmtCd->execute([$id]);

            $stmt = $conn->prepare("DELETE FROM docente WHERE id_docente = ?");
            $stmt->execute([$id]);

            if ($doc) {
                $stmtUser = $conn->prepare("DELETE FROM usuario WHERE id_usuario = ?");
                $stmtUser->execute([$doc['id_usuario']]);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Docente y su usuario han sido eliminados']);
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

function validarDocente(array $data): void
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

function guardarCursoDocente(PDO $conn, int|string $idDocente, int|string|null $idCurso): void
{
    if (empty($idCurso)) {
        return;
    }

    $idPeriodo = obtenerPeriodoActual($conn);
    $stmt = $conn->prepare("INSERT INTO curso_docente (id_curso, id_docente, id_periodo) VALUES (?, ?, ?)");
    $stmt->execute([$idCurso, $idDocente, $idPeriodo]);
}

function obtenerPeriodoActual(PDO $conn): int
{
    $stmt = $conn->query("SELECT id_periodo FROM periodo_academico WHERE estado = 'activo' ORDER BY id_periodo DESC LIMIT 1");
    $idPeriodo = $stmt->fetchColumn();
    if ($idPeriodo) {
        return (int) $idPeriodo;
    }

    $stmt = $conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1");
    $idPeriodo = $stmt->fetchColumn();
    if ($idPeriodo) {
        return (int) $idPeriodo;
    }

    $anio = date('Y');
    $stmtInsert = $conn->prepare("INSERT INTO periodo_academico (anio, nombre, estado) VALUES (?, ?, 'activo')");
    $stmtInsert->execute([$anio, "Periodo $anio"]);
    return (int) $conn->lastInsertId();
}
?>
