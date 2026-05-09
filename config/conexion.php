<?php
$host = 'localhost';
$dbname = 'sistema_escolar_v2';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // La línea del echo "¡Magia pura!..." DEBE estar borrada o comentada:
    // echo "¡Magia pura! Conexión exitosa..."; 

} catch(PDOException $e) {
    die("Error crítico de conexión: " . $e->getMessage());
}
?>