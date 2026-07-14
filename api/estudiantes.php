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
            if (esDocente($user) && $user['id_docente']) {
                $where[] = 'e.id_estudiante IN (
                                SELECT DISTINCT e_inner.id_estudiante
                                FROM horario h
                                INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                                INNER JOIN estudiante e_inner ON h.id_grado = e_inner.id_grado AND h.id_seccion = e_inner.id_seccion
                                WHERE cd.id_docente = ?
                            )';
                $params[] = $user['id_docente'];
            }
            $sql = "SELECT e.id_estudiante, e.codigo_estudiante as codigo, e.nombre, e.apellido, e.dni, e.fecha_nacimiento, e.id_grado, e.id_seccion, e.estado,
                           g.nombre as grado, s.nombre as seccion
                    FROM estudiante e
                    LEFT JOIN grado g ON e.id_grado = g.id_grado
                    LEFT JOIN seccion s ON e.id_seccion = s.id_seccion
                    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                    ORDER BY e.apellido, e.nombre";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'readonly' => esAlumno($user)]);
            break;

        case 'combo':
            $grados = $conn->query("SELECT id_grado, nombre FROM grado ORDER BY id_grado")->fetchAll(PDO::FETCH_ASSOC);
            $secciones = $conn->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'grados' => $grados, 'secciones' => $secciones]);
            break;

        case 'crear':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede registrar estudiantes');
            $data = json_decode(file_get_contents('php://input'), true);
            validarEstudiante($data);
            $stmt = $conn->prepare("INSERT INTO estudiante (codigo_estudiante, nombre, apellido, dni, fecha_nacimiento, id_grado, id_seccion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['codigo'], $data['nombre'], $data['apellido'], $data['dni'], $data['fecha_nacimiento'], $data['id_grado'], $data['id_seccion'], $data['estado'] ?: 'preinscrito']);
            echo json_encode(['success' => true, 'message' => 'Estudiante registrado correctamente']);
            break;

        case 'actualizar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede actualizar estudiantes');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_estudiante'])) throw new Exception('No se recibio el estudiante a actualizar');
            validarEstudiante($data);
            $stmt = $conn->prepare("UPDATE estudiante SET codigo_estudiante=?, nombre=?, apellido=?, dni=?, fecha_nacimiento=?, id_grado=?, id_seccion=?, estado=? WHERE id_estudiante=?");
            $stmt->execute([$data['codigo'], $data['nombre'], $data['apellido'], $data['dni'], $data['fecha_nacimiento'], $data['id_grado'], $data['id_seccion'], $data['estado'], $data['id_estudiante']]);
            echo json_encode(['success' => true, 'message' => 'Estudiante actualizado correctamente']);
            break;

        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede eliminar estudiantes');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_estudiante'])) throw new Exception('No se recibio el estudiante a eliminar');

            $conn->beginTransaction();
            try {
                $idEstudiante = $data['id_estudiante'];
                // Obtener matriculas asociadas para revertir vacantes si es necesario
                $stmtMatriculas = $conn->prepare("SELECT id_matricula, id_vacante FROM matricula WHERE id_estudiante = ?");
                $stmtMatriculas->execute([$idEstudiante]);
                $matriculas = $stmtMatriculas->fetchAll(PDO::FETCH_ASSOC);

                foreach ($matriculas as $matricula) {
                    // Revertir vacante si la matricula estaba activa
                    if (matriculaPagada($conn, $matricula['id_matricula'])) {
                        ajustarVacanteDisponible($conn, $matricula['id_vacante'], 1);
                    }
                    // Eliminar pagos y otros registros asociados a la matricula
                    $conn->prepare("DELETE FROM pago WHERE id_matricula = ?")->execute([$matricula['id_matricula']]);
                    $conn->prepare("DELETE FROM matricula_curso WHERE id_matricula = ?")->execute([$matricula['id_matricula']]);
                }

                // Eliminar otras dependencias
                $conn->prepare("DELETE FROM matricula WHERE id_estudiante = ?")->execute([$idEstudiante]);
                $conn->prepare("DELETE FROM nota WHERE id_estudiante = ?")->execute([$idEstudiante]);
                $conn->prepare("DELETE FROM asistencia WHERE id_estudiante = ?")->execute([$idEstudiante]);
                $conn->prepare("DELETE FROM incidencia WHERE id_estudiante = ?")->execute([$idEstudiante]);
                $conn->prepare("DELETE FROM apoderado_estudiante WHERE id_estudiante = ?")->execute([$idEstudiante]);

                // Finalmente, eliminar al estudiante
                $stmt = $conn->prepare("DELETE FROM estudiante WHERE id_estudiante=?");
                $stmt->execute([$idEstudiante]);

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Estudiante y todos sus registros asociados han sido eliminados']);
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarEstudiante(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['codigo', 'nombre', 'apellido', 'dni', 'fecha_nacimiento', 'id_grado', 'id_seccion'] as $campo) {
        if (empty($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
}

// Estas funciones se pueden mover a un archivo de helpers si se repiten en otros modulos
function matriculaPagada(PDO $conn, int|string $idMatricula): bool { $stmt = $conn->prepare("SELECT COUNT(*) FROM pago p INNER JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago WHERE p.id_matricula=? AND LOWER(ep.nombre)='pagado' AND LOWER(p.concepto) LIKE '%matric%'"); $stmt->execute([$idMatricula]); return (int) $stmt->fetchColumn() > 0; }
function ajustarVacanteDisponible(PDO $conn, int|string $idVacante, int $delta): void { $stmt = $conn->prepare("UPDATE vacante SET vacantes_disponibles = vacantes_disponibles + ? WHERE id_vacante = ?"); $stmt->execute([$delta, $idVacante]); }
?>