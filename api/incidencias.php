<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user = usuarioActual($conn);

try {
    switch ($action) {
        case 'obtener':
            $where = [];
            $params = [];
            if (esAlumno($user) && $user['id_estudiante']) {
                $where[] = 'i.id_estudiante = ?';
                $params[] = $user['id_estudiante'];
            } elseif (esDocente($user) && $user['id_docente']) {
                $where[] = 'i.id_docente = ?';
                $params[] = $user['id_docente'];
            }
            $sql = "SELECT i.id_incidencia, i.fecha, i.id_estudiante, i.id_tipo_incidencia, i.descripcion, i.accion_tomada, i.id_docente,
                           CONCAT(e.apellido, ', ', e.nombre) as estudiante,
                           ti.nombre as tipo,
                           CONCAT(u.nombres, ' ', u.apellidos) as docente
                    FROM incidencia i
                    INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
                    LEFT JOIN tipo_incidencia ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
                    LEFT JOIN docente d ON i.id_docente = d.id_docente
                    LEFT JOIN usuario u ON d.id_usuario = u.id_usuario
                    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                    ORDER BY i.fecha DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'readonly' => esAlumno($user)]);
            break;

        case 'combo':
            asegurarTipoIncidencia($conn);
            if (esAlumno($user)) {
                 $estudiantes = [];
            } elseif (esDocente($user) && $user['id_docente']) {
                $stmtEst = $conn->prepare("SELECT DISTINCT e.id_estudiante, e.nombre, e.apellido
                                           FROM estudiante e
                                           INNER JOIN horario h ON e.id_grado = h.id_grado AND e.id_seccion = h.id_seccion
                                           INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                                           WHERE cd.id_docente = ? AND (e.estado IS NULL OR LOWER(e.estado) = 'activo')
                                           ORDER BY e.apellido, e.nombre");
                $stmtEst->execute([$user['id_docente']]);
                $estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $estudiantes = $conn->query("SELECT id_estudiante, nombre, apellido FROM estudiante WHERE estado = 'activo' ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
            }
            $tipos = $conn->query("SELECT id_tipo_incidencia, nombre FROM tipo_incidencia ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            $docentes = $conn->query("SELECT d.id_docente, u.nombres, u.apellidos
                                      FROM docente d
                                      INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                      ORDER BY u.apellidos, u.nombres")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estudiantes' => $estudiantes, 'tipos' => $tipos, 'docentes' => $docentes]);
            break;

        case 'crear':
            if (esAlumno($user)) throw new Exception('Los alumnos no pueden registrar incidencias');
            $data = json_decode(file_get_contents('php://input'), true);
            validarIncidencia($data);
            if (esDocente($user)) {
                $data['id_docente'] = $user['id_docente'];
            }
            $stmt = $conn->prepare("INSERT INTO incidencia (fecha, id_estudiante, id_tipo_incidencia, descripcion, accion_tomada, id_docente) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['fecha'], $data['id_estudiante'], $data['id_tipo_incidencia'], $data['descripcion'], $data['accion_tomada'], $data['id_docente'] ?: null]);
            echo json_encode(['success' => true, 'message' => 'Incidencia registrada correctamente']);
            break;

        case 'actualizar':
            if (esAlumno($user)) throw new Exception('Los alumnos no pueden modificar incidencias');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_incidencia'])) throw new Exception('No se recibio la incidencia a actualizar');
            validarIncidencia($data);
            validarPropiedadIncidencia($conn, $data['id_incidencia'], $user);
            if (esDocente($user)) {
                $data['id_docente'] = $user['id_docente'];
            }
            $stmt = $conn->prepare("UPDATE incidencia SET fecha=?, id_estudiante=?, id_tipo_incidencia=?, descripcion=?, accion_tomada=?, id_docente=? WHERE id_incidencia=?");
            $stmt->execute([$data['fecha'], $data['id_estudiante'], $data['id_tipo_incidencia'], $data['descripcion'], $data['accion_tomada'], $data['id_docente'] ?: null, $data['id_incidencia']]);
            echo json_encode(['success' => true, 'message' => 'Incidencia actualizada correctamente']);
            break;

        case 'eliminar':
            if (esAlumno($user)) throw new Exception('Los alumnos no pueden eliminar incidencias');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_incidencia'])) throw new Exception('No se recibio la incidencia a eliminar');
            validarPropiedadIncidencia($conn, $data['id_incidencia'], $user);
            $stmt = $conn->prepare("DELETE FROM incidencia WHERE id_incidencia=?");
            $stmt->execute([$data['id_incidencia']]);
            echo json_encode(['success' => true, 'message' => 'Incidencia eliminada']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarIncidencia(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['fecha', 'id_estudiante', 'id_tipo_incidencia', 'descripcion'] as $campo) {
        if (empty($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
}

function validarPropiedadIncidencia(PDO $conn, int|string $idIncidencia, array $user): void
{
    if (esAdmin($user)) return;
    if (!esDocente($user)) throw new Exception('No tiene permiso para modificar esta incidencia');

    $stmt = $conn->prepare("SELECT id_docente FROM incidencia WHERE id_incidencia=?");
    $stmt->execute([$idIncidencia]);
    $idDocente = $stmt->fetchColumn();
    if (!$idDocente || (int) $idDocente !== (int) ($user['id_docente'] ?? 0)) {
        throw new Exception('Solo puede modificar incidencias registradas por usted');
    }
}

function asegurarTipoIncidencia(PDO $conn): void
{
    $total = (int) $conn->query("SELECT COUNT(*) FROM tipo_incidencia")->fetchColumn();
    if ($total > 0) return;
    $stmt = $conn->prepare("INSERT INTO tipo_incidencia (nombre) VALUES (?)");
    foreach (['Disciplinaria', 'Academica', 'Salud'] as $nombre) {
        $stmt->execute([$nombre]);
    }
}
?>