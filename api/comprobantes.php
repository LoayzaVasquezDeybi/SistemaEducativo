<?php
require_once __DIR__ . '/../config/conexion.php';
error_reporting(0);

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    if ($action !== 'generar') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Accion no valida']);
        exit;
    }

    $idPago = $_GET['id_pago'] ?? null;
    if (!$idPago) throw new Exception('No se recibio el pago');

    $stmt = $conn->prepare("SELECT p.id_pago, p.concepto, p.monto, p.fecha_pago, ep.nombre as estado_pago, mp.nombre as metodo,
                                   m.id_matricula, CONCAT(e.nombre, ' ', e.apellido) as estudiante, e.dni
                            FROM pago p
                            INNER JOIN matricula m ON p.id_matricula = m.id_matricula
                            INNER JOIN estudiante e ON m.id_estudiante = e.id_estudiante
                            LEFT JOIN estado_pago ep ON p.id_estado_pago = ep.id_estado_pago
                            LEFT JOIN metodo_pago mp ON p.id_metodo_pago = mp.id_metodo_pago
                            WHERE p.id_pago = ?");
    $stmt->execute([$idPago]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pago) throw new Exception('No se encontro el pago');

    $numero = 'COMP-' . str_pad((string) $idPago, 6, '0', STR_PAD_LEFT);
    $stmtComp = $conn->prepare("INSERT INTO comprobante (id_pago, numero_comprobante, tipo_comprobante, archivo_pdf)
                                VALUES (?, ?, 'Recibo', NULL)
                                ON DUPLICATE KEY UPDATE numero_comprobante = VALUES(numero_comprobante)");
    $stmtComp->execute([$idPago, $numero]);

    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset='utf-8'><title>Comprobante $numero</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:36px;color:#111} .box{border:1px solid #ccc;padding:24px;max-width:720px} h1{margin:0 0 8px} table{width:100%;border-collapse:collapse;margin-top:18px} td{padding:8px;border-bottom:1px solid #eee}</style>";
    echo "</head><body><div class='box'>";
    echo "<h1>Comprobante de Pago</h1><p><strong>Numero:</strong> " . htmlspecialchars($numero) . "</p>";
    echo "<table>";
    echo "<tr><td>Estudiante</td><td>" . htmlspecialchars($pago['estudiante']) . "</td></tr>";
    echo "<tr><td>DNI</td><td>" . htmlspecialchars($pago['dni']) . "</td></tr>";
    echo "<tr><td>Matricula</td><td>#" . htmlspecialchars($pago['id_matricula']) . "</td></tr>";
    echo "<tr><td>Concepto</td><td>" . htmlspecialchars($pago['concepto']) . "</td></tr>";
    echo "<tr><td>Monto</td><td>S/ " . number_format((float) $pago['monto'], 2) . "</td></tr>";
    echo "<tr><td>Fecha</td><td>" . htmlspecialchars($pago['fecha_pago']) . "</td></tr>";
    echo "<tr><td>Metodo</td><td>" . htmlspecialchars($pago['metodo'] ?? '-') . "</td></tr>";
    echo "<tr><td>Estado</td><td>" . htmlspecialchars($pago['estado_pago'] ?? '-') . "</td></tr>";
    echo "</table><p style='margin-top:24px'>Use la opcion imprimir del navegador para guardar como PDF.</p>";
    echo "</div><script>window.print();</script></body></html>";
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
