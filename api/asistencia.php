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
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden registrar asistencia por curso');
            $stmt=$conn->prepare("SELECT DISTINCT cd.id_curso_docente,h.id_grado,h.id_seccion,c.nombre AS curso,g.nombre AS grado,s.nombre AS seccion
                FROM horario h INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente
                INNER JOIN curso c ON cd.id_curso=c.id_curso INNER JOIN grado g ON h.id_grado=g.id_grado INNER JOIN seccion s ON h.id_seccion=s.id_seccion
                WHERE cd.id_docente=? ORDER BY g.id_grado,s.nombre,c.nombre");
            $stmt->execute([$user['id_docente']]);
            echo json_encode(['success'=>true,'asignaciones'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'planilla_obtener':
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden registrar asistencia por curso');
            $idCurso=$_GET['id_curso_docente']??null; $idGrado=$_GET['id_grado']??null; $idSeccion=$_GET['id_seccion']??null; $fecha=$_GET['fecha']??null;
            validarAsignacionAsistencia($conn,$user,$idCurso,$idGrado,$idSeccion);
            validarFechaAsistencia($fecha);
            $stmt=$conn->prepare("SELECT e.id_estudiante,e.codigo_estudiante,e.nombre,e.apellido,e.dni,a.id_asistencia,a.estado_asistencia,a.observacion
                FROM estudiante e LEFT JOIN asistencia a ON a.id_estudiante=e.id_estudiante AND a.id_curso_docente=? AND a.fecha=?
                WHERE e.id_grado=? AND e.id_seccion=? AND (e.estado IS NULL OR LOWER(e.estado)='activo')
                ORDER BY TRIM(UPPER(e.apellido)) ASC,TRIM(UPPER(e.nombre)) ASC");
            $stmt->execute([$idCurso,$fecha,$idGrado,$idSeccion]);
            echo json_encode(['success'=>true,'estudiantes'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'planilla_guardar':
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los profesores pueden registrar asistencia por curso');
            $data=json_decode(file_get_contents('php://input'),true);
            if (!$data) throw new Exception('No se recibió la asistencia');
            validarAsignacionAsistencia($conn,$user,$data['id_curso_docente']??null,$data['id_grado']??null,$data['id_seccion']??null);
            validarFechaAsistencia($data['fecha']??null);
            $estados=['Presente','Tardanza','Falta']; $nuevos=0; $actualizados=0;
            $conn->beginTransaction();
            $stmtAlumno=$conn->prepare("SELECT COUNT(*) FROM estudiante WHERE id_estudiante=? AND id_grado=? AND id_seccion=? AND (estado IS NULL OR LOWER(estado)='activo')");
            $stmtGuardar=$conn->prepare("INSERT INTO asistencia(id_estudiante,id_curso_docente,fecha,estado_asistencia,observacion) VALUES(?,?,?,?,?)
                ON DUPLICATE KEY UPDATE estado_asistencia=VALUES(estado_asistencia),observacion=VALUES(observacion)");
            $stmtExiste=$conn->prepare("SELECT COUNT(*) FROM asistencia WHERE id_estudiante=? AND id_curso_docente=? AND fecha=?");
            foreach(($data['asistencias']??[]) as $fila) {
                if (!in_array($fila['estado_asistencia']??'',$estados,true)) throw new Exception('Estado de asistencia no válido');
                $stmtAlumno->execute([$fila['id_estudiante']??null,$data['id_grado'],$data['id_seccion']]);
                if (!(int)$stmtAlumno->fetchColumn()) throw new Exception('Un estudiante no pertenece al grado y sección asignados');
                $stmtExiste->execute([$fila['id_estudiante'],$data['id_curso_docente'],$data['fecha']]);
                $existe=(int)$stmtExiste->fetchColumn()>0;
                $stmtGuardar->execute([$fila['id_estudiante'],$data['id_curso_docente'],$data['fecha'],$fila['estado_asistencia'],trim($fila['observacion']??'')?:null]);
                $existe?$actualizados++:$nuevos++;
            }
            $conn->commit();
            echo json_encode(['success'=>true,'message'=>"Asistencia guardada: $nuevos registro(s) nuevo(s) y $actualizados actualizado(s)"]);
            break;

        case 'obtener':
            $fecha = $_GET['fecha'] ?? null;
            $sql = "SELECT a.id_asistencia, a.id_estudiante, a.id_curso_docente, a.fecha, a.estado_asistencia, a.observacion,
                           CONCAT(e.nombre, ' ', e.apellido) as estudiante, c.nombre as curso
                    FROM asistencia a
                    INNER JOIN estudiante e ON a.id_estudiante = e.id_estudiante
                    INNER JOIN curso_docente cd ON a.id_curso_docente = cd.id_curso_docente
                    INNER JOIN curso c ON cd.id_curso = c.id_curso";
            $params = [];
            $filters = [];
            if ($fecha) {
                $filters[] = "a.fecha = ?";
                $params[] = $fecha;
            }
            if (esAlumno($user) && $user['id_estudiante']) {
                $filters[] = "a.id_estudiante = ?";
                $params[] = $user['id_estudiante'];
            } elseif (esDocente($user) && $user['id_docente']) {
                $filters[] = "cd.id_docente = ?";
                $params[] = $user['id_docente'];
            } elseif (esAdmin($user)) {
                // El administrador ve toda la asistencia
            }
            if ($filters) $sql .= " WHERE " . implode(" AND ", $filters);
            $sql .= " ORDER BY a.fecha DESC, estudiante";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'readonly' => esAlumno($user)]);
            break;

        case 'combo':
            if (esAlumno($user) && $user['id_estudiante']) {
                $stmtEst = $conn->prepare("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE id_estudiante=?");
                $stmtEst->execute([$user['id_estudiante']]);
                $estudiantes = $stmtEst->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $estudiantes = $conn->query("SELECT id_estudiante, nombre, apellido, dni FROM estudiante WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll(PDO::FETCH_ASSOC);
            }
            $whereCursos = '';
            if (esDocente($user) && $user['id_docente']) {
                $whereCursos = 'WHERE d.id_docente = ' . (int) $user['id_docente'];
            } elseif (esAlumno($user) && $user['id_estudiante']) {
                $whereCursos = 'WHERE EXISTS (
                    SELECT 1
                    FROM asistencia a2
                    WHERE a2.id_curso_docente = cd.id_curso_docente
                      AND a2.id_estudiante = ' . (int) $user['id_estudiante'] . '
                )';
            }
            $cursos = $conn->query("SELECT cd.id_curso_docente, c.nombre as curso, CONCAT(u.nombres, ' ', u.apellidos) as docente
                                    FROM curso_docente cd
                                    INNER JOIN curso c ON cd.id_curso = c.id_curso
                                    INNER JOIN docente d ON cd.id_docente = d.id_docente
                                    INNER JOIN usuario u ON d.id_usuario = u.id_usuario
                                    $whereCursos
                                    ORDER BY c.nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estudiantes' => $estudiantes, 'cursos' => $cursos]);
            break;

        case 'crear':
            if (esAlumno($user)) throw new Exception('Los alumnos solo pueden consultar asistencia');
            $data = json_decode(file_get_contents('php://input'), true);
            validarAsistencia($data);
            validarPermisoRegistroAsistencia($conn,$user,$data['id_estudiante'],$data['id_curso_docente']);
            $stmt = $conn->prepare("INSERT INTO asistencia (id_estudiante, id_curso_docente, fecha, estado_asistencia, observacion) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['id_estudiante'], $data['id_curso_docente'], $data['fecha'], $data['estado_asistencia'], $data['observacion'] ?? null]);
            echo json_encode(['success' => true, 'message' => 'Asistencia registrada correctamente']);
            break;

        case 'actualizar':
            if (esAlumno($user)) throw new Exception('Los alumnos solo pueden consultar asistencia');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_asistencia'])) throw new Exception('No se recibio la asistencia');
            validarAsistencia($data);
            validarPropiedadAsistencia($conn,$user,$data['id_asistencia']);
            validarPermisoRegistroAsistencia($conn,$user,$data['id_estudiante'],$data['id_curso_docente']);
            $stmt = $conn->prepare("UPDATE asistencia SET id_estudiante=?, id_curso_docente=?, fecha=?, estado_asistencia=?, observacion=? WHERE id_asistencia=?");
            $stmt->execute([$data['id_estudiante'], $data['id_curso_docente'], $data['fecha'], $data['estado_asistencia'], $data['observacion'] ?? null, $data['id_asistencia']]);
            echo json_encode(['success' => true, 'message' => 'Asistencia actualizada correctamente']);
            break;

        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede eliminar asistencia');
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id_asistencia'])) throw new Exception('No se recibio la asistencia');
            $stmt = $conn->prepare("DELETE FROM asistencia WHERE id_asistencia=?");
            $stmt->execute([$data['id_asistencia']]);
            echo json_encode(['success' => true, 'message' => 'Asistencia eliminada']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Accion no valida']);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function validarAsignacionAsistencia(PDO $conn,array $user,int|string|null $idCurso,int|string|null $idGrado,int|string|null $idSeccion): void
{
    if (!$idCurso || !$idGrado || !$idSeccion) throw new Exception('Seleccione curso, grado y sección');
    $stmt=$conn->prepare("SELECT COUNT(*) FROM horario h INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente
        WHERE h.id_curso_docente=? AND h.id_grado=? AND h.id_seccion=? AND cd.id_docente=?");
    $stmt->execute([$idCurso,$idGrado,$idSeccion,$user['id_docente']]);
    if (!(int)$stmt->fetchColumn()) throw new Exception('Este curso, grado y sección no están asignados al profesor');
}

function validarFechaAsistencia(?string $fecha): void
{
    $d=$fecha?DateTime::createFromFormat('Y-m-d',$fecha):false;
    if (!$d || $d->format('Y-m-d')!==$fecha) throw new Exception('Seleccione una fecha válida');
    if ($fecha>date('Y-m-d')) throw new Exception('No se puede registrar asistencia en una fecha futura');
}

function validarPermisoRegistroAsistencia(PDO $conn,array $user,int|string $idEstudiante,int|string $idCurso): void
{
    if (esAdmin($user)) return;
    if (!esDocente($user) || !$user['id_docente']) throw new Exception('No tiene permiso para registrar asistencia');
    $stmt=$conn->prepare("SELECT COUNT(*) FROM estudiante e INNER JOIN horario h ON h.id_grado=e.id_grado AND h.id_seccion=e.id_seccion
        INNER JOIN curso_docente cd ON h.id_curso_docente=cd.id_curso_docente
        WHERE e.id_estudiante=? AND h.id_curso_docente=? AND cd.id_docente=?");
    $stmt->execute([$idEstudiante,$idCurso,$user['id_docente']]);
    if (!(int)$stmt->fetchColumn()) throw new Exception('El estudiante no pertenece al curso, grado y sección asignados al profesor');
}

function validarPropiedadAsistencia(PDO $conn,array $user,int|string $idAsistencia): void
{
    if (esAdmin($user)) return;
    if (!esDocente($user) || !$user['id_docente']) throw new Exception('No tiene permiso para modificar asistencia');
    $stmt=$conn->prepare("SELECT COUNT(*) FROM asistencia a INNER JOIN curso_docente cd ON a.id_curso_docente=cd.id_curso_docente
        WHERE a.id_asistencia=? AND cd.id_docente=?");
    $stmt->execute([$idAsistencia,$user['id_docente']]);
    if (!(int)$stmt->fetchColumn()) throw new Exception('Solo puede modificar la asistencia de sus propios cursos');
}

function validarAsistencia(?array $data): void
{
    if (!$data) throw new Exception('No se recibieron datos validos');
    foreach (['id_estudiante', 'id_curso_docente', 'fecha', 'estado_asistencia'] as $campo) {
        if (empty($data[$campo])) throw new Exception("El campo $campo es obligatorio");
    }
}
?>
