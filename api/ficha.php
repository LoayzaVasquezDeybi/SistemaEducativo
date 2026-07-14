<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? 'obtener';

try {
    switch ($action) {
        case 'combo':
            $stmt = $conn->query("SELECT m.id_matricula, e.id_estudiante, e.nombre, e.apellido, e.dni
                                  FROM matricula m
                                  INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                                  WHERE EXISTS (
                                      SELECT 1 FROM pago p
                                      INNER JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                                      WHERE p.id_matricula = m.id_matricula
                                        AND LOWER(ep.nombre) = 'pagado'
                                        AND LOWER(p.concepto) LIKE '%matric%'
                                  )
                                  ORDER BY e.apellido, e.nombre");
            echo json_encode(['success' => true, 'matriculas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'obtener':
            $idMatricula = $_GET['id_matricula'] ?? null;
            if (!$idMatricula) throw new Exception('Seleccione una matricula');

            $stmt = $conn->prepare("SELECT m.id_matricula, m.fecha_matricula, em.nombre as estado_matricula,
                                           e.id_estudiante, e.codigo_estudiante, e.nombre, e.apellido, e.dni, e.fecha_nacimiento, e.estado,
                                           g.nombre as grado, s.nombre as seccion,
                                           v.total_vacantes, v.vacantes_disponibles,
                                           p.nombre as periodo
                                    FROM matricula m
                                    INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                                    INNER JOIN vacante v ON m.id_vacante = v.id_vacante
                                    INNER JOIN periodo_academico p ON v.id_periodo = p.id_periodo
                                    LEFT JOIN grado g ON v.id_grado = g.id_grado
                                    LEFT JOIN seccion s ON v.id_seccion = s.id_seccion
                                    LEFT JOIN estado_matricula em ON m.id_estado_matricula = em.id_estado_matricula
                                    WHERE m.id_matricula = ?");
            $stmt->execute([$idMatricula]);
            $ficha = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ficha) throw new Exception('No se encontro la matricula');

            $stmtPagoMatricula = $conn->prepare("SELECT COUNT(*)
                                                 FROM pago p
                                                 INNER JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                                                 WHERE p.id_matricula = ?
                                                   AND LOWER(ep.nombre) = 'pagado'
                                                   AND LOWER(p.concepto) LIKE '%matric%'");
            $stmtPagoMatricula->execute([$idMatricula]);
            if ((int) $stmtPagoMatricula->fetchColumn() === 0) {
                throw new Exception('La ficha se habilita despues de procesar el pago de matricula');
            }

            $stmtApo = $conn->prepare("SELECT u.nombres, u.apellidos, u.dni, u.email, ea.parentesco
                                       FROM estudiante_apoderado ea
                                       INNER JOIN apoderado a ON ea.id_apoderado = a.id_apoderado
                                       INNER JOIN usuario u ON a.id_usuario = u.id_usuario
                                       WHERE ea.id_estudiante = ?");
            $stmtApo->execute([$ficha['id_estudiante']]);

            $stmtPagos = $conn->prepare("SELECT p.concepto, p.monto, p.fecha_pago, ep.nombre as estado_pago, mp.nombre as metodo
                                         FROM pago p
                                         LEFT JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                                         LEFT JOIN metodo_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                                         WHERE p.id_matricula = ?
                                         ORDER BY p.fecha_pago DESC");
            $stmtPagos->execute([$idMatricula]);

            echo json_encode([
                'success' => true,
                'ficha' => $ficha,
                'apoderados' => $stmtApo->fetchAll(PDO::FETCH_ASSOC),
                'pagos' => $stmtPagos->fetchAll(PDO::FETCH_ASSOC),
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
