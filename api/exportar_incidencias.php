<?php
session_start();
require_once '../config/conexion.php';

// Validar que el usuario tenga sesión activa
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    die("Error 403: Acceso Prohibido. Debe iniciar sesión.");
}

// Consulta para obtener los datos de incidencias
$query = "SELECT 
            DATE_FORMAT(i.fecha, '%d/%m/%Y') as fecha,
            UPPER(CONCAT(e.apellido, ', ', e.nombre)) as estudiante,
            UPPER(COALESCE(ti.nombre, 'SIN TIPO')) as tipo,
            i.descripcion,
            i.accion_tomada,
            UPPER(COALESCE(CONCAT(u.nombres, ' ', u.apellidos), 'SISTEMA')) as reportado_por
          FROM incidencia i
          INNER JOIN estudiante e ON i.id_estudiante = e.id_estudiante
          LEFT JOIN tipo_incidencia ti ON i.id_tipo_incidencia = ti.id_tipo_incidencia
          LEFT JOIN docente d ON i.id_docente = d.id_docente
          LEFT JOIN usuario u ON d.id_usuario = u.id_usuario
          ORDER BY i.fecha DESC, e.apellido, e.nombre";

try {
    $stmt = $conn->query($query);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($registros);

    // Obtener el periodo académico actual
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
    <title>Reporte de Incidencias - IE 22237</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #1e293b; 
            --accent: #2563eb; 
            --border: #cbd5e1; 
            --bg-light: #f1f5f9; 
        }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4; margin: 20mm; }
        body { font-family: 'DM Sans', sans-serif; margin: 0; padding: 20px 0; color: #334155; background-color: #f8fafc; display: flex; flex-direction: column; align-items: center; }
        .page { background: white; width: 210mm; min-height: 297mm; padding: 20mm; margin: 20px auto; box-shadow: 0 0 20px rgba(0,0,0,0.1); position: relative; }
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 20px; margin-bottom: 30px; }
        .brand { display: flex; align-items: center; gap: 15px; }
        .logo-placeholder { width: 50px; height: 50px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 20px; }
        .inst-info h1 { margin: 0; font-size: 20px; color: #1e293b; }
        .inst-info p { margin: 2px 0; font-size: 11px; color: #64748b; }
        .report-title { text-align: right; }
        .report-title h2 { margin: 0; color: var(--primary); font-size: 18px; text-transform: uppercase; }
        .report-title p { margin: 2px 0; font-size: 10px; color: #64748b; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th { background: #f8fafc; color: var(--primary); font-size: 10px; padding: 12px 10px; text-align: left; text-transform: uppercase; border-bottom: 2px solid var(--primary); }
        td { font-size: 11px; padding: 10px; border-bottom: 1px solid var(--border); color: #334155; vertical-align: top; }
        .text-bold { font-weight: 600; color: var(--primary); }
        .tipo-incidencia { padding: 3px 8px; border-radius: 4px; font-size: 7.5px; font-weight: 700; text-transform: uppercase; display: inline-block; background: #fee2e2; color: #b91c1c; }
        .tipo-academica { background: #dbeafe; color: #1e40af; }
        .tipo-salud { background: #dcfce7; color: #166534; }
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
        <div class="report-header">
            <div class="brand">
                <div class="logo-placeholder">JY</div>
                <div class="inst-info">
                    <h1>I.E. N° 22237</h1>
                    <p>"José Yataco Pachas" — Chincha Baja</p>
                    <p>Reporte de Control de Incidencias</p>
                </div>
            </div>
            <div class="report-title">
                <h2>Reporte General de Incidencias</h2>
                <p>PERIODO LECTIVO <?php echo $anioLectivo; ?></p>
                <p>Fecha: <?php echo date('d/m/Y'); ?> | Hora: <?php echo date('H:i'); ?></p>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Estudiante</th>
                    <th>Tipo</th>
                    <th style="width: 30%;">Descripción</th>
                    <th style="width: 25%;">Acción Tomada</th>
                    <th>Reportado por</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registros)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 20px;">No hay incidencias registradas para mostrar.</td></tr>
                <?php else: ?>
                    <?php foreach ($registros as $reg): 
                        $tipoClass = 'tipo-' . strtolower(str_replace(' ', '', $reg['tipo']));
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($reg['fecha']); ?></td>
                        <td class="text-bold"><?php echo htmlspecialchars($reg['estudiante']); ?></td>
                        <td>
                            <span class="tipo-incidencia <?php echo $tipoClass; ?>">
                                <?php echo htmlspecialchars($reg['tipo']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($reg['descripcion']); ?></td>
                        <td><?php echo htmlspecialchars($reg['accion_tomada']); ?></td>
                        <td><?php echo htmlspecialchars($reg['reportado_por']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="footer-sign">
            <div class="metadata">
                Generado por: <?php echo $_SESSION['nombres'] ?? 'Administrador'; ?><br>
                Total de registros: <?php echo $total; ?>
            </div>
            <div class="sign-box">
                <strong>Auxiliar de Educación</strong><br>
                I.E. N° 22237
            </div>
        </div>
    </div>
</body>
</html>