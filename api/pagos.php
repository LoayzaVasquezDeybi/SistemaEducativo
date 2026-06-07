<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        // 1. OBTENER LISTA DE PAGOS
        case 'obtener':
            $query = "SELECT p.id_pago, p.id_matricula, p.concepto, p.monto, p.fecha_pago, p.id_metodo_pago, p.id_estado_pago,
                             CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni,
                             COALESCE(mp.nombre, p.id_metodo_pago) as metodo_nombre,
                             COALESCE(ep.nombre, p.id_estado_pago) as estado_nombre
                      FROM pago p
                      INNER JOIN matricula m ON p.id_matricula = m.id_matricula
                      INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                      LEFT JOIN metodo_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                      LEFT JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                      ORDER BY p.fecha_pago DESC";
            try {
                $stmt = $conn->query($query);
            } catch (Exception $e) {
                // Respaldo por si las tablas no existen
                $query_respaldo = "SELECT p.id_pago, p.id_matricula, p.concepto, p.monto, p.fecha_pago, p.id_metodo_pago, p.id_estado_pago,
                                          CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni
                                   FROM pago p INNER JOIN matricula m ON p.id_matricula = m.id_matricula
                                   INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante ORDER BY p.fecha_pago DESC";
                $stmt = $conn->query($query_respaldo);
            }
            $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $pagos]);
            break;

        // 2. OBTENER MATRÍCULAS PARA EL SELECT (COMBO)
        case 'combo_matriculas':
            $stmt = $conn->query("SELECT m.id_matricula, e.nombre, e.apellido, e.dni FROM matricula m INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante WHERE e.estado = 'activo' ORDER BY e.apellido, e.nombre");
            $matriculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'matriculas' => $matriculas]);
            break;

        case 'combo_metodos':
            $stmt = $conn->query("SELECT * FROM metodo_pago");
            $metodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'metodos' => $metodos]);
            break;

        case 'combo_estados':
            $stmt = $conn->query("SELECT * FROM estado_pago");
            $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estados' => $estados]);
            break;

        // 3. REGISTRAR NUEVO PAGO
        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $stmt = $conn->prepare("INSERT INTO pago (id_matricula, concepto, monto, fecha_pago, id_metodo_pago, id_estado_pago) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['id_matricula'],
                $data['concepto'],
                $data['monto'],
                $data['fecha_pago'],
                $data['id_metodo_pago'],
                $data['id_estado_pago']
            ]);
            echo json_encode(['success' => true, 'message' => 'Pago registrado correctamente']);
            break;

        // 4. ACTUALIZAR PAGO EXISTENTE
        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE pago SET id_matricula=?, concepto=?, monto=?, fecha_pago=?, id_metodo_pago=?, id_estado_pago=? WHERE id_pago=?");
            $stmt->execute([
                $data['id_matricula'], $data['concepto'], $data['monto'], 
                $data['fecha_pago'], $data['id_metodo_pago'], $data['id_estado_pago'], $data['id_pago']
            ]);
            echo json_encode(['success' => true, 'message' => 'Pago actualizado con éxito']);
            break;

        // 5. MARCAR COMO PAGADO (BOTÓN RÁPIDO)
        case 'pagar':
            $data = json_decode(file_get_contents('php://input'), true);
            // Asumimos que 2 es el ID para "Pagado" en tu tabla estado_pago
            $stmt = $conn->prepare("UPDATE pago SET id_estado_pago=2 WHERE id_pago=?");
            $stmt->execute([$data['id_pago']]);
            echo json_encode(['success' => true, 'message' => 'Pago marcado como realizado']);
            break;

        // 6. ELIMINAR PAGO
        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("DELETE FROM pago WHERE id_pago=?");
            $stmt->execute([$data['id_pago']]);
            echo json_encode(['success' => true, 'message' => 'Registro de pago eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
