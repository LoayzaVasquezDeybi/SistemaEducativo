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
            $filters = [];
            $params = [];
            if (esDocente($user) && $user['id_docente']) {
                $filters[] = 'cd.id_docente = ?';
                $params[] = $user['id_docente'];
            } elseif (esAlumno($user) && $user['id_estudiante']) {
                $filters[] = 'h.id_grado = (SELECT id_grado FROM estudiante WHERE id_estudiante = ?)';
                $params[] = $user['id_estudiante'];
                $filters[] = 'h.id_seccion = (SELECT id_seccion FROM estudiante WHERE id_estudiante = ?)';
                $params[] = $user['id_estudiante'];
            }
            $sql = "SELECT h.id_horario, h.id_curso_docente, h.id_aula, h.id_grado, h.id_seccion,
                                         h.dia_semana, h.hora_inicio, h.hora_fin,
                                         c.nombre as curso, a.nombre_aula, g.nombre as grado, s.nombre as seccion,
                                         CONCAT(u.nombres, ' ', u.apellidos) as docente
                                  FROM horario h
                                  INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                                  INNER JOIN curso c ON cd.id_curso = c.id_curso
                                  INNER JOIN docente d ON cd.id_docente = d.id_docente
                                  INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                  INNER JOIN aula a ON h.id_aula = a.id_aula
                                  LEFT JOIN grado g ON h.id_grado = g.id_grado
                                  LEFT JOIN seccion s ON h.id_seccion = s.id_seccion
                                  " . ($filters ? 'WHERE ' . implode(' AND ', $filters) : '') . "
                                  ORDER BY FIELD(h.dia_semana, 'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'), h.hora_inicio";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'combo':
            $whereCursos = esDocente($user) && $user['id_docente'] ? 'WHERE d.id_docente = ' . (int) $user['id_docente'] : '';
            $cursos = $conn->query("SELECT cd.id_curso_docente, c.nombre as curso, CONCAT(u.nombres, ' ', u.apellidos) as docente
                                    FROM curso_docente cd
                                    INNER JOIN curso c ON cd.id_curso = c.id_curso
                                    INNER JOIN docente d ON cd.id_docente = d.id_docente
                                    INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                    $whereCursos
                                    ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
            $aulas = $conn->query("SELECT id_aula, nombre_aula FROM aula WHERE estado IS NULL OR LOWER(estado)='activo' ORDER BY nombre_aula")->fetchAll(PDO::FETCH_ASSOC);
            $grados = $conn->query("SELECT id_grado, nombre FROM grado ORDER BY id_grado")->fetchAll(PDO::FETCH_ASSOC);
            $secciones = $conn->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'cursos' => $cursos, 'aulas' => $aulas, 'grados' => $grados, 'secciones' => $secciones]);
            break;

        case 'crear':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede crear horarios');
            $data = json_decode(file_get_contents('php://input'), true);
            validarHorario($data);
            validarCruceHorario($conn, $data);
            $stmt = $conn->prepare("INSERT INTO horario (id_curso_docente, id_aula, id_grado, id_seccion, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['id_curso_docente'], $data['id_aula'], $data['id_grado'], $data['id_seccion'], $data['dia_semana'], $data['hora_inicio'], $data['hora_fin']]);
            echo json_encode(['success' => true, 'message' => 'Horario registrado correctamente']);
            break;

        case 'actualizar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede actualizar horarios');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_horario'])) throw new Exception('No se recibio el horario');
            validarHorario($data);
            validarCruceHorario($conn, $data, $data['id_horario']);
            $stmt = $conn->prepare("UPDATE horario SET id_curso_docente=?, id_aula=?, id_grado=?, id_seccion=?, dia_semana=?, hora_inicio=?, hora_fin=? WHERE id_horario=?");
            $stmt->execute([$data['id_curso_docente'], $data['id_aula'], $data['id_grado'], $data['id_seccion'], $data['dia_semana'], $data['hora_inicio'], $data['hora_fin'], $data['id_horario']]);
            echo json_encode(['success' => true, 'message' => 'Horario actualizado correctamente']);
            break;

        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede eliminar horarios');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_horario'])) throw new Exception('No se recibio el horario');
            $stmt = $conn->prepare("DELETE FROM horario WHERE id_horario=?");
            $stmt->execute([$data['id_horario']]);
            echo json_encode(['success' => true, 'message' => 'Horario eliminado']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarHorario(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['id_curso_docente', 'id_aula', 'id_grado', 'id_seccion', 'dia_semana', 'hora_inicio', 'hora_fin'] as $campo) {
        if (empty($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
    if ($data['hora_inicio'] >= $data['hora_fin']) throw new Exception('La hora de inicio debe ser menor que la hora fin');

    $inicio = strtotime($data['hora_inicio']);
    $fin = strtotime($data['hora_fin']);
    $baseInicio = strtotime('08:15:00');
    $baseFin = strtotime('14:15:00');
    $refrigerioInicio = strtotime('11:15:00');
    $refrigerioFin = strtotime('11:45:00');

    if ($inicio < $baseInicio || $fin > $baseFin) throw new Exception('El horario debe estar entre 08:15 y 14:15');
    if (($fin - $inicio) / 60 < 90) throw new Exception('Cada clase debe durar minimo 1 hora y 30 minutos');
    if (($fin - $inicio) / 60 > 90) throw new Exception('Las clases no pueden superar 90 minutos');
    if ($inicio < $refrigerioFin && $fin > $refrigerioInicio) throw new Exception('El horario no puede cruzar el refrigerio de 11:15 a 11:45');
}

function validarCruceHorario(PDO $conn, array $data, int|string|null $idHorario = null): void
{
    $sqlSeccion = "SELECT COUNT(*) FROM horario
            WHERE dia_semana = ?
              AND id_grado = ?
              AND id_seccion = ?
              AND hora_inicio < ?
              AND hora_fin > ?";
    $paramsSeccion = [$data['dia_semana'], $data['id_grado'], $data['id_seccion'], $data['hora_fin'], $data['hora_inicio']];
    if ($idHorario) {
        $sqlSeccion .= " AND id_horario <> ?";
        $paramsSeccion[] = $idHorario;
    }
    $stmt = $conn->prepare($sqlSeccion);
    $stmt->execute($paramsSeccion);
    if ($stmt->fetchColumn() > 0) throw new Exception('La seccion ya tiene una clase en ese rango de horas');

    $sqlAula = "SELECT COUNT(*) FROM horario
                WHERE dia_semana = ?
                  AND id_aula = ?
                  AND hora_inicio < ?
                  AND hora_fin > ?";
    $paramsAula = [$data['dia_semana'], $data['id_aula'], $data['hora_fin'], $data['hora_inicio']];
    if ($idHorario) {
        $sqlAula .= " AND id_horario <> ?";
        $paramsAula[] = $idHorario;
    }
    $stmt = $conn->prepare($sqlAula);
    $stmt->execute($paramsAula);
    if ($stmt->fetchColumn() > 0) throw new Exception('El aula ya esta ocupada en ese rango de horas');

    $stmtDocente = $conn->prepare("SELECT id_docente FROM curso_docente WHERE id_curso_docente=?");
    $stmtDocente->execute([$data['id_curso_docente']]);
    $idDocente = $stmtDocente->fetchColumn();
    if (!$idDocente) throw new Exception('No se encontro el docente del curso seleccionado');

    $sqlDocente = "SELECT COUNT(*)
                   FROM horario h
                   INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                   WHERE h.dia_semana = ?
                     AND cd.id_docente = ?
                     AND h.hora_inicio < ?
                     AND h.hora_fin > ?";
    $paramsDocente = [$data['dia_semana'], $idDocente, $data['hora_fin'], $data['hora_inicio']];
    if ($idHorario) {
        $sqlDocente .= " AND h.id_horario <> ?";
        $paramsDocente[] = $idHorario;
    }
    $stmt = $conn->prepare($sqlDocente);
    $stmt->execute($paramsDocente);
    if ($stmt->fetchColumn() > 0) throw new Exception('El docente ya tiene una clase en ese rango de horas');

    $duracion = (strtotime($data['hora_fin']) - strtotime($data['hora_inicio'])) / 60;

    $sqlCargaSeccion = "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(h.hora_fin, h.hora_inicio)) / 60), 0)
                        FROM horario h
                        INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                        WHERE h.dia_semana = ?
                          AND cd.id_docente = ?
                          AND h.id_grado = ?
                          AND h.id_seccion = ?";
    $paramsCargaSeccion = [$data['dia_semana'], $idDocente, $data['id_grado'], $data['id_seccion']];
    if ($idHorario) {
        $sqlCargaSeccion .= " AND h.id_horario <> ?";
        $paramsCargaSeccion[] = $idHorario;
    }
    $stmt = $conn->prepare($sqlCargaSeccion);
    $stmt->execute($paramsCargaSeccion);
    if (((float) $stmt->fetchColumn() + $duracion) > 180) {
        throw new Exception('El docente no puede dictar mas de 3 horas al dia en la misma seccion');
    }

    $sqlCargaAula = "SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(h.hora_fin, h.hora_inicio)) / 60), 0)
                     FROM horario h
                     INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                     WHERE h.dia_semana = ?
                       AND cd.id_docente = ?
                       AND h.id_aula = ?";
    $paramsCargaAula = [$data['dia_semana'], $idDocente, $data['id_aula']];
    if ($idHorario) {
        $sqlCargaAula .= " AND h.id_horario <> ?";
        $paramsCargaAula[] = $idHorario;
    }
    $stmt = $conn->prepare($sqlCargaAula);
    $stmt->execute($paramsCargaAula);
    if (((float) $stmt->fetchColumn() + $duracion) > 180) {
        throw new Exception('El docente no puede dictar mas de 3 horas al dia en la misma aula');
    }
}
?>
