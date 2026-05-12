<?php
$host = 'localhost';
$dbname = 'sistema_escolar_v2';
$username = 'root';
$password = '';
$port=3307;


try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // La línea del echo "¡Magia pura!..." DEBE estar borrada o comentada:
    // echo "¡Magia pura! Conexión exitosa..."; 

} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => "Error crítico de conexión: " . $e->getMessage()]));
}
?>