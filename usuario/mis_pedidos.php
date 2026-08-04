<?php
/**
 * usuario/mis_pedidos.php
 * Historial de pedidos del usuario con filtro y detalles
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_usuario();

$usuario = obtener_usuario_sesion();

// Filtro por estado (si existe)
$estado_filtro = trim($_GET['estado'] ?? '');

// Consulta de pedidos del usuario
$sql = "SELECT p.id, p.fecha_pedido, p.estado,
               p.total_puntos_usados,
               (SELECT COUNT(*) FROM detalle_pedidos WHERE pedido_id = p.id) as cantidad_productos
        FROM pedidos p
        WHERE p.usuario_id = ?";

$params = [$usuario['id']];

if (!empty($estado_filtro)) {
    $sql .= " AND p.estado = ?";
    $params[] = $estado_filtro;
}

$sql .= " ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Estados disponibles para el filtro
$estados = ['Solicitud', 'En camino', 'Listo para recoger'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - Puntos Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Mis Pedidos</h2>
        <p class="text-muted mb-0">Historial de todos tus pedidos</p>
    </div>

    <!-- Filtro por estado -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted me-2">Filtrar por estado:</span>
                <a href="mis_pedidos.php"
                   class="btn btn-sm <?= empty($estado_filtro) ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Todos
                </a>
                <?php foreach ($estados as $estado): ?>
                <a href="mis_pedidos.php?estado=<?= urlencode($estado) ?>"
                   class="btn btn-sm <?= $estado_filtro === $estado ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= $estado ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Mensaje de nuevo pedido -->
    <?php if (isset($_GET['nuevo'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>¡Pedido confirmado!</strong> Tu pedido #<?= (int)$_GET['nuevo'] ?> ha sido creado exitosamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Tabla de pedidos -->
    <?php if (empty($pedidos)): ?>
    <div class="text-center py-5">
        <div style="font-size:80px">📋</div>
        <h3 class="text-muted mt-3">No tienes pedidos</h3>
        <p class="text-muted">Cuando realices un pedido, aparecerá aquí.</p>
        <a href="index.php" class="btn btn-primary mt-2">Ver Catálogo</a>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Fecha</th>
                            <th>Productos</th>
                            <th>Total Puntos</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold">#<?= $pedido['id'] ?></span>
                            </td>
                            <td>
                                <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= $pedido['cantidad_productos'] ?> producto(s)
                                </span>
                            </td>
                            <td>
                                <span class="text-primary fw-semibold">
                                    <?= number_format($pedido['total_puntos_usados']) ?> pts
                                </span>
                            </td>
                            <td>
                                <?php
                                $badge_class = match($pedido['estado']) {
                                    'Solicitud' => 'bg-warning text-dark',
                                    'En camino' => 'bg-info',
                                    'Listo para recoger' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $badge_class ?>">
                                    <?= sanitizar_salida($pedido['estado']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="verDetallePedido(<?= $pedido['id'] ?>)">
                                    Ver Detalles
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Modal: Detalle del Pedido -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetallePedido">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2 text-muted">Cargando detalles...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Función: verDetallePedido(pedidoId)
 * Carga los detalles de un pedido específico via AJAX
 *
 * @param {number} pedidoId ID del pedido
 */
function verDetallePedido(pedidoId) {
    const modal = new bootstrap.Modal(document.getElementById('modalDetallePedido'));
    const contenido = document.getElementById('contenidoDetallePedido');

    // Mostrar cargando
    contenido.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-2 text-muted">Cargando detalles...</p>
        </div>`;

    modal.show();

    // Determinar ruta base
    const base = window.location.pathname.includes('/usuario/') ? '../' : '';

    // Fetch para obtener detalles
    fetch(base + 'api/detalle_pedido.php?pedido_id=' + pedidoId)
        .then(response => {
            if (!response.ok) throw new Error('Error al cargar');
            return response.json();
        })
        .then(data => {
            if (data.success && data.pedido) {
                const p = data.pedido;
                const items = data.items || [];

                // Determinar clase del badge de estado
                const estadoClases = {
                    'Solicitud': 'bg-warning text-dark',
                    'En camino': 'bg-info',
                    'Listo para recoger': 'bg-success'
                };
                const badgeClass = estadoClases[p.estado] || 'bg-secondary';

                // Construir HTML de los items
                let itemsHtml = '';
                let total = 0;
                items.forEach(item => {
                    const subtotal = item.cantidad * item.puntos_unitarios;
                    total += subtotal;
                    itemsHtml += `
                        <tr>
                            <td>${item.nombre_producto}</td>
                            <td class="text-center">${item.cantidad}</td>
                            <td class="text-end">${parseInt(item.puntos_unitarios).toLocaleString()} pts</td>
                            <td class="text-end fw-semibold">${subtotal.toLocaleString()} pts</td>
                        </tr>`;
                });

                // Mostrar contenido
                contenido.innerHTML = `
                    <!-- Info del pedido -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="text-muted small">Código</div>
                            <div class="fw-semibold">#${p.id}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Fecha</div>
                            <div class="fw-semibold">${new Date(p.fecha_pedido).toLocaleString('es-ES')}</div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Estado</div>
                            <span class="badge ${badgeClass}">${p.estado}</span>
                        </div>
                    </div>

                    <hr>

                    <!-- Tabla de productos -->
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cantidad</th>
                                    <th class="text-end">Puntos Unit.</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${itemsHtml}
                            </tbody>
                            <tfoot>
                                <tr class="table-light">
                                    <td colspan="3" class="text-end fw-bold">Total:</td>
                                    <td class="text-end fw-bold text-primary fs-5">
                                        ${total.toLocaleString()} pts
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;
            } else {
                contenido.innerHTML = `
                    <div class="alert alert-danger">
                        Error al cargar los detalles del pedido.
                    </div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contenido.innerHTML = `
                <div class="alert alert-danger">
                    Error de conexión. Intenta de nuevo.
                </div>`;
        });
}
</script>
</body>
</html>