<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'obtener':
            $query = "SELECT p.id_pago, p.id_matricula, p.concepto, p.monto, p.fecha_pago, p.id_metodo_pago, p.id_estado_pago,
                             CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni,
                             COALESCE(mp.nombre, p.id_metodo_pago) as metodo_nombre,
                             COALESCE(ep.nombre, p.id_estado_pago) as estado_nombre,
                             em.nombre as estado_matricula
                      FROM pago p
                      INNER JOIN matricula m ON p.id_matricula = m.id_matricula
                      INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                      LEFT JOIN metodo_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                      LEFT JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                      LEFT JOIN estado_matricula em ON m.id_estado_matricula = em.id_estado_matricula
                      ORDER BY p.fecha_pago DESC";
            $stmt = $conn->query($query);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_matriculas':
            $stmt = $conn->query("SELECT m.id_matricula, e.nombre, e.apellido, e.dni, e.estado, em.nombre as estado_matricula
                                  FROM matricula m
                                  INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                                  LEFT JOIN estado_matricula em ON m.id_estado_matricula = em.id_estado_matricula
                                  ORDER BY e.apellido, e.nombre");
            echo json_encode(['success' => true, 'matriculas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_metodos':
            $stmt = $conn->query("SELECT * FROM metodo_pago ORDER BY nombre");
            echo json_encode(['success' => true, 'metodos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo_estados':
            $stmt = $conn->query("SELECT * FROM estado_pago ORDER BY id_estado_pago");
            echo json_encode(['success' => true, 'estados' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            validarPago($data);

            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO pago (id_matricula, concepto, monto, fecha_pago, id_metodo_pago, id_estado_pago) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['id_matricula'], $data['concepto'], $data['monto'], $data['fecha_pago'], $data['id_metodo_pago'], $data['id_estado_pago']]);
            activarMatriculaSiCorresponde($conn, $data['id_matricula'], $data['concepto'], $data['id_estado_pago']);
            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Pago registrado correctamente']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_pago'])) throw new Exception('No se recibio el pago a actualizar');
            validarPago($data);

            $conn->beginTransaction();
            $stmt = $conn->prepare("UPDATE pago SET id_matricula=?, concepto=?, monto=?, fecha_pago=?, id_metodo_pago=?, id_estado_pago=? WHERE id_pago=?");
            $stmt->execute([$data['id_matricula'], $data['concepto'], $data['monto'], $data['fecha_pago'], $data['id_metodo_pago'], $data['id_estado_pago'], $data['id_pago']]);
            activarMatriculaSiCorresponde($conn, $data['id_matricula'], $data['concepto'], $data['id_estado_pago']);
            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Pago actualizado con exito']);
            break;

        case 'pagar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_pago'])) throw new Exception('No se recibio el pago');

            $stmtEstado = $conn->query("SELECT id_estado_pago FROM estado_pago WHERE LOWER(nombre) = 'pagado' ORDER BY id_estado_pago DESC LIMIT 1");
            $idPagado = $stmtEstado->fetchColumn() ?: 4;

            $conn->beginTransaction();
            $stmtInfo = $conn->prepare("SELECT id_matricula, concepto FROM pago WHERE id_pago=?");
            $stmtInfo->execute([$data['id_pago']]);
            $pago = $stmtInfo->fetch(PDO::FETCH_ASSOC);
            if (!$pago) throw new Exception('No se encontro el pago');

            $stmt = $conn->prepare("UPDATE pago SET id_estado_pago=? WHERE id_pago=?");
            $stmt->execute([$idPagado, $data['id_pago']]);
            activarMatriculaSiCorresponde($conn, $pago['id_matricula'], $pago['concepto'], $idPagado);
            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Pago marcado como realizado. Si es pago de matricula, la matricula y el estudiante quedaron activos.']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_pago'])) throw new Exception('No se recibio el pago');
            $stmt = $conn->prepare("DELETE FROM pago WHERE id_pago=?");
            $stmt->execute([$data['id_pago']]);
            echo json_encode(['success' => true, 'message' => 'Registro de pago eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarPago(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['id_matricula', 'concepto', 'monto', 'fecha_pago', 'id_metodo_pago', 'id_estado_pago'] as $campo) {
        if ($data[$campo] === '' || !isset($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
    if ((float) $data['monto'] <= 0) throw new Exception('El monto debe ser mayor a cero');
}

function activarMatriculaSiCorresponde(PDO $conn, int|string $idMatricula, string $concepto, int|string $idEstadoPago): void
{
    if (stripos($concepto, 'matric') === false || !estadoPagoEsPagado($conn, $idEstadoPago)) {
        return;
    }

    $stmtMat = $conn->prepare("SELECT m.id_estudiante, m.id_vacante, em.nombre as estado_matricula
                               FROM matricula m
                               LEFT JOIN estado_matricula em ON m.id_estado_matricula = em.id_estado_matricula
                               WHERE m.id_matricula=? FOR UPDATE");
    $stmtMat->execute([$idMatricula]);
    $matricula = $stmtMat->fetch(PDO::FETCH_ASSOC);
    if (!$matricula) throw new Exception('No se encontro la matricula del pago');

    $yaActiva = strtolower($matricula['estado_matricula'] ?? '') === 'activo';
    if (!$yaActiva) {
        ajustarVacanteDisponiblePago($conn, $matricula['id_vacante'], -1);
    }

    $idActivo = obtenerEstadoMatriculaPago($conn, 'Activo');
    $stmtUpdateMat = $conn->prepare("UPDATE matricula SET id_estado_matricula=? WHERE id_matricula=?");
    $stmtUpdateMat->execute([$idActivo, $idMatricula]);

    $stmtUpdateEst = $conn->prepare("UPDATE estudiante SET estado='activo' WHERE id_estudiante=?");
    $stmtUpdateEst->execute([$matricula['id_estudiante']]);
}

function estadoPagoEsPagado(PDO $conn, int|string $idEstadoPago): bool
{
    $stmt = $conn->prepare("SELECT nombre FROM estado_pago WHERE id_estado_pago=?");
    $stmt->execute([$idEstadoPago]);
    return strtolower((string) $stmt->fetchColumn()) === 'pagado';
}

function obtenerEstadoMatriculaPago(PDO $conn, string $nombre): int
{
    $stmt = $conn->prepare("SELECT id_estado_matricula FROM estado_matricula WHERE LOWER(nombre)=LOWER(?) LIMIT 1");
    $stmt->execute([$nombre]);
    $id = $stmt->fetchColumn();
    if ($id) return (int) $id;

    $stmt = $conn->prepare("INSERT INTO estado_matricula (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
    return (int) $conn->lastInsertId();
}

function ajustarVacanteDisponiblePago(PDO $conn, int|string $idVacante, int $delta): void
{
    $stmt = $conn->prepare("SELECT vacantes_disponibles, total_vacantes FROM vacante WHERE id_vacante=? FOR UPDATE");
    $stmt->execute([$idVacante]);
    $vacante = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$vacante) throw new Exception('La vacante de la matricula no existe');

    $nuevo = (int) $vacante['vacantes_disponibles'] + $delta;
    if ($nuevo < 0) throw new Exception('No hay vacantes disponibles para activar la matricula');
    if ($nuevo > (int) $vacante['total_vacantes']) $nuevo = (int) $vacante['total_vacantes'];

    $stmtUpdate = $conn->prepare("UPDATE vacante SET vacantes_disponibles=? WHERE id_vacante=?");
    $stmtUpdate->execute([$nuevo, $idVacante]);
}
?>
