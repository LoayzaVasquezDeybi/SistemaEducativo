<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user = usuarioActual($conn);

try {
    asegurarEstructuraGenerador($conn);

    switch ($action) {
        case 'combo':
            $grados = $conn->query("SELECT id_grado, nombre FROM grado ORDER BY id_grado")->fetchAll(PDO::FETCH_ASSOC);
            $secciones = $conn->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'grados' => $grados, 'secciones' => $secciones]);
            break;

        case 'preview':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede generar horarios automaticos');
            $data = json_decode(file_get_contents('php://input'), true);
            validarEntradaGenerador($data);
            asegurarCursosBase($conn);
            $resultado = generarHorarioSeccion($conn, (int) $data['id_grado'], (int) $data['id_seccion']);
            $resultado['estudiantes_asignados'] = contarEstudiantesDeSeccion($conn, (int) $data['id_grado'], (int) $data['id_seccion']);
            echo json_encode(['success' => true] + $resultado);
            break;

        case 'guardar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede guardar horarios automaticos');
            $data = json_decode(file_get_contents('php://input'), true);
            validarEntradaGenerador($data);
            if (empty($data['bloques']) || !is_array($data['bloques'])) throw new Exception('No hay bloques generados para guardar');

            $conn->beginTransaction();
            $estudiantesAsignados = contarEstudiantesDeSeccion($conn, (int) $data['id_grado'], (int) $data['id_seccion']);
            $stmtGen = $conn->prepare("INSERT INTO horario_generacion (id_grado, id_seccion, creado_por, estado, resumen) VALUES (?, ?, ?, 'guardado', ?)");
            $stmtGen->execute([$data['id_grado'], $data['id_seccion'], $user['id_usuario'], json_encode(['bloques' => count($data['bloques']), 'estudiantes_asignados' => $estudiantesAsignados, 'logs' => $data['logs'] ?? []])]);
            $idGeneracion = $conn->lastInsertId();

            // Regenerar solo esta seccion: no toca otras secciones ni el sistema base.
            $stmtDel = $conn->prepare("DELETE FROM horario WHERE id_grado=? AND id_seccion=?");
            $stmtDel->execute([$data['id_grado'], $data['id_seccion']]);

            $stmt = $conn->prepare("INSERT INTO horario (id_curso_docente, id_aula, id_grado, id_seccion, dia_semana, hora_inicio, hora_fin, id_generacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtDetalle = $conn->prepare("INSERT INTO horario_generacion_detalle (id_generacion, id_curso_docente, id_aula, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($data['bloques'] as $bloque) {
                $stmt->execute([
                    $bloque['id_curso_docente'],
                    $bloque['id_aula'],
                    $data['id_grado'],
                    $data['id_seccion'],
                    $bloque['dia_semana'],
                    $bloque['hora_inicio'],
                    $bloque['hora_fin'],
                    $idGeneracion,
                ]);
                $stmtDetalle->execute([
                    $idGeneracion,
                    $bloque['id_curso_docente'],
                    $bloque['id_aula'],
                    $bloque['dia_semana'],
                    $bloque['hora_inicio'],
                    $bloque['hora_fin'],
                ]);
            }

            if (!empty($data['logs'])) {
                $stmtLog = $conn->prepare("INSERT INTO horario_generacion_log (id_generacion, mensaje) VALUES (?, ?)");
                foreach ($data['logs'] as $log) $stmtLog->execute([$idGeneracion, $log]);
            }

            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Horario automatico guardado. Queda asignado a $estudiantesAsignados estudiante(s) de la seccion seleccionada.",
                'id_generacion' => $idGeneracion,
                'estudiantes_asignados' => $estudiantesAsignados
            ]);
            break;

        case 'revertir':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede revertir horarios automaticos');
            $data = json_decode(file_get_contents('php://input'), true);
            validarEntradaGenerador($data);
            $resultado = revertirHorarioSeccion($conn, (int) $data['id_grado'], (int) $data['id_seccion']);
            echo json_encode(['success' => true] + $resultado);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarEntradaGenerador(?array $data): void
{
    if (!$data || empty($data['id_grado']) || empty($data['id_seccion'])) {
        throw new Exception('Seleccione grado y seccion');
    }
}

