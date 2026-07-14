<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';

try {
    switch ($action) {
        case 'obtener':
            $stmt = $conn->query("SELECT d.id_documento, d.nombre_archivo, d.ruta_archivo, d.fecha_subida,
                                         d.id_tipo_documento, d.id_usuario, d.id_estudiante,
                                         td.nombre as tipo_documento,
                                         CONCAT(u.nombres, ' ', u.apellidos) as usuario,
                                         CONCAT(e.nombre, ' ', e.apellido) as estudiante
                                  FROM documento d
                                  INNER JOIN tipo_documento td ON d.id_tipo_documento = td.id_tipo_documento
                                  INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                  LEFT JOIN estudiante e ON d.id_estudiante = e.id_estudiante
                                  ORDER BY d.fecha_subida DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo':
            asegurarTiposDocumento($conn);
            $tipos = $conn->query("SELECT id_tipo_documento, nombre FROM tipo_documento ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            $usuarios = $conn->query("SELECT id_usuario, CONCAT(nombres, ' ', apellidos) as nombre FROM usuario ORDER BY apellidos, nombres")->fetchAll(PDO::FETCH_ASSOC);
            $estudiantes = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'tipos' => $tipos, 'usuarios' => $usuarios, 'estudiantes' => $estudiantes]);
            break;

        case 'crear':
            asegurarTiposDocumento($conn);
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
            if (empty($_FILES['archivo'])) throw new Exception('Debe seleccionar un archivo');
            $idTipo = $_POST['id_tipo_documento'] ?? null;
            $idUsuario = $_POST['id_usuario'] ?? null;
            if (!$idTipo || !$idUsuario) throw new Exception('Tipo de documento y usuario son obligatorios');

            $archivo = $_FILES['archivo'];
            if ($archivo['size'] > 10 * 1024 * 1024) throw new Exception('El archivo no debe superar 10 MB');
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $permitidos, true)) throw new Exception('Tipo de archivo no permitido');

            $nombreSeguro = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
            $nombreFinal = $nombreSeguro . '_' . date('YmdHis') . '.' . $extension;
            $rutaFinal = $uploadDir . DIRECTORY_SEPARATOR . $nombreFinal;
            if (!move_uploaded_file($archivo['tmp_name'], $rutaFinal)) throw new Exception('No se pudo guardar el archivo');

            $rutaRelativa = 'uploads/' . $nombreFinal;
            $stmt = $conn->prepare("INSERT INTO documento (nombre_archivo, ruta_archivo, id_tipo_documento, id_usuario, id_estudiante) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$archivo['name'], $rutaRelativa, $idTipo, $idUsuario, $_POST['id_estudiante'] ?: null]);
            echo json_encode(['success' => true, 'message' => 'Documento subido correctamente']);
            break;

        case 'eliminar':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_documento'])) throw new Exception('No se recibio el documento');
            $stmtGet = $conn->prepare("SELECT ruta_archivo FROM documento WHERE id_documento=?");
            $stmtGet->execute([$data['id_documento']]);
            $ruta = $stmtGet->fetchColumn();
            $stmt = $conn->prepare("DELETE FROM documento WHERE id_documento=?");
            $stmt->execute([$data['id_documento']]);
            if ($ruta) {
                $rutaAbs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $ruta);
                if (is_file($rutaAbs)) unlink($rutaAbs);
            }
            echo json_encode(['success' => true, 'message' => 'Documento eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function asegurarTiposDocumento(PDO $conn): void
{
    if ((int) $conn->query("SELECT COUNT(*) FROM tipo_documento")->fetchColumn() > 0) return;
    $stmt = $conn->prepare("INSERT INTO tipo_documento (nombre) VALUES (?)");
    foreach (['Matricula', 'Boleta', 'Circular', 'Academico'] as $tipo) {
        $stmt->execute([$tipo]);
    }
}
?>
