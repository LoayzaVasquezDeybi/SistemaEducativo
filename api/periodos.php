<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        case 'obtener':
            $stmt = $conn->query("SELECT * FROM periodo_academico ORDER BY anio DESC");
            $periodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $periodos]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $stmt = $conn->prepare("INSERT INTO periodo_academico (anio, nombre, fecha_inicio, fecha_fin, estado) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['anio'], $data['nombre'], $data['fecha_inicio'], $data['fecha_fin'], $data['estado']
            ]);
            echo json_encode(['success' => true, 'message' => 'Periodo registrado correctamente']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE periodo_academico SET anio=?, nombre=?, fecha_inicio=?, fecha_fin=?, estado=? WHERE id_periodo=?");
            $stmt->execute([
                $data['anio'], $data['nombre'], $data['fecha_inicio'], $data['fecha_fin'], $data['estado'], $data['id_periodo']
            ]);
            echo json_encode(['success' => true, 'message' => 'Periodo actualizado con éxito']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("DELETE FROM periodo_academico WHERE id_periodo=?");
            $stmt->execute([$data['id_periodo']]);
            echo json_encode(['success' => true, 'message' => 'Registro de periodo eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>