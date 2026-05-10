<?php
session_start();
session_destroy(); // Destruye todos los datos de la sesión

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
?>