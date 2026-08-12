<?php
/**
 * admin/reportes.php
 * Reportes del sistema con gráficas y exportación CSV/Excel/PDF
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once 'includes/funciones_pedidos.php';

validar_sesion_admin();

// Fechas por defecto: último mes
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
$tipo_reporte = $_GET['tipo']         ?? 'otorgados';

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) $fecha_inicio = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin))    $fecha_fin    = date('Y-m-d');

// Obtener datos según tipo de reporte
$datos_reporte = [];
$top_usuarios  = [];

if ($tipo_reporte === 'otorgados') {
    $datos_reporte = obtener_reporte_puntos_otorgados($fecha_inicio, $fecha_fin);
} elseif ($tipo_reporte === 'gastados') {
    $datos_reporte = obtener_reporte_puntos_gastados($fecha_inicio, $fecha_fin);
} elseif ($tipo_reporte === 'puntos_por_motivo') {
    $datos_reporte = obtener_puntos_otorgados_por_motivo($fecha_inicio, $fecha_fin);
} elseif ($tipo_reporte === 'dinero_gastado') {
    $datos_reporte = obtener_dinero_gastado_por_puntos_otorgados($fecha_inicio, $fecha_fin);
}

$top_usuarios = obtener_top_usuarios_puntos_gastados(10);

// Datos para gráfica de barras (puntos por día)
$datos_por_dia = [];
foreach ($datos_reporte as $row) {
    $fecha = date('d/m', strtotime($row['fecha_movimiento']));
    if (!isset($datos_por_dia[$fecha])) $datos_por_dia[$fecha] = 0;
    $datos_por_dia[$fecha] += abs($row['cantidad_puntos']);
}

// Exportar a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte_' . $tipo_reporte . '_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM para UTF-8 en Excel

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'Usuario', 'Cantidad Puntos', 'Tipo', 'Motivo', 'Descripción', 'Admin']);
    foreach ($datos_reporte as $row) {
        fputcsv($out, [
            $row['fecha_movimiento'],
            $row['usuario_nombre'],
            $row['cantidad_puntos'],
            $row['tipo_movimiento'],
            $row['motivo'] ?? '',
            $row['descripcion'] ?? '',
            $row['admin_nombre'] ?? 'Sistema'
        ]);
    }
    fclose($out);
    exit();
}

// Exportar a HTML para imprimir como PDF
if (isset($_GET['exportar']) && $_GET['exportar'] === 'pdf') {
    // Generar HTML imprimible (el usuario usa Ctrl+P para guardar como PDF)
    $titulo = $tipo_reporte === 'otorgados' ? 'Puntos Otorgados' : 'Puntos Gastados';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Reporte <?= $titulo ?></title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #dc2626; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #dc2626; color: white; padding: 8px; text-align: left; }
            td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) { background: #f8fafc; }
            .header { display: flex; justify-content: space-between; align-items: center; }
            @media print { button { display: none; } }
        </style>
    </head>
    <body>
        <div class="header">
            <div>
                <h1>Puntos Red - Reporte de <?= $titulo ?></h1>
                <p>Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></p>
            </div>
            <button onclick="window.print()">Imprimir / Guardar PDF</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Puntos</th>
                    <th>Motivo</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($datos_reporte as $row): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($row['fecha_movimiento'])) ?></td>
                    <td><?= sanitizar_salida($row['usuario_nombre']) ?></td>
                    <td><?= number_format($row['cantidad_puntos']) ?></td>
                    <td><?= sanitizar_salida($row['motivo'] ?? '') ?></td>
                    <td><?= sanitizar_salida($row['admin_nombre'] ?? 'Sistema') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><strong>Total registros:</strong> <?= count($datos_reporte) ?></p>
        <script>window.onload = () => window.print();</script>
    </body>
    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Reportes</h2>
        <p class="text-muted mb-0">Análisis de puntos del sistema</p>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="reportes.php" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Tipo de Reporte</label>
                    <select class="form-select" name="tipo">
                        <option value="otorgados" <?= $tipo_reporte === 'otorgados' ? 'selected' : '' ?>>Puntos Otorgados</option>
                        <option value="gastados"  <?= $tipo_reporte === 'gastados'  ? 'selected' : '' ?>>Puntos Gastados</option>
                        <option value="puntos_por_motivo" <?= $tipo_reporte === 'puntos_por_motivo' ? 'selected' : '' ?>>Cuántos Puntos por Motivo</option>
                        <option value="dinero_gastado" <?= $tipo_reporte === 'dinero_gastado' ? 'selected' : '' ?>>Cuánto Dinero Gastado por Puntos</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio"
                           value="<?= sanitizar_salida($fecha_inicio) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin"
                           value="<?= sanitizar_salida($fecha_fin) ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Generar Reporte</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Gráficas en formato 2x2 -->
    <div class="row g-4 mb-4">

        <!-- Gráfica 1: Puntos por Día (Barras) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <?= $tipo_reporte === 'otorgados' ? 'Puntos Otorgados' : ($tipo_reporte === 'gastados' ? 'Puntos Gastados' : ($tipo_reporte === 'puntos_por_motivo' ? 'Puntos por Motivo' : 'Dinero Gastado')) ?>
                    por Día
                </div>
                <div class="card-body">
                    <?php if (empty($datos_por_dia)): ?>
                    <div class="text-center text-muted py-4">
                        <div style="font-size:36px">📊</div>
                        <p>Sin datos</p>
                    </div>
                    <?php else: ?>
                    <canvas id="graficaBarras" height="180"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gráfica 2: Distribución (Doughnut) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    Distribución por <?= $tipo_reporte === 'puntos_por_motivo' ? 'Motivo' : ($tipo_reporte === 'dinero_gastado' ? 'Motivo' : 'Tipo') ?>
                </div>
                <div class="card-body">
                    <?php if (empty($datos_reporte)): ?>
                    <div class="text-center text-muted py-4">
                        <div style="font-size:36px">🍩</div>
                        <p>Sin datos</p>
                    </div>
                    <?php else: ?>
                    <canvas id="graficaDoughnut" height="180"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Gráfica 3: Línea (Tendencias) -->
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    Tendencia de <?= $tipo_reporte === 'otorgados' ? 'Otorgados' : 'Gastados' ?>
                </div>
                <div class="card-body">
                    <?php if (empty($datos_por_dia)): ?>
                    <div class="text-center text-muted py-4">
                        <div style="font-size:36px">📈</div>
                        <p>Sin datos</p>
                    </div>
                    <?php else: ?>
                    <canvas id="graficaLinea" height="180"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top 10 Usuarios -->
        <div class="col-md-6 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Top 10 Usuarios (Puntos Gastados)</div>
                <div class="card-body p-0">
                    <?php if (empty($top_usuarios)): ?>
                    <div class="text-center text-muted py-4">Sin datos</div>
                    <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_usuarios as $i => $u): ?>
                        <div class="list-group-item d-flex align-items-center gap-2 py-2">
                            <span class="badge bg-danger rounded-pill" style="min-width:28px"><?= $i + 1 ?></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate" style="font-size:13px"><?= sanitizar_salida($u['nombre']) ?></div>
                            </div>
                            <div class="text-danger fw-bold" style="font-size:13px;white-space:nowrap">
                                <?= number_format($u['total_gastado']) ?> pts
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Botones de exportar -->
        <div class="col-md-6 col-lg-6">
            <div class="card h-100">
                <div class="card-header">Opciones de Exportación</div>
                <div class="card-body d-flex flex-column justify-content-center gap-3">
                    <a href="reportes.php?tipo=<?= $tipo_reporte ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&exportar=csv"
                       class="btn btn-success w-100">
                        📊 Exportar CSV
                    </a>
                    <a href="reportes.php?tipo=<?= $tipo_reporte ?>&fecha_inicio=<?= $fecha_inicio ?>&fecha_fin=<?= $fecha_fin ?>&exportar=pdf"
                       class="btn btn-danger w-100" target="_blank">
                        📄 Exportar PDF
                    </a>
                </div>
            </div>
        </div>

    </div>

    <!-- Tabla de datos del reporte -->
    <?php if ($tipo_reporte === 'puntos_por_motivo' || $tipo_reporte === 'dinero_gastado'): ?>
    <!-- Tabla agrupada por motivo -->
    <div class="card">
        <div class="card-header">
            Resumen por Motivo
            <span class="badge bg-danger ms-2"><?= count($datos_reporte) ?> motivos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Motivo</th>
                            <th class="text-end">Total Puntos</th>
                            <?php if ($tipo_reporte === 'dinero_gastado'): ?>
                            <th class="text-end">Total Dinero ($)</th>
                            <?php endif; ?>
                            <th class="text-end">Movimientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datos_reporte)): ?>
                        <tr><td colspan="<?= $tipo_reporte === 'dinero_gastado' ? 4 : 3 ?>" class="text-center text-muted py-5">Sin datos para el período</td></tr>
                        <?php else: ?>
                        <?php $total_puntos = 0; $total_dinero = 0; ?>
                        <?php foreach ($datos_reporte as $row): ?>
                        <?php $total_puntos += $row['total_puntos']; ?>
                        <tr>
                            <td><strong><?= sanitizar_salida($row['motivo']) ?></strong></td>
                            <td class="text-end"><span class="text-success fw-bold"><?= number_format($row['total_puntos']) ?></span></td>
                            <?php if ($tipo_reporte === 'dinero_gastado'): ?>
                            <?php $total_dinero += $row['total_dinero']; ?>
                            <td class="text-end"><span class="text-danger fw-bold">$<?= number_format($row['total_dinero'], 2) ?></span></td>
                            <?php endif; ?>
                            <td class="text-end"><?= number_format($row['cantidad_movimientos']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="table-secondary">
                            <td><strong>Total</strong></td>
                            <td class="text-end"><strong class="text-success"><?= number_format($total_puntos) ?></strong></td>
                            <?php if ($tipo_reporte === 'dinero_gastado'): ?>
                            <td class="text-end"><strong class="text-danger">$<?= number_format($total_dinero, 2) ?></strong></td>
                            <?php endif; ?>
                            <td class="text-end">-</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Tabla detallada por movimiento -->
    <div class="card">
        <div class="card-header">
            Detalle del Reporte
            <span class="badge bg-danger ms-2"><?= count($datos_reporte) ?> registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Puntos</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Descripción</th>
                            <th>Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($datos_reporte)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">Sin datos para el período</td></tr>
                        <?php else: ?>
                        <?php foreach ($datos_reporte as $row): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($row['fecha_movimiento'])) ?></td>
                            <td><?= sanitizar_salida($row['usuario_nombre']) ?></td>
                            <td>
                                <strong class="<?= $row['cantidad_puntos'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= ($row['cantidad_puntos'] >= 0 ? '+' : '') . number_format($row['cantidad_puntos']) ?>
                                </strong>
                            </td>
                            <td><span class="badge bg-secondary"><?= sanitizar_salida($row['tipo_movimiento']) ?></span></td>
                            <td><?= sanitizar_salida($row['motivo'] ?? '-') ?></td>
                            <td><?= sanitizar_salida(substr($row['descripcion'] ?? '', 0, 50)) ?></td>
                            <td><?= sanitizar_salida($row['admin_nombre'] ?? 'Sistema') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php 
// Preparar datos para gráficas
$labels_grafica = [];
$data_grafica = [];
$colores = ['#dc2626', '#10b981', '#f59e0b', '#06b6d4', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'];

if ($tipo_reporte === 'puntos_por_motivo' || $tipo_reporte === 'dinero_gastado') {
    foreach ($datos_reporte as $i => $row) {
        $labels_grafica[] = $row['motivo'];
        $data_grafica[] = $row['total_puntos'];
    }
    // Para doughnut de dinero
    $data_dinero = array_column($datos_reporte, 'total_dinero');
} else {
    foreach ($datos_por_dia as $fecha => $cantidad) {
        $labels_grafica[] = $fecha;
        $data_grafica[] = $cantidad;
    }
    $data_dinero = [];
}

// Datos para gráfica doughnut (distribución por motivo)
$labels_doughnut = json_encode($labels_grafica);
$data_doughnut = json_encode(array_values($data_grafica));
$data_dinero_json = json_encode($data_dinero ?? []);
?>

<?php if (!empty($datos_por_dia) || !empty($datos_reporte)): ?>
<script>
// Colores para las gráficas
const coloresGrafica = <?= json_encode($colores) ?>;

// Gráfica 1: Barras (Puntos por Día)
<?php if (!empty($datos_por_dia)): ?>
const ctxBarras = document.getElementById('graficaBarras').getContext('2d');
new Chart(ctxBarras, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_keys($datos_por_dia)) ?>,
        datasets: [{
            label: 'Puntos',
            data: <?= json_encode(array_values($datos_por_dia)) ?>,
            backgroundColor: 'rgba(220, 38, 38, 0.7)',
            borderColor: '#dc2626',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => v.toLocaleString() } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>

// Gráfica 2: Doughnut (Distribución)
<?php if (!empty($datos_reporte)): ?>
const ctxDoughnut = document.getElementById('graficaDoughnut').getContext('2d');
new Chart(ctxDoughnut, {
    type: 'doughnut',
    data: {
        labels: <?= $labels_doughnut ?>,
        datasets: [{
            data: <?= $data_doughnut ?>,
            backgroundColor: coloresGrafica.slice(0, <?= count($data_grafica) ?>),
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } },
            tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed.toLocaleString() + ' pts' } }
        }
    }
});
<?php endif; ?>

// Gráfica 3: Línea (Tendencia)
<?php if (!empty($datos_por_dia)): ?>
const ctxLinea = document.getElementById('graficaLinea').getContext('2d');
new Chart(ctxLinea, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($datos_por_dia)) ?>,
        datasets: [{
            label: 'Tendencia',
            data: <?= json_encode(array_values($datos_por_dia)) ?>,
            borderColor: '#dc2626',
            backgroundColor: 'rgba(220, 38, 38, 0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#dc2626',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>
