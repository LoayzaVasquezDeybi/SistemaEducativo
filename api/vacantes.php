<?php
header('Content-Type: application/json');
require_once '../config/conexion.php';

error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch($action) {
        case 'obtener':
            $query = "SELECT v.*, 
                             COALESCE(p.nombre, v.id_periodo) as periodo_nombre, 
                             COALESCE(g.nombre, v.id_grado) as grado_nombre, 
                             COALESCE(s.nombre, v.id_seccion) as seccion_nombre 
                      FROM vacante v 
                      LEFT JOIN periodo_academico p ON v.id_periodo = p.id_periodo 
                      LEFT JOIN grado g ON v.id_grado = g.id_grado 
                      LEFT JOIN seccion s ON v.id_seccion = s.id_seccion 
                      ORDER BY v.id_vacante DESC";
            try {
                $stmt = $conn->query($query);
            } catch (Exception $e) {
                // Si las tablas relacionadas no existen, caemos a una consulta básica
                $stmt = $conn->query("SELECT * FROM vacante ORDER BY id_vacante DESC");
            }
            $vacantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $vacantes]);
            break;

        case 'combo':
            $grados = []; $secciones = []; $periodos = [];
            try { $grados = $conn->query("SELECT id_grado, nombre FROM grado")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e) {}
            try { $secciones = $conn->query("SELECT id_seccion, nombre FROM seccion")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e) {}
            try { $periodos = $conn->query("SELECT id_periodo, nombre FROM periodo_academico")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e) {
                // Alternativa por si el periodo usa la columna "anio"
                try { $periodos = $conn->query("SELECT id_periodo, anio as nombre FROM periodo_academico")->fetchAll(PDO::FETCH_ASSOC); } catch(Exception $e) {}
            }
            echo json_encode(['success' => true, 'grados' => $grados, 'secciones' => $secciones, 'periodos' => $periodos]);
            break;

        case 'crear':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $stmt = $conn->prepare("INSERT INTO vacante (id_periodo, id_grado, id_seccion, total_vacantes, vacantes_disponibles) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['id_periodo'],
                $data['id_grado'],
                $data['id_seccion'],
                $data['total_vacantes'],
                $data['vacantes_disponibles']
            ]);
            echo json_encode(['success' => true, 'message' => 'Vacante registrada correctamente']);
            break;

        case 'actualizar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE vacante SET id_periodo=?, id_grado=?, id_seccion=?, total_vacantes=?, vacantes_disponibles=? WHERE id_vacante=?");
            $stmt->execute([
                $data['id_periodo'], $data['id_grado'], $data['id_seccion'], 
                $data['total_vacantes'], $data['vacantes_disponibles'], $data['id_vacante']
            ]);
            echo json_encode(['success' => true, 'message' => 'Vacante actualizada con éxito']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("DELETE FROM vacante WHERE id_vacante=?");
            $stmt->execute([$data['id_vacante']]);
            echo json_encode(['success' => true, 'message' => 'Registro de vacante eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>