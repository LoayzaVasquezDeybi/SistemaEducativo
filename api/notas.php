<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user = usuarioActual($conn);

try {
    switch ($action) {
        case 'planilla_asignaciones':
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden usar la planilla de calificaciones');
            asegurarPeriodoEvaluacion($conn);
            $stmt = $conn->prepare("SELECT DISTINCT cd.id_curso_docente,h.id_grado,h.id_seccion,
                    c.nombre AS curso,g.nombre AS grado,s.nombre AS seccion
                FROM horario h
                INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente
                INNER JOIN curso c ON cd.id_curso=c.id_curso
                INNER JOIN grado g ON h.id_grado=g.id_grado
                INNER JOIN seccion s ON h.id_seccion=s.id_seccion
                WHERE cd.id_docente=? ORDER BY h.id_grado,s.nombre,c.nombre");
            $stmt->execute([$user['id_docente']]);
            $periodos=$conn->query("SELECT id_periodo_evaluacion,nombre FROM periodo_evaluacion ORDER BY id_periodo_evaluacion")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'asignaciones'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'periodos'=>$periodos,'evaluaciones'=>nombresEvaluacionesPlanilla()]);
            break;

        case 'planilla_obtener':
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden usar la planilla de calificaciones');
            $idCursoDocente=$_GET['id_curso_docente']??null;
            $idGrado=$_GET['id_grado']??null;
            $idSeccion=$_GET['id_seccion']??null;
            $idPeriodoEvaluacion=$_GET['id_periodo_evaluacion']??null;
            validarAsignacionPlanilla($conn,$user,$idCursoDocente,$idGrado,$idSeccion);
            if (!$idPeriodoEvaluacion) throw new Exception('Seleccione el periodo de evaluación');
            $stmt=$conn->prepare("SELECT e.id_estudiante,e.codigo_estudiante,e.nombre,e.apellido,e.dni
                FROM estudiante e WHERE e.id_grado=? AND e.id_seccion=? AND (e.estado IS NULL OR LOWER(e.estado)='activo')
                ORDER BY TRIM(UPPER(e.apellido)) ASC, TRIM(UPPER(e.nombre)) ASC");
            $stmt->execute([$idGrado,$idSeccion]);
            $estudiantes=$stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmtNotas=$conn->prepare("SELECT n.id_estudiante,n.evaluacion,n.calificacion FROM nota n
                INNER JOIN estudiante e ON n.id_estudiante=e.id_estudiante
                WHERE n.id_curso_docente=? AND n.id_periodo_evaluacion=? AND n.id_docente=?
                  AND e.id_grado=? AND e.id_seccion=?");
            $stmtNotas->execute([$idCursoDocente,$idPeriodoEvaluacion,$user['id_docente'],$idGrado,$idSeccion]);
            $notas=[];
            foreach($stmtNotas->fetchAll(PDO::FETCH_ASSOC) as $n) $notas[$n['id_estudiante']][$n['evaluacion']]=$n['calificacion'];
            echo json_encode(['success'=>true,'estudiantes'=>$estudiantes,'notas'=>$notas,'evaluaciones'=>nombresEvaluacionesPlanilla()]);
            break;

        case 'planilla_guardar':
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden registrar esta planilla');
            $data=json_decode(file_get_contents('php://input'),true);
            if (!$data) throw new Exception('No se recibieron calificaciones');
            validarAsignacionPlanilla($conn,$user,$data['id_curso_docente']??null,$data['id_grado']??null,$data['id_seccion']??null);
            if (empty($data['id_periodo_evaluacion'])) throw new Exception('Seleccione el periodo de evaluación');
            $permitidas=nombresEvaluacionesPlanilla();
            $conn->beginTransaction();
            $stmtAlumno=$conn->prepare("SELECT COUNT(*) FROM estudiante WHERE id_estudiante=? AND id_grado=? AND id_seccion=? AND (estado IS NULL OR LOWER(estado)='activo')");
            $stmtBuscar=$conn->prepare("SELECT id_nota FROM nota WHERE id_estudiante=? AND id_curso_docente=? AND id_periodo_evaluacion=? AND evaluacion=? AND id_docente=? LIMIT 1");
            $stmtInsert=$conn->prepare("INSERT INTO nota(id_estudiante,id_curso_docente,id_periodo_evaluacion,evaluacion,calificacion,id_docente) VALUES(?,?,?,?,?,?)");
            $stmtUpdate=$conn->prepare("UPDATE nota SET calificacion=?,fecha_registro=CURRENT_TIMESTAMP WHERE id_nota=?");
            $nuevas=0;
            $modificadas=0;
            foreach(($data['calificaciones']??[]) as $fila) {
                $idEstudiante=$fila['id_estudiante']??null;
                $stmtAlumno->execute([$idEstudiante,$data['id_grado'],$data['id_seccion']]);
                if (!(int)$stmtAlumno->fetchColumn()) throw new Exception('Un estudiante no pertenece al grado y sección asignados');
                foreach(($fila['notas']??[]) as $evaluacion=>$calificacion) {
                    if (!in_array($evaluacion,$permitidas,true)) throw new Exception('La evaluación no está permitida');
                    if ($calificacion==='' || $calificacion===null) continue;
                    if (!is_numeric($calificacion) || $calificacion<0 || $calificacion>20) throw new Exception('Todas las calificaciones deben estar entre 0 y 20');
                    $stmtBuscar->execute([$idEstudiante,$data['id_curso_docente'],$data['id_periodo_evaluacion'],$evaluacion,$user['id_docente']]);
                    $idNota=$stmtBuscar->fetchColumn();
                    if ($idNota) { $stmtUpdate->execute([$calificacion,$idNota]); $modificadas++; }
                    else { $stmtInsert->execute([$idEstudiante,$data['id_curso_docente'],$data['id_periodo_evaluacion'],$evaluacion,$calificacion,$user['id_docente']]); $nuevas++; }
                }
            }
            $conn->commit();
            echo json_encode(['success'=>true,'message'=>"Planilla guardada: $nuevas nota(s) nueva(s) y $modificadas nota(s) actualizada(s)",'nuevas'=>$nuevas,'modificadas'=>$modificadas]);
            break;

        case 'obtener':
            $where = [];
            $params = [];
            if (esAlumno($user) && $user['id_estudiante']) {
                $where[] = 'n.id_estudiante = ?';
                $params[] = $user['id_estudiante'];
            } elseif (esDocente($user) && $user['id_docente']) {
                $where[] = 'n.id_docente = ?';
                $params[] = $user['id_docente'];
            }
            $sql = "SELECT n.id_nota, n.id_estudiante, n.id_curso_docente, n.id_periodo_evaluacion,
                                         n.evaluacion, n.calificacion, n.fecha_registro, n.id_docente,
                                         CONCAT(e.nombre, ' ', e.apellido) as estudiante,
                                         c.nombre as curso, pe.nombre as periodo_evaluacion,
                                         CONCAT(u.nombres, ' ', u.apellidos) as docente
                                  FROM nota n
                                  INNER JOIN estudiante e ON n.id_estudiante = e.id_estudiante
                                  INNER JOIN curso_docente cd ON n.id_curso_docente = cd.id_curso_docente
                                  INNER JOIN curso c ON cd.id_curso = c.id_curso
                                  INNER JOIN periodo_evaluacion pe ON n.id_periodo_evaluacion = pe.id_periodo_evaluacion
                                  INNER JOIN docente d ON n.id_docente = d.id_docente
                                  INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                  " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                                  ORDER BY n.fecha_registro DESC, estudiante";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'readonly' => esAlumno($user)]);
            break;

        case 'combo':
            asegurarPeriodoEvaluacion($conn);
            if (esAlumno($user) && $user['id_estudiante']) {
                $stmtEst = $conn->prepare("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE id_estudiante=?");
                $stmtEst->execute([$user['id_estudiante']]);
                $estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);
            } elseif (esDocente($user) && $user['id_docente']) {
                $stmtEst = $conn->prepare("SELECT DISTINCT e.id_estudiante, e.nombre, e.apellido, e.dni
                                           FROM estudiante e
                                           INNER JOIN horario h ON e.id_grado = h.id_grado AND e.id_seccion = h.id_seccion
                                           INNER JOIN curso_docente cd ON h.id_curso_docente = cd.id_curso_docente
                                           WHERE cd.id_docente = ?
                                             AND (e.estado IS NULL OR LOWER(e.estado) = 'activo')
                                           ORDER BY e.apellido, e.nombre");
                $stmtEst->execute([$user['id_docente']]);
                $estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $estudiantes = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE estado = 'activo' ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
            }
            $whereCursos = esDocente($user) && $user['id_docente'] ? 'WHERE d.id_docente = ' . (int) $user['id_docente'] : '';
            $cursos = $conn->query("SELECT cd.id_curso_docente, c.nombre as curso, d.id_docente, CONCAT(u.nombres, ' ', u.apellidos) as docente
                                    FROM curso_docente cd
                                    INNER JOIN curso c ON cd.id_curso = c.id_curso
                                    INNER JOIN docente d ON cd.id_docente = d.id_docente
                                    INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                    $whereCursos
                                    ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
            $periodos = $conn->query("SELECT id_periodo_evaluacion, nombre FROM periodo_evaluacion ORDER BY id_periodo_evaluacion")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estudiantes' => $estudiantes, 'cursos' => $cursos, 'periodos' => $periodos]);
            break;

        case 'crear':
            if (esAlumno($user)) throw new Exception('Los alumnos solo pueden consultar notas');
            $data = json_decode(file_get_contents('php://input'), true);
            validarNota($data);
            $idDocente = obtenerDocentePorCursoDocente($conn, $data['id_curso_docente']);
            validarPermisoDocenteNota($user, $idDocente);
            $stmt = $conn->prepare("INSERT INTO nota (id_estudiante, id_curso_docente, id_periodo_evaluacion, evaluacion, calificacion, id_docente) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['id_estudiante'], $data['id_curso_docente'], $data['id_periodo_evaluacion'], $data['evaluacion'], $data['calificacion'], $idDocente]);
            echo json_encode(['success' => true, 'message' => 'Nota registrada correctamente']);
            break;

        case 'actualizar':
            if (esAlumno($user)) throw new Exception('Los alumnos solo pueden consultar notas');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_nota'])) throw new Exception('No se recibio la nota a actualizar');
            validarNota($data);
            $idDocente = obtenerDocentePorCursoDocente($conn, $data['id_curso_docente']);
            validarPermisoDocenteNota($user, $idDocente);
            validarPropiedadNota($conn, $data['id_nota'], $user);
            $stmt = $conn->prepare("UPDATE nota SET id_estudiante=?, id_curso_docente=?, id_periodo_evaluacion=?, evaluacion=?, calificacion=?, id_docente=? WHERE id_nota=?");
            $stmt->execute([$data['id_estudiante'], $data['id_curso_docente'], $data['id_periodo_evaluacion'], $data['evaluacion'], $data['calificacion'], $idDocente, $data['id_nota']]);
            echo json_encode(['success' => true, 'message' => 'Nota actualizada correctamente']);
            break;

        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede eliminar notas');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_nota'])) throw new Exception('No se recibio la nota a eliminar');
            $stmt = $conn->prepare("DELETE FROM nota WHERE id_nota=?");
            $stmt->execute([$data['id_nota']]);
            echo json_encode(['success' => true, 'message' => 'Nota eliminada']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function nombresEvaluacionesPlanilla(): array
{
    $nombres=[];
    for($i=1;$i<=10;$i++) $nombres[]="Evaluación $i";
    $nombres[]='Práctica calificada 1';
    $nombres[]='Práctica calificada 2';
    $nombres[]='Examen final';
    return $nombres;
}

function validarAsignacionPlanilla(PDO $conn,array $user,int|string|null $idCursoDocente,int|string|null $idGrado,int|string|null $idSeccion): void
{
    if (!$idCursoDocente || !$idGrado || !$idSeccion) throw new Exception('Seleccione curso, grado y sección');
    $stmt=$conn->prepare("SELECT COUNT(*) FROM horario h INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente
        WHERE h.id_curso_docente=? AND h.id_grado=? AND h.id_seccion=? AND cd.id_docente=?");
    $stmt->execute([$idCursoDocente,$idGrado,$idSeccion,$user['id_docente']]);
    if (!(int)$stmt->fetchColumn()) throw new Exception('Este curso, grado y sección no están asignados al profesor');
}

function validarNota(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['id_estudiante', 'id_curso_docente', 'id_periodo_evaluacion', 'evaluacion', 'calificacion'] as $campo) {
        if ($data[$campo] === '' || !isset($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
    if ($data['calificacion'] < 0 || $data['calificacion'] > 20) throw new Exception('La calificacion debe estar entre 0 y 20');
}

function obtenerDocentePorCursoDocente(PDO $conn, int|string $idCursoDocente): int
{
    $stmt = $conn->prepare("SELECT id_docente FROM curso_docente WHERE id_curso_docente=?");
    $stmt->execute([$idCursoDocente]);
    $idDocente = $stmt->fetchColumn();
    if (!$idDocente) throw new Exception('El curso docente seleccionado no existe');
    return (int) $idDocente;
}

function validarPermisoDocenteNota(array $user, int $idDocenteCurso): void
{
    if (esAdmin($user)) return;
    if (esDocente($user) && (int) ($user['id_docente'] ?? 0) === $idDocenteCurso) return;
    throw new Exception('No tiene permiso para registrar notas en este curso');
}

function validarPropiedadNota(PDO $conn, int|string $idNota, array $user): void
{
    if (esAdmin($user)) return;
    if (!esDocente($user)) throw new Exception('No tiene permiso para actualizar esta nota');

    $stmt = $conn->prepare("SELECT id_docente FROM nota WHERE id_nota=?");
    $stmt->execute([$idNota]);
    $idDocente = $stmt->fetchColumn();
    if (!$idDocente || (int) $idDocente !== (int) ($user['id_docente'] ?? 0)) {
        throw new Exception('Solo puede actualizar notas registradas para sus cursos');
    }
}

function asegurarPeriodoEvaluacion(PDO $conn): void
{
    $total = (int) $conn->query("SELECT COUNT(*) FROM periodo_evaluacion")->fetchColumn();
    if ($total > 0) return;
    $idPeriodo = (int) $conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1")->fetchColumn();
    if (!$idPeriodo) return;
    $stmt = $conn->prepare("INSERT INTO periodo_evaluacion (nombre, id_periodo) VALUES (?, ?)");
    foreach (['Bimestre 1', 'Bimestre 2', 'Bimestre 3', 'Bimestre 4'] as $nombre) {
        $stmt->execute([$nombre, $idPeriodo]);
    }
}
?>
