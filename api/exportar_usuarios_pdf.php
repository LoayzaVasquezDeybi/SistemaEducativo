<?php
session_start();
require_once '../config/conexion.php';

// Validar que el usuario tenga sesión activa
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Error 403: Acceso Prohibido. Debe iniciar sesión.");
}

// Consulta para obtener los datos de usuarios
$query = "SELECT
            UPPER(CONCAT(u.apellidos, ', ', u.nombres)) as nombres_completos,
            u.dni,
            UPPER(u.email) as email,
            UPPER(r.nombre) as rol,
            UPPER(IF(u.id_estado_usuario = 1, 'activo', 'inactivo')) as estado
          FROM usuario u
          LEFT JOIN rol r ON u.id_rol = r.id_rol
          ORDER BY u.apellidos, u.nombres";

try {
    $stmt = $conn->query($query);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($usuarios);
    $activos = count(array_filter($usuarios, fn($u) => $u['estado'] === 'ACTIVO'));

    // Obtener el periodo académico actual (se mantiene por consistencia con otros reportes, aunque no es directamente relevante para usuarios)
    $stmtPeriodo = $conn->query("SELECT anio FROM periodo_academico ORDER BY id_periodo DESC LIMIT 1");
    $periodo = $stmtPeriodo->fetch(PDO::FETCH_ASSOC);
    $anioLectivo = $periodo ? $periodo['anio'] : date('Y');
} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Padrón de Usuarios - IE 22237</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Mono&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --accent: #2563eb;
            --border: #cbd5e1;
            --bg-light: #f1f5f9;
            --margin-v: 20mm;
            --margin-h: 20mm;
        }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4; margin: var(--margin-v) var(--margin-h); }
        body { font-family: 'DM Sans', sans-serif; margin: 0; padding: 20px 0; color: #334155; background-color: #f8fafc; display: flex; flex-direction: column; align-items: center; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: var(--margin-v) var(--margin-h); margin: 20px auto; box-shadow: 0 0 20px rgba(0,0,0,0.1); position: relative; }
        .watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 100px; font-weight: 800; color: rgba(226, 232, 240, 0.2); z-index: 0; pointer-events: none; }
        .report-header { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo-placeholder { width: 50px; height: 50px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 20px; }
        .inst-info h1 { margin: 0; font-size: 20px; color: #1e293b; }
        .inst-info p { margin: 2px 0; font-size: 11px; color: #64748b; }
        .report-title { text-align: right; }
        .report-title h2 { margin: 0; color: var(--primary); font-size: 18px; letter-spacing: 0.5px; text-transform: uppercase; }
        .report-title p { margin: 2px 0; font-size: 10px; color: #64748b; font-weight: 600; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 40px; }
        .summary-card { background: var(--bg-light); border: 1px solid #e2e8f0; border-top: 3px solid var(--accent); border-radius: 6px; padding: 12px; text-align: center; }
        .summary-card span { font-size: 10px; text-transform: uppercase; color: #64748b; display: block; margin-bottom: 5px; font-weight: 700; }
        .summary-card strong { font-size: 20px; color: var(--primary); }
        table { width: 100%; border-collapse: collapse; margin-bottom: 60px; }
        th { background: #f8fafc; color: var(--primary); font-size: 10px; padding: 12px 10px; text-align: left; text-transform: uppercase; border-bottom: 2px solid var(--primary); }
        td { font-size: 11px; padding: 10px; border-bottom: 1px solid var(--border); color: #334155; }
        .text-bold { font-weight: 600; color: var(--primary); }
        .status { padding: 3px 8px; border-radius: 4px; font-size: 7.5px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .status-activo { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .status-inactivo { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .footer-sign { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; page-break-inside: avoid; }
        .metadata { font-size: 9px; color: #94a3b8; line-height: 1.5; }
        .sign-box { width: 180px; text-align: center; border-top: 1.5px solid var(--primary); padding-top: 8px; }
        .sign-box strong { display: block; font-size: 11px; color: #1e293b; }
        .sign-box span { font-size: 9px; color: #64748b; text-transform: uppercase; }
        @media print {
            body { background: white; padding: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="background: #0f172a; color: white; padding: 12px; text-align: center; font-size: 13px; position: sticky; top: 0; z-index: 100; margin-bottom: 20px; width: 100%;">
        Documento oficial generado. Seleccione <b>"Guardar como PDF"</b> en el destino de impresión.
    </div>

    <div class="page">
    <div class="watermark">IE 22237</div>

    <div class="report-header">
        <div class="brand">
            <div class="logo-placeholder">JY</div>
            <div class="inst-info">
                <h1>I.E. N° 22237</h1>
                <p>"José Yataco Pachas" — Chincha Baja</p>
                <p>Reporte de Usuarios del Sistema</p>
            </div>
        </div>
        <div class="report-title">
            <h2>Padrón de Usuarios</h2>
            <p>PERIODO LECTIVO <?php echo $anioLectivo; ?></p>
            <p>Fecha: <?php echo date('d/m/Y'); ?> | Hora: <?php echo date('H:i'); ?></p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card"><span>Total Usuarios</span><strong><?php echo $total; ?></strong></div>
        <div class="summary-card"><span>Usuarios Activos</span><strong><?php echo $activos; ?></strong></div>
        <div class="summary-card"><span>Tipo Reporte</span><strong>Institucional</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Apellidos y Nombres</th>
                <th>DNI</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $user): ?>
            <tr>
                <td class="text-bold"><?php echo htmlspecialchars($user['nombres_completos']); ?></td>
                <td><?php echo htmlspecialchars($user['dni']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['rol']); ?></td>
                <td>
                    <span class="status <?php echo ($user['estado'] == 'ACTIVO') ? 'status-activo' : 'status-inactivo'; ?>">
                        <?php echo htmlspecialchars($user['estado']); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-sign">
        <div class="metadata">
            Generado por: <?php echo $_SESSION['nombres'] ?? 'ADMINISTRADOR'; ?><br>
            Firma Digital: <?php echo strtoupper(substr(md5(time() . 'user'), 0, 12)); ?>
        </div>
        <div class="sign-box">
            <strong>Dirección General</strong><br>
            I.E. N° 22237
        </div>
    </div>
    </div>
</body></html>