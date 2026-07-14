<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'obtener':
            asegurarEstadosMatricula($conn);
            $query = "SELECT m.id_matricula, m.id_estudiante, m.id_vacante, m.fecha_matricula, m.id_estado_matricula,
                             CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni,
                             e.estado as estado_estudiante,
                             em.nombre as estado_matricula,
                             EXISTS (
                                SELECT 1 FROM pago p
                                INNER JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                                WHERE p.id_matricula = m.id_matricula
                                  AND LOWER(ep.nombre) = 'pagado'
                                  AND LOWER(p.concepto) LIKE '%matric%'
                             ) as pago_matricula_realizado
                      FROM matricula m
                      INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                      LEFT JOIN estado_matricula em ON m.id_estado_matricula = em.id_estado_matricula
                      ORDER BY m.fecha_matricula DESC";
            $stmt = $conn->query($query);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_estudiantes':
            $stmt = $conn->query("SELECT id_estudiante, nombre, apellido, dni, estado
                                  FROM estudiante
                                  WHERE estado IN ('preinscrito', 'pendiente_pago', 'activo')
                                  ORDER BY apellido, nombre");
            echo json_encode(['success' => true, 'estudiantes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_vacantes':
            $stmt = $conn->query("SELECT id_vacante, total_vacantes, vacantes_disponibles FROM vacante ORDER BY id_vacante DESC");
            echo json_encode(['success' => true, 'vacantes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_estados':
            asegurarEstadosMatricula($conn);
            $stmt = $conn->query("SELECT * FROM estado_matricula ORDER BY id_estado_matricula");
            echo json_encode(['success' => true, 'estados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('No se recibieron datos validos');
            }
            validarMatricula($data);

            $conn->beginTransaction();
            $idPendiente = obtenerEstadoMatricula($conn, 'Pendiente de pago');

            $stmt = $conn->prepare("INSERT INTO matricula (id_estudiante, id_vacante, fecha_matricula, id_estado_matricula) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data['id_estudiante'], $data['id_vacante'], $data['fecha_matricula'], $idPendiente]);

            $stmtEst = $conn->prepare("UPDATE estudiante SET estado='pendiente_pago' WHERE id_estudiante=? AND estado <> 'activo'");
            $stmtEst->execute([$data['id_estudiante']]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Solicitud de matricula creada. Procese el pago de matricula para activarla y habilitar la ficha.']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id_matricula'])) {
                throw new Exception('No se recibieron datos validos');
            }
            validarMatricula($data);

            $conn->beginTransaction();

            $stmtActual = $conn->prepare("SELECT id_vacante FROM matricula WHERE id_matricula = ?");
            $stmtActual->execute([$data['id_matricula']]);
            $vacanteActual = $stmtActual->fetchColumn();
            if (!$vacanteActual) {
                throw new Exception('No se encontro la matricula');
            }

            $estaActiva = matriculaPagada($conn, $data['id_matricula']);
            if ($estaActiva && (int) $vacanteActual !== (int) $data['id_vacante']) {
                ajustarVacanteDisponible($conn, $vacanteActual, 1);
                ajustarVacanteDisponible($conn, $data['id_vacante'], -1);
            }

            $stmt = $conn->prepare("UPDATE matricula SET id_estudiante = ?, id_vacante = ?, fecha_matricula = ?, id_estado_matricula = ? WHERE id_matricula = ?");
            $stmt->execute([$data['id_estudiante'], $data['id_vacante'], $data['fecha_matricula'], $data['id_estado_matricula'], $data['id_matricula']]);

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Matricula actualizada con exito']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || empty($data['id_matricula'])) {
                throw new Exception('No se recibio la matricula a eliminar');
            }

            $conn->beginTransaction();

            $stmtActual = $conn->prepare("SELECT id_vacante FROM matricula WHERE id_matricula = ?");
            $stmtActual->execute([$data['id_matricula']]);
            $vacanteActual = $stmtActual->fetchColumn();

            $stmtPagos = $conn->prepare("DELETE FROM pago WHERE id_matricula = ?");
            $stmtPagos->execute([$data['id_matricula']]);

            $stmtCursos = $conn->prepare("DELETE FROM matricula_curso WHERE id_matricula = ?");
            $stmtCursos->execute([$data['id_matricula']]);

            $stmt = $conn->prepare("DELETE FROM matricula WHERE id_matricula = ?");
            $stmt->execute([$data['id_matricula']]);

            if ($vacanteActual && matriculaPagada($conn, $data['id_matricula'])) {
                ajustarVacanteDisponible($conn, $vacanteActual, 1);
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Registro de matricula eliminado']);
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

function validarMatricula(array $data): void
{
    foreach (['id_estudiante', 'id_vacante', 'fecha_matricula'] as $campo) {
        if (empty($data[$campo])) {
            throw new Exception("El campo $campo es obligatorio");
        }
    }
}

function ajustarVacanteDisponible(PDO $conn, int|string $idVacante, int $delta): void
{
    $stmt = $conn->prepare("SELECT vacantes_disponibles, total_vacantes FROM vacante WHERE id_vacante = ? FOR UPDATE");
    $stmt->execute([$idVacante]);
    $vacante = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vacante) {
        throw new Exception('La vacante seleccionada no existe');
    }

    $nuevoDisponible = (int) $vacante['vacantes_disponibles'] + $delta;
    if ($nuevoDisponible < 0) {
        throw new Exception('No hay vacantes disponibles para esta seleccion');
    }
    if ($nuevoDisponible > (int) $vacante['total_vacantes']) {
        $nuevoDisponible = (int) $vacante['total_vacantes'];
    }

    $stmtUpdate = $conn->prepare("UPDATE vacante SET vacantes_disponibles = ? WHERE id_vacante = ?");
    $stmtUpdate->execute([$nuevoDisponible, $idVacante]);
}

function asegurarEstadosMatricula(PDO $conn): void
{
    foreach (['Pendiente de pago', 'Activo'] as $nombre) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM estado_matricula WHERE LOWER(nombre)=LOWER(?)");
        $stmt->execute([$nombre]);
        if ((int) $stmt->fetchColumn() === 0) {
            $insert = $conn->prepare("INSERT INTO estado_matricula (nombre) VALUES (?)");
            $insert->execute([$nombre]);
        }
    }
}

function obtenerEstadoMatricula(PDO $conn, string $nombre): int
{
    asegurarEstadosMatricula($conn);
    $stmt = $conn->prepare("SELECT id_estado_matricula FROM estado_matricula WHERE LOWER(nombre)=LOWER(?) LIMIT 1");
    $stmt->execute([$nombre]);
    return (int) $stmt->fetchColumn();
}

function matriculaPagada(PDO $conn, int|string $idMatricula): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*)
                            FROM pago p
                            INNER JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                            WHERE p.id_matricula=?
                              AND LOWER(ep.nombre)='pagado'
                              AND LOWER(p.concepto) LIKE '%matric%'");
    $stmt->execute([$idMatricula]);
    return (int) $stmt->fetchColumn() > 0;
}
?>
