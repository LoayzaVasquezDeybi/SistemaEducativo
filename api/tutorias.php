<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/auth.php';

$user = usuarioActual($conn);
$action = $_GET['action'] ?? $_POST['action'] ?? 'obtener';

function asegurarTablaTutorSeccion(PDO $conn): void {
    $conn->exec("CREATE TABLE IF NOT EXISTS tutor_seccion (
        id_tutor_seccion INT AUTO_INCREMENT PRIMARY KEY,
        id_docente INT NOT NULL,
        id_grado INT NOT NULL,
        id_seccion INT NOT NULL,
        id_periodo INT NOT NULL,
        fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tutor_seccion_periodo (id_grado, id_seccion, id_periodo),
        UNIQUE KEY uq_docente_tutor_periodo (id_docente, id_periodo),
        CONSTRAINT fk_tutor_docente FOREIGN KEY (id_docente) REFERENCES docente(id_docente) ON DELETE CASCADE,
        CONSTRAINT fk_tutor_grado FOREIGN KEY (id_grado) REFERENCES grado(id_grado),
        CONSTRAINT fk_tutor_seccion FOREIGN KEY (id_seccion) REFERENCES seccion(id_seccion),
        CONSTRAINT fk_tutor_periodo FOREIGN KEY (id_periodo) REFERENCES periodo_academico(id_periodo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

try {
    asegurarTablaTutorSeccion($conn);
    if (!$user['id_usuario']) throw new Exception('Debe iniciar sesión');
    $idPeriodo = obtenerPeriodoTutor($conn);

    switch ($action) {
        case 'obtener':
            if (esAdmin($user)) {
                $stmt = $conn->prepare("SELECT ts.id_tutor_seccion, ts.id_docente, ts.id_grado, ts.id_seccion,
                    g.nombre AS grado, s.nombre AS seccion, CONCAT(u.apellidos, ', ', u.nombres) AS tutor,
                    (SELECT COUNT(*) FROM estudiante e WHERE e.id_grado=ts.id_grado AND e.id_seccion=ts.id_seccion AND (e.estado IS NULL OR LOWER(e.estado)='activo')) AS alumnos
                    FROM tutor_seccion ts
                    INNER JOIN docente d ON ts.id_docente=d.id_docente
                    INNER JOIN usuario u ON d.id_usuario=u.id_usuario
                    INNER JOIN grado g ON ts.id_grado=g.id_grado
                    INNER JOIN seccion s ON ts.id_seccion=s.id_seccion
                    WHERE ts.id_periodo=? ORDER BY g.id_grado,s.nombre");
                $stmt->execute([$idPeriodo]);
                echo json_encode(['success'=>true,'es_admin'=>true,'asignaciones'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
            }
            if (!esDocente($user) || !$user['id_docente']) throw new Exception('Solo los tutores pueden acceder');
            $stmt = $conn->prepare("SELECT ts.id_tutor_seccion,ts.id_grado,ts.id_seccion,g.nombre AS grado,s.nombre AS seccion
                FROM tutor_seccion ts INNER JOIN grado g ON ts.id_grado=g.id_grado INNER JOIN seccion s ON ts.id_seccion=s.id_seccion
                WHERE ts.id_docente=? AND ts.id_periodo=? LIMIT 1");
            $stmt->execute([$user['id_docente'],$idPeriodo]);
            $asignacion=$stmt->fetch(PDO::FETCH_ASSOC);
            $alumnos=[];
            if ($asignacion) {
                $stmtAlumnos=$conn->prepare("SELECT id_estudiante,codigo_estudiante,nombre,apellido,dni FROM estudiante
                    WHERE id_grado=? AND id_seccion=? AND (estado IS NULL OR LOWER(estado)='activo')
                    ORDER BY TRIM(UPPER(apellido)) ASC, TRIM(UPPER(nombre)) ASC");
                $stmtAlumnos->execute([$asignacion['id_grado'],$asignacion['id_seccion']]);
                $alumnos=$stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success'=>true,'es_admin'=>false,'asignacion'=>$asignacion,'alumnos'=>$alumnos]);
            break;

        case 'combos':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede asignar tutores');
            echo json_encode(['success'=>true,
                'docentes'=>$conn->query("SELECT d.id_docente,CONCAT(u.apellidos, ', ', u.nombres) AS nombre FROM docente d INNER JOIN usuario u ON d.id_usuario=u.id_usuario WHERE u.id_estado_usuario=1 ORDER BY u.apellidos,u.nombres")->fetchAll(PDO::FETCH_ASSOC),
                'grados'=>$conn->query("SELECT id_grado,nombre FROM grado ORDER BY id_grado")->fetchAll(PDO::FETCH_ASSOC),
                'secciones'=>$conn->query("SELECT id_seccion,nombre FROM seccion ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'guardar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede asignar tutores');
            $data=json_decode(file_get_contents('php://input'),true);
            foreach (['id_docente','id_grado','id_seccion'] as $campo) if (empty($data[$campo])) throw new Exception('Complete todos los campos');
            $stmtOcupado=$conn->prepare("SELECT COUNT(*) FROM tutor_seccion WHERE id_docente=? AND id_periodo=? AND NOT (id_grado=? AND id_seccion=?)");
            $stmtOcupado->execute([$data['id_docente'],$idPeriodo,$data['id_grado'],$data['id_seccion']]);
            if ((int)$stmtOcupado->fetchColumn()>0) throw new Exception('El docente ya es tutor de otra sección en este periodo');
            $stmt=$conn->prepare("INSERT INTO tutor_seccion (id_docente,id_grado,id_seccion,id_periodo) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE id_docente=VALUES(id_docente),fecha_asignacion=CURRENT_TIMESTAMP");
            try { $stmt->execute([$data['id_docente'],$data['id_grado'],$data['id_seccion'],$idPeriodo]); }
            catch (PDOException $e) { if ($e->getCode()==='23000') throw new Exception('El docente ya es tutor de otra sección en este periodo'); throw $e; }
            echo json_encode(['success'=>true,'message'=>'Tutor asignado correctamente']);
            break;

        case 'eliminar':
            if (!esAdmin($user)) throw new Exception('Solo el administrador puede retirar tutores');
            $data=json_decode(file_get_contents('php://input'),true);
            if (empty($data['id_tutor_seccion'])) throw new Exception('Asignación no válida');
            $stmt=$conn->prepare("DELETE FROM tutor_seccion WHERE id_tutor_seccion=? AND id_periodo=?");
            $stmt->execute([$data['id_tutor_seccion'],$idPeriodo]);
            echo json_encode(['success'=>true,'message'=>'Asignación retirada']);
            break;
        default: throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

function obtenerPeriodoTutor(PDO $conn): int {
    $id=$conn->query("SELECT id_periodo FROM periodo_academico WHERE LOWER(estado)='activo' ORDER BY id_periodo DESC LIMIT 1")->fetchColumn();
    if (!$id) $id=$conn->query("SELECT id_periodo FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1")->fetchColumn();
    if (!$id) throw new Exception('No existe un periodo académico');
    return (int)$id;
}
