<?php
$host = 'localhost';
$dbname = 'sistema_escolar_v2';
$username = 'root';
$password = 'admin';
$port = 3306;


try {
    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    // La línea del echo "¡Magia pura!..." DEBE estar borrada o comentada:
    // echo "¡Magia pura! Conexión exitosa..."; 

} catch(PDOException $e) {
    die(json_encode(['success' => false, 'error' => "Error crítico de conexión: " . $e->getMessage()]));
}
?>
