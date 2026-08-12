<?php
/**
 * admin/index.php
 * Dashboard principal del administrador
 * Muestra estadísticas generales del sistema
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

// Validar que sea administrador (redirige si no lo es)
validar_sesion_admin();

// ============================================================
// OBTENER ESTADÍSTICAS PARA EL DASHBOARD
// ============================================================

// Total de usuarios registrados (solo rol usuario)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE rol = 'usuario' AND estado = 'activo'");
$stmt->execute();
$total_usuarios = $stmt->fetchColumn();

// Total de productos activos (con stock > 0)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE stock > 0");
$stmt->execute();
$total_productos = $stmt->fetchColumn();

// Pedidos creados hoy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE DATE(fecha_pedido) = CURDATE()");
$stmt->execute();
$pedidos_hoy = $stmt->fetchColumn();

// Puntos otorgados hoy (solo asignaciones positivas)
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(cantidad_puntos), 0)
     FROM historial_puntos
     WHERE tipo_movimiento = 'asignacion'
     AND DATE(fecha_movimiento) = CURDATE()"
);
$stmt->execute();
$puntos_hoy = $stmt->fetchColumn();

// Total de puntos en circulación (suma de todos los usuarios)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(puntos_disponibles), 0) FROM usuarios WHERE rol = 'usuario'");
$stmt->execute();
$total_puntos = $stmt->fetchColumn();

// Pedidos pendientes (en estado Solicitud)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE estado = 'Solicitud'");
$stmt->execute();
$pedidos_pendientes = $stmt->fetchColumn();

// Últimos 8 pedidos con datos del usuario
$stmt = $pdo->prepare(
    "SELECT p.id, p.fecha_pedido, p.estado, p.total_puntos_usados,
            u.nombre AS usuario_nombre,
            COUNT(dp.id) AS cantidad_productos
     FROM pedidos p
     INNER JOIN usuarios u ON p.usuario_id = u.id
     LEFT JOIN detalle_pedidos dp ON p.id = dp.pedido_id
     GROUP BY p.id, p.fecha_pedido, p.estado, p.total_puntos_usados, u.nombre
     ORDER BY p.fecha_pedido DESC
     LIMIT 8"
);
$stmt->execute();
$ultimos_pedidos = $stmt->fetchAll();

// Top 5 usuarios con más puntos
$stmt = $pdo->prepare(
    "SELECT nombre, email, puntos_disponibles
     FROM usuarios
     WHERE rol = 'usuario' AND estado = 'activo'
     ORDER BY puntos_disponibles DESC
     LIMIT 5"
);
$stmt->execute();
$top_usuarios = $stmt->fetchAll();

// Datos para gráfica: puntos otorgados últimos 7 días
$stmt = $pdo->prepare(
    "SELECT DATE(fecha_movimiento) AS fecha, SUM(cantidad_puntos) AS total
     FROM historial_puntos
     WHERE tipo_movimiento = 'asignacion'
     AND fecha_movimiento >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY DATE(fecha_movimiento)
     ORDER BY fecha ASC"
);
$stmt->execute();
$datos_grafica = $stmt->fetchAll();

// Preparar datos para Chart.js
$labels_grafica  = [];
$valores_grafica = [];
foreach ($datos_grafica as $dato) {
    $labels_grafica[]  = date('d/m', strtotime($dato['fecha']));
    $valores_grafica[] = (int)$dato['total'];
}

$usuario_sesion = obtener_usuario_sesion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <!-- Encabezado de página -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Dashboard</h2>
            <p class="text-muted mb-0">Bienvenido, <?= sanitizar_salida($usuario_sesion['nombre']) ?></p>
        </div>
        <div class="text-muted small">
            <?php
$meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
echo $dias[date('w')] . ', ' . date('d') . ' de ' . $meses[date('n')] . ' de ' . date('Y');
?>
        </div>
    </div>

    <!-- ============================================================
         TARJETAS DE ESTADÍSTICAS
         ============================================================ -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-6 g-4 mb-4">

        <div class="col">
            <div class="stat-card stat-primary h-100">
                <div class="stat-number"><?= number_format($total_usuarios) ?></div>
                <div class="stat-label">Usuarios Activos</div>
                <div class="stat-icon">👥</div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card stat-success h-100">
                <div class="stat-number"><?= number_format($total_productos) ?></div>
                <div class="stat-label">Productos en Stock</div>
                <div class="stat-icon">📦</div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card stat-warning h-100">
                <div class="stat-number"><?= number_format($pedidos_hoy) ?></div>
                <div class="stat-label">Pedidos Hoy</div>
                <div class="stat-icon">🛒</div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card stat-info h-100">
                <div class="stat-number"><?= number_format($puntos_hoy) ?></div>
                <div class="stat-label">Puntos Otorgados Hoy</div>
                <div class="stat-icon">⭐</div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card stat-primary h-100">
                <div class="stat-number"><?= number_format($total_puntos) ?></div>
                <div class="stat-label">Puntos en Circulación</div>
                <div class="stat-icon">💎</div>
            </div>
        </div>

        <div class="col">
            <div class="stat-card stat-danger h-100">
                <div class="stat-number"><?= number_format($pedidos_pendientes) ?></div>
                <div class="stat-label">Pedidos Pendientes</div>
                <div class="stat-icon">⏳</div>
            </div>
        </div>

    </div><!-- /row estadísticas -->

    <!-- ============================================================
         GRÁFICA + TOP USUARIOS
         ============================================================ -->
    <div class="row g-4 mb-4">

        <!-- Gráfica de puntos últimos 7 días -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Puntos Otorgados - Últimos 7 Días</span>
                    <a href="reportes.php" class="btn btn-sm btn-primary">Ver Reportes</a>
                </div>
                <div class="card-body">
                    <?php if (empty($datos_grafica)): ?>
                    <div class="text-center text-muted py-5">
                        <div style="font-size:48px">📊</div>
                        <p>No hay datos de puntos en los últimos 7 días</p>
                    </div>
                    <?php else: ?>
                    <canvas id="graficaPuntos" height="100"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top 5 usuarios con más puntos -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Top Usuarios</span>
                    <a href="usuarios.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($top_usuarios as $i => $u): ?>
                        <div class="list-group-item d-flex align-items-center gap-3 py-3">
                            <div class="fw-bold text-muted" style="width:24px">#<?= $i + 1 ?></div>
                            <div class="avatar-circle" style="width:36px;height:36px;font-size:14px;background:linear-gradient(135deg,#dc2626,#ef4444);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;flex-shrink:0">
                                <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate" style="font-size:14px"><?= sanitizar_salida($u['nombre']) ?></div>
                                <div class="text-muted" style="font-size:12px"><?= sanitizar_salida($u['email']) ?></div>
                            </div>
                            <div class="text-primary fw-bold" style="font-size:14px;white-space:nowrap">
                                <?= number_format($u['puntos_disponibles']) ?> pts
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($top_usuarios)): ?>
                        <div class="list-group-item text-center text-muted py-4">Sin usuarios registrados</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /row gráfica -->

    <!-- ============================================================
         ÚLTIMOS PEDIDOS
         ============================================================ -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Últimos Pedidos</span>
            <a href="pedidos.php" class="btn btn-sm btn-primary">Ver todos</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#Pedido</th>
                            <th>Usuario</th>
                            <th>Fecha</th>
                            <th>Productos</th>
                            <th>Total Puntos</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimos_pedidos)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay pedidos registrados</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($ultimos_pedidos as $pedido): ?>
                        <tr>
                            <td><strong>#<?= $pedido['id'] ?></strong></td>
                            <td><?= sanitizar_salida($pedido['usuario_nombre']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                            <td><?= $pedido['cantidad_productos'] ?> producto(s)</td>
                            <td><strong><?= number_format($pedido['total_puntos_usados']) ?> pts</strong></td>
                            <td>
                                <?php
                                $clase_estado = match($pedido['estado']) {
                                    'Solicitud'         => 'badge-solicitud',
                                    'En camino'         => 'badge-en-camino',
                                    'Listo para recoger'=> 'badge-listo',
                                    default             => 'bg-secondary text-white'
                                };
                                ?>
                                <span class="badge <?= $clase_estado ?>">
                                    <?= sanitizar_salida($pedido['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="pedidos.php?ver=<?= $pedido['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    Ver
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /container -->
</div><!-- /main-content -->

<!-- Bootstrap JS + Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php if (!empty($datos_grafica)): ?>
<script>
// Gráfica de puntos otorgados últimos 7 días
const ctx = document.getElementById('graficaPuntos').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels_grafica) ?>,
        datasets: [{
            label: 'Puntos Otorgados',
            data: <?= json_encode($valores_grafica) ?>,
            backgroundColor: 'rgba(220, 38, 38, 0.7)',
            borderColor: '#dc2626',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.parsed.y.toLocaleString() + ' puntos'
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { callback: val => val.toLocaleString() }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>
