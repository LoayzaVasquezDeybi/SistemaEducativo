<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

// Silenciar errores para que no ensucien la respuesta JSON
error_reporting(0); 

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user = usuarioActual($conn);

try {
    switch($action) {
        // 1. OBTENER CURSOS Y SU DOCENTE ASIGNADO
        case 'obtener':
            $filters = [];
            $params = [];
            if (esDocente($user) && $user['id_docente']) {
                $filters[] = 'cd.id_docente = ?';
                $params[] = $user['id_docente'];
            } elseif (esAlumno($user) && $user['id_estudiante']) {
                $filters[] = "EXISTS (
                    SELECT 1 FROM nota n
                    WHERE n.id_curso_docente = cd.id_curso_docente AND n.id_estudiante = ?
                )";
                $params[] = $user['id_estudiante'];
            }
            $query = "SELECT c.id_curso, c.nombre, c.descripcion, c.creditos, c.estado,
                             cd.id_docente, u.nombres as docente_nombre, u.apellidos as docente_apellido
                      FROM curso c
                      LEFT JOIN curso_docente cd ON c.id_curso = cd.id_curso
                      LEFT JOIN docente d ON cd.id_docente = d.id_docente
                      LEFT JOIN usuario u ON d.id_usuario = u.id_usuario
                      " . ($filters ? 'WHERE ' . implode(' AND ', $filters) : '') . "
                      ORDER BY c.nombre";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $cursos]);
            break;

        // 2. OBTENER DOCENTES PARA EL SELECT
        case 'combo':
            $queryDocentes = "SELECT d.id_docente, u.nombres, u.apellidos 
                              FROM docente d 
                              INNER JOIN usuario u ON d.id_usuario = u.id_usuario 
                              WHERE u.id_estado_usuario = 1 
                              ORDER BY u.apellidos, u.nombres";
            $stmt = $conn->query($queryDocentes);
            $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'docentes' => $docentes]);
            break;

        // 3. CREAR CURSO
        case 'crear':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede crear cursos');
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('No se recibieron datos válidos');
            
            $conn->beginTransaction();
            
            // Buscar el último periodo académico válido automáticamente
            $stmtPeriodo = $conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1");
            $periodoRow = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
            
            if (!$periodoRow) {
                // Intentar auto-crear un periodo con el año actual si no existe ninguno
                try {
                    $anio = date('Y');
                    $conn->exec("INSERT INTO periodo_academico (anio, nombre, estado) VALUES ('$anio', 'Periodo $anio', 'activo')");
                    $id_periodo = $conn->lastInsertId();
                } catch (Exception $e) {
                    throw new Exception("Tu tabla 'periodo_academico' está vacía. Entra a phpMyAdmin e inserta un año manualmente para continuar.");
                }
            } else {
                $id_periodo = $periodoRow['id_periodo'];
            }
            
            // Crear Curso
            $stmt = $conn->prepare("INSERT INTO curso (nombre, descripcion, creditos, estado) VALUES (?, ?, ?, 'activo')");
            $stmt->execute([$data['nombre'], $data['descripcion'] ?? null, $data['creditos'] ?? 0]);
            
            $id_curso = $conn->lastInsertId();
            
            // Asignar docente si se seleccionó uno usando el periodo dinámico
            if (!empty($data['id_docente'])) {
                $stmtCd = $conn->prepare("INSERT INTO curso_docente (id_curso, id_docente, id_periodo) VALUES (?, ?, ?)");
                $stmtCd->execute([$id_curso, $data['id_docente'], $id_periodo]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Curso creado exitosamente']);
            break;

        // 4. ACTUALIZAR CURSO
        case 'actualizar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede actualizar cursos');
            $data = json_decode(file_get_contents('php://input'), true);
            $conn->beginTransaction();
            
            // Buscar el último periodo académico válido automáticamente
            $stmtPeriodo = $conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1");
            $periodoRow = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
            if (!$periodoRow) {
                // Intentar auto-crear un periodo con el año actual si no existe ninguno
                try {
                    $anio = date('Y');
                    $conn->exec("INSERT INTO periodo_academico (anio, nombre, estado) VALUES ('$anio', 'Periodo $anio', 'activo')");
                    $id_periodo = $conn->lastInsertId();
                } catch (Exception $e) {
                    throw new Exception("Tu tabla 'periodo_academico' está vacía. Entra a phpMyAdmin e inserta un año manualmente para continuar.");
                }
            } else {
                $id_periodo = $periodoRow['id_periodo'];
            }
            
            // Actualizar curso
            $stmt = $conn->prepare("UPDATE curso SET nombre=?, descripcion=?, creditos=?, estado=? WHERE id_curso=?");
            $stmt->execute([$data['nombre'], $data['descripcion'] ?? null, $data['creditos'] ?? 0, $data['estado'] ?? 'activo', $data['id_curso']]);
            
            // Actualizar asignación de docente (Borramos la anterior y creamos la nueva)
            $stmtDelCd = $conn->prepare("DELETE FROM curso_docente WHERE id_curso=?");
            $stmtDelCd->execute([$data['id_curso']]);
            
            if (!empty($data['id_docente'])) {
                $stmtCd = $conn->prepare("INSERT INTO curso_docente (id_curso, id_docente, id_periodo) VALUES (?, ?, ?)");
                $stmtCd->execute([$data['id_curso'], $data['id_docente'], $id_periodo]);
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Curso actualizado con éxito']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }
} catch(Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
