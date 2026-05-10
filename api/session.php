<?php
session_start();
header('Content-Type: application/json');

// Verificamos si existe la variable de sesión
if (isset($_SESSION['usuario_id'])) {
    echo json_encode([
        'success' => true,
        'usuario' => [
            'id' => $_SESSION['usuario_id'],
            'nombres' => $_SESSION['nombres'],
            'apellidos' => $_SESSION['apellidos'],
            'rol' => $_SESSION['rol']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No hay sesión activa']);
}
?>