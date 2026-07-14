<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function usuarioActual(PDO $conn): array
{
    $idUsuario = $_SESSION['usuario_id'] ?? null;
    if (!$idUsuario) {
        return ['id_usuario' => null, 'id_rol' => null, 'rol_nombre' => 'Invitado'];
    }

    $stmt = $conn->prepare("SELECT u.id_usuario, u.id_rol, r.nombre as rol_nombre
                            FROM usuario u
                            INNER JOIN rol r ON u.id_rol = r.id_rol
                            WHERE u.id_usuario = ?");
    $stmt->execute([$idUsuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id_usuario' => $idUsuario, 'id_rol' => $_SESSION['rol'] ?? null, 'rol_nombre' => 'Usuario'];

    $stmtEst = $conn->prepare("SELECT id_estudiante FROM estudiante WHERE id_usuario = ?");
    $stmtEst->execute([$idUsuario]);
    $user['id_estudiante'] = $stmtEst->fetchColumn() ?: null;
    if (!$user['id_estudiante']) {
        $stmtEst = $conn->prepare("SELECT e.id_estudiante
                                   FROM estudiante e
                                   INNER JOIN usuario u ON e.dni = u.dni
                                   WHERE u.id_usuario = ?");
        $stmtEst->execute([$idUsuario]);
        $user['id_estudiante'] = $stmtEst->fetchColumn() ?: null;
    }

    $stmtDoc = $conn->prepare("SELECT id_docente FROM docente WHERE id_usuario = ?");
    $stmtDoc->execute([$idUsuario]);
    $user['id_docente'] = $stmtDoc->fetchColumn() ?: null;

    $user['rol_key'] = strtolower($user['rol_nombre']);
    return $user;
}

function esAdmin(array $user): bool
{
    return (int) ($user['id_rol'] ?? 0) === 1 || str_contains($user['rol_key'] ?? '', 'admin');
}

function esDocente(array $user): bool
{
    return str_contains($user['rol_key'] ?? '', 'docente');
}

function esAlumno(array $user): bool
{
    return str_contains($user['rol_key'] ?? '', 'alumno') || str_contains($user['rol_key'] ?? '', 'estudiante');
}

function esRecepcionista(array $user): bool
{
    return str_contains($user['rol_key'] ?? '', 'recepcion');
}
?>