function contarEstudiantesDeSeccion(PDO $conn, int $idGrado, int $idSeccion): int
{
    $stmt = $conn->prepare("SELECT COUNT(*)
                            FROM estudiante
                            WHERE id_grado = ?
                              AND id_seccion = ?
                              AND (estado IS NULL OR LOWER(estado) = 'activo')");
    $stmt->execute([$idGrado, $idSeccion]);
    return (int) $stmt->fetchColumn();
}

function revertirHorarioSeccion(PDO $conn, int $idGrado, int $idSeccion): array
{
    $conn->beginTransaction();

    $stmtActual = $conn->prepare("SELECT MAX(id_generacion)
                                  FROM horario
                                  WHERE id_grado = ?
                                    AND id_seccion = ?
                                    AND id_generacion IS NOT NULL");
    $stmtActual->execute([$idGrado, $idSeccion]);
    $idGeneracionActual = $stmtActual->fetchColumn();

    $idGeneracionAnterior = null;
    if ($idGeneracionActual) {
        $stmtAnterior = $conn->prepare("SELECT id_generacion
                                        FROM horario_generacion
                                        WHERE id_grado = ?
                                          AND id_seccion = ?
                                          AND id_generacion < ?
                                        ORDER BY id_generacion DESC
                                        LIMIT 1");
        $stmtAnterior->execute([$idGrado, $idSeccion, $idGeneracionActual]);
        $idGeneracionAnterior = $stmtAnterior->fetchColumn();
    }

    $stmtDel = $conn->prepare("DELETE FROM horario WHERE id_grado=? AND id_seccion=?");
    $stmtDel->execute([$idGrado, $idSeccion]);

    if (!$idGeneracionAnterior) {
        $conn->commit();
        return [
            'message' => 'Se elimino el horario actual de la seccion seleccionada. No habia una generacion anterior para restaurar.',
            'restaurado' => false,
            'bloques_restaurados' => 0
        ];
    }

    $stmtDetalle = $conn->prepare("SELECT id_curso_docente, id_aula, dia_semana, hora_inicio, hora_fin
                                   FROM horario_generacion_detalle
                                   WHERE id_generacion = ?");
    $stmtDetalle->execute([$idGeneracionAnterior]);
    $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

    $stmtInsert = $conn->prepare("INSERT INTO horario (id_curso_docente, id_aula, id_grado, id_seccion, dia_semana, hora_inicio, hora_fin, id_generacion)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($detalles as $bloque) {
        $stmtInsert->execute([
            $bloque['id_curso_docente'],
            $bloque['id_aula'],
            $idGrado,
            $idSeccion,
            $bloque['dia_semana'],
            $bloque['hora_inicio'],
            $bloque['hora_fin'],
            $idGeneracionAnterior,
        ]);
    }

    $conn->commit();
    return [
        'message' => 'Se revirtio el horario de la seccion seleccionada a la generacion anterior.',
        'restaurado' => true,
        'id_generacion_restaurada' => (int) $idGeneracionAnterior,
        'bloques_restaurados' => count($detalles)
    ];
}

function asegurarEstructuraGenerador(PDO $conn): void
{
    $cols = $conn->query("SHOW COLUMNS FROM horario")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('id_generacion', $cols, true)) {
        $conn->exec("ALTER TABLE horario ADD COLUMN id_generacion INT(11) NULL AFTER id_seccion");
    }

    $conn->exec("CREATE TABLE IF NOT EXISTS horario_generacion (
        id_generacion INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_grado INT(11) NOT NULL,
        id_seccion INT(11) NOT NULL,
        creado_por INT(11) NULL,
        fecha_generacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        estado VARCHAR(50) DEFAULT 'preview',
        resumen TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS horario_generacion_log (
        id_log INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_generacion INT(11) NOT NULL,
        mensaje VARCHAR(500) NOT NULL,
        fecha DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS horario_generacion_detalle (
        id_detalle INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_generacion INT(11) NOT NULL,
        id_curso_docente INT(11) NOT NULL,
        id_aula INT(11) NOT NULL,
        dia_semana VARCHAR(20) NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->exec("CREATE TABLE IF NOT EXISTS docente_disponibilidad (
        id_disponibilidad INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        id_docente INT(11) NOT NULL,
        dia_semana VARCHAR(20) NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function asegurarCursosBase(PDO $conn): void
{
    $nombres = ['Lenguaje', 'Literatura', 'Algebra', 'Trigonometria', 'Fisica', 'Quimica', 'Educacion Fisica', 'Historia', 'Geografia', 'Ingles'];
    $stmtCurso = $conn->prepare("INSERT INTO curso (nombre, descripcion, creditos, estado)
                                 SELECT ?, 'Curso base para generador automatico', 0, 'activo'
                                 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM curso WHERE LOWER(nombre)=LOWER(?))");
    foreach ($nombres as $nombre) $stmtCurso->execute([$nombre, $nombre]);

    $docentes = $conn->query("SELECT id_docente FROM docente ORDER BY id_docente")->fetchAll(PDO::FETCH_COLUMN);
    if (!$docentes) throw new Exception('No hay docentes registrados para asignar cursos');
    $idPeriodo = obtenerPeriodoActualGenerador($conn);

    $cursos = $conn->query("SELECT id_curso FROM curso WHERE estado='activo' ORDER BY id_curso")->fetchAll(PDO::FETCH_COLUMN);
    $stmtExiste = $conn->prepare("SELECT COUNT(*) FROM curso_docente WHERE id_curso=?");
    $stmtCd = $conn->prepare("INSERT INTO curso_docente (id_curso, id_docente, id_periodo) VALUES (?, ?, ?)");
    foreach ($cursos as $i => $idCurso) {
        $stmtExiste->execute([$idCurso]);
        if ((int) $stmtExiste->fetchColumn() === 0) {
            $stmtCd->execute([$idCurso, $docentes[$i % count($docentes)], $idPeriodo]);
        }
    }
}

function generarHorarioSeccion(PDO $conn, int $idGrado, int $idSeccion): array
{
    $logs = [];
    $bloques = [];
    $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'];
    $slots = [
        ['08:15:00', '09:45:00'],
        ['09:45:00', '11:15:00'],
        ['11:15:00', '11:45:00', 'Refrigerio'],
        ['11:45:00', '13:15:00'],
        ['13:15:00', '14:15:00'],
    ];

    $cursos = obtenerCursosPrioritarios($conn);
    $aulas = $conn->query("SELECT id_aula, nombre_aula FROM aula WHERE estado IS NULL OR LOWER(estado)='activo' ORDER BY id_aula")->fetchAll(PDO::FETCH_ASSOC);
    if (!$aulas) throw new Exception('No hay aulas activas para generar horario');

    $pendientes = expandirCursosSemanal($cursos);
    $ocupacionProfesor = cargarOcupacionProfesor($conn, $idGrado, $idSeccion);
    $ocupacionAula = cargarOcupacionAula($conn, $idGrado, $idSeccion);
    $ocupacionSeccion = [];

    foreach ($pendientes as $curso) {
        $asignado = false;
        foreach ($dias as $dia) {
            foreach ($slots as $slot) {
                if (isset($slot[2])) continue; // refrigerio fijo
                [$inicio, $fin] = $slot;
                if ($curso['curso_normalizado'] === 'educacion fisica' && diferenciaMinutos($inicio, $fin) < 90) continue;

                $keySeccion = "$dia|$inicio|$fin";
                if (isset($ocupacionSeccion[$keySeccion])) continue;
                if (estaOcupado($ocupacionProfesor[$curso['id_docente']] ?? [], $dia, $inicio, $fin)) continue;
                if (!docenteDisponible($conn, $curso['id_docente'], $dia, $inicio, $fin)) continue;

                foreach ($aulas as $aula) {
                    if (estaOcupado($ocupacionAula[$aula['id_aula']] ?? [], $dia, $inicio, $fin)) continue;
                    $bloques[] = [
                        'id_curso_docente' => $curso['id_curso_docente'],
                        'id_curso' => $curso['id_curso'],
                        'id_docente' => $curso['id_docente'],
                        'id_aula' => $aula['id_aula'],
                        'curso' => $curso['curso'],
                        'docente' => $curso['docente'],
                        'aula' => $aula['nombre_aula'],
                        'dia_semana' => $dia,
                        'hora_inicio' => $inicio,
                        'hora_fin' => $fin,
                    ];
                    $ocupacionSeccion[$keySeccion] = true;
                    $ocupacionProfesor[$curso['id_docente']][] = [$dia, $inicio, $fin];
                    $ocupacionAula[$aula['id_aula']][] = [$dia, $inicio, $fin];
                    $asignado = true;
                    break 3;
                }
            }
        }
        if (!$asignado) {
            $logs[] = 'No se pudo asignar ' . $curso['curso'] . ' sin cruces.';
        }
    }

    $hayEducacionFisica = array_filter($bloques, fn($b) => normalizar($b['curso']) === 'educacion fisica' && diferenciaMinutos($b['hora_inicio'], $b['hora_fin']) >= 90);
    if (!$hayEducacionFisica) $logs[] = 'Regla critica: no se logro asignar Educacion Fisica de 90 minutos.';

    return ['bloques' => $bloques, 'logs' => $logs, 'refrigerio' => ['hora_inicio' => '11:15:00', 'hora_fin' => '11:45:00']];
}

function docenteDisponible(PDO $conn, int|string $idDocente, string $dia, string $inicio, string $fin): bool
{
    $stmtCount = $conn->prepare("SELECT COUNT(*) FROM docente_disponibilidad WHERE id_docente=?");
    $stmtCount->execute([$idDocente]);
    if ((int) $stmtCount->fetchColumn() === 0) {
        return true;
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM docente_disponibilidad
                            WHERE id_docente=?
                              AND dia_semana=?
                              AND hora_inicio <= ?
                              AND hora_fin >= ?");
    $stmt->execute([$idDocente, $dia, $inicio, $fin]);
    return (int) $stmt->fetchColumn() > 0;
}

function obtenerCursosPrioritarios(PDO $conn): array
{
    $sql = "SELECT cd.id_curso_docente, c.id_curso, c.nombre as curso, d.id_docente, CONCAT(u.nombres, ' ', u.apellidos) as docente
            FROM curso_docente cd
            INNER JOIN curso c ON cd.id_curso = c.id_curso
            INNER JOIN docente d ON cd.id_docente = d.id_docente
            INNER JOIN usuario u ON d.id_usuario = u.id_usuario
            WHERE c.estado IS NULL OR LOWER(c.estado)='activo'";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['curso_normalizado'] = normalizar($row['curso']);
        $row['prioridad'] = prioridadCurso($row['curso_normalizado']);
    }
    usort($rows, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);
    return $rows;
}

function expandirCursosSemanal(array $cursos): array
{
    $objetivo = [
        'educacion fisica' => 1,
        'lenguaje' => 3,
        'algebra' => 3,
        'ingles' => 2,
        'literatura' => 2,
        'fisica' => 2,
        'quimica' => 2,
        'historia' => 2,
        'geografia' => 1,
        'trigonometria' => 1,
    ];
    $salida = [];
    foreach ($cursos as $curso) {
        $veces = $objetivo[$curso['curso_normalizado']] ?? 1;
        for ($i = 0; $i < $veces; $i++) $salida[] = $curso;
    }
    usort($salida, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);
    return array_slice($salida, 0, 20);
}

function cargarOcupacionProfesor(PDO $conn, int $idGrado, int $idSeccion): array
{
    $stmt = $conn->prepare("SELECT cd.id_docente, h.dia_semana, h.hora_inicio, h.hora_fin
                            FROM horario h
                            INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                            WHERE NOT (h.id_grado=? AND h.id_seccion=?)");
    $stmt->execute([$idGrado, $idSeccion]);
    $ocupacion = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ocupacion[$row['id_docente']][] = [$row['dia_semana'], $row['hora_inicio'], $row['hora_fin']];
    }
    return $ocupacion;
}

function cargarOcupacionAula(PDO $conn, int $idGrado, int $idSeccion): array
{
    $stmt = $conn->prepare("SELECT id_aula, dia_semana, hora_inicio, hora_fin
                            FROM horario
                            WHERE NOT (id_grado=? AND id_seccion=?)");
    $stmt->execute([$idGrado, $idSeccion]);
    $ocupacion = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $ocupacion[$row['id_aula']][] = [$row['dia_semana'], $row['hora_inicio'], $row['hora_fin']];
    }
    return $ocupacion;
}

function estaOcupado(array $ocupaciones, string $dia, string $inicio, string $fin): bool
{
    foreach ($ocupaciones as $o) {
        if ($o[0] === $dia && $inicio < $o[2] && $fin > $o[1]) return true;
    }
    return false;
}

function prioridadCurso(string $curso): int
{
    if ($curso === 'educacion fisica') return 1;
    if (in_array($curso, ['lenguaje', 'algebra', 'fisica', 'quimica', 'ingles'], true)) return 2;
    return 3;
}

function normalizar(string $texto): string
{
    $texto = strtolower(trim($texto));
    $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
    return strtr($texto, $map);
}

function diferenciaMinutos(string $inicio, string $fin): int
{
    return (strtotime($fin) - strtotime($inicio)) / 60;
}

function obtenerPeriodoActualGenerador(PDO $conn): int
{
    $id = $conn->query("SELECT id_periodo FROM periodo_academico WHERE LOWER(estado)='activo' ORDER BY id_periodo DESC LIMIT 1")->fetchColumn();
    if ($id) return (int) $id;
    $id = $conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1")->fetchColumn();
    if ($id) return (int) $id;
    $anio = date('Y');
    $stmt = $conn->prepare("INSERT INTO periodo_academico (anio, nombre, estado) VALUES (?, ?, 'activo')");
    $stmt->execute([$anio, "Periodo $anio"]);
    return (int) $conn->lastInsertId();
}
?>
