<?php
/**
 * admin/pedidos.php
 * Gestión y seguimiento de pedidos
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once 'includes/funciones_pedidos.php';

validar_sesion_admin();

$csrf_token = generar_token_csrf();

// Filtro por estado
$filtro_estado = $_GET['estado'] ?? null;
if (!in_array($filtro_estado, ESTADOS_PEDIDO)) $filtro_estado = null;

$pedidos = obtener_pedidos($filtro_estado);

// Contar pedidos por estado
$stmt = $pdo->prepare("SELECT estado, COUNT(*) as total FROM pedidos GROUP BY estado");
$stmt->execute();
$conteos = [];
foreach ($stmt->fetchAll() as $row) {
    $conteos[$row['estado']] = $row['total'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Gestión de Pedidos</h2>
        <p class="text-muted mb-0"><?= count($pedidos) ?> pedido(s) encontrado(s)</p>
    </div>

    <!-- Filtros por estado -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted fw-semibold me-2">Estado:</span>
                <a href="pedidos.php" class="btn btn-sm <?= $filtro_estado === null ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Todos <span class="badge bg-white text-dark ms-1"><?= array_sum($conteos) ?></span>
                </a>
                <?php foreach (ESTADOS_PEDIDO as $estado): ?>
                <?php
                $clase_btn = match($estado) {
                    'Solicitud' => 'btn-outline-warning',
                    'En camino' => 'btn-outline-info',
                    'Listo para recoger' => 'btn-outline-success',
                    default => 'btn-outline-secondary'
                };
                if ($filtro_estado === $estado) $clase_btn = str_replace('outline-', '', $clase_btn);
                ?>
                <a href="pedidos.php?estado=<?= urlencode($estado) ?>" class="btn btn-sm <?= $clase_btn ?>">
                    <?= sanitizar_salida($estado) ?>
                    <span class="badge bg-white text-dark ms-1"><?= $conteos[$estado] ?? 0 ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de pedidos -->
    <div class="card">
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
                        <?php if (empty($pedidos)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No hay pedidos</td></tr>
                        <?php else: ?>
                        <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td>
                                <div><?= sanitizar_salida($p['usuario_nombre']) ?></div>
                                <small class="text-muted"><?= sanitizar_salida($p['usuario_email']) ?></small>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                            <td><?= $p['cantidad_productos'] ?> producto(s)</td>
                            <td><strong class="text-primary"><?= number_format($p['total_puntos_usados']) ?> pts</strong></td>
                            <td>
                                <?php
                                $clase = match($p['estado']) {
                                    'Solicitud' => 'badge-solicitud',
                                    'En camino' => 'badge-en-camino',
                                    'Listo para recoger' => 'badge-listo',
                                    default => 'bg-secondary text-white'
                                };
                                ?>
                                <span class="badge <?= $clase ?>"><?= sanitizar_salida($p['estado']) ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="verDetallePedido(<?= $p['id'] ?>)">
                                    Ver Detalle
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
</div>

<!-- Modal: Detalle del Pedido -->
<div class="modal fade" id="modalDetallePedido" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle del Pedido <span id="numeroPedidoModal"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetallePedido">
                <div class="text-center py-4">Cargando...</div>
            </div>
            <div class="modal-footer" id="footerDetallePedido"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let pedidoIdActual = 0;
let listaProductos = [];

function verDetallePedido(id) {
    pedidoIdActual = id;
    document.getElementById('numeroPedidoModal').textContent = '#' + id;
    document.getElementById('contenidoDetallePedido').innerHTML = '<div class="text-center py-4">Cargando...</div>';
    document.getElementById('footerDetallePedido').innerHTML = '';
    new bootstrap.Modal(document.getElementById('modalDetallePedido')).show();

    fetch('../api/detalle_pedido.php?pedido_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('contenidoDetallePedido').innerHTML = 
                    '<p class="text-danger text-center py-4">' + data.mensaje + '</p>';
                return;
            }

            const p = data.pedido;
            listaProductos = data.productos || [];
            let esEditable = p.estado === 'Solicitud';

            // Construir HTML
            let html = '<div class="row g-3 mb-4">' +
                '<div class="col-md-6"><div class="bg-light rounded p-3">' +
                '<div class="text-muted small mb-1">Usuario</div>' +
                '<div class="fw-semibold">' + p.usuario_nombre + '</div>' +
                '<div class="text-muted small">' + p.usuario_email + '</div></div></div>' +
                '<div class="col-md-3"><div class="bg-light rounded p-3">' +
                '<div class="text-muted small mb-1">Fecha</div>' +
                '<div class="fw-semibold">' + p.fecha_pedido + '</div></div></div>' +
                '<div class="col-md-3"><div class="bg-light rounded p-3">' +
                '<div class="text-muted small mb-1">Estado</div>' +
                '<div class="fw-semibold">' + p.estado + '</div></div></div></div>' +
                '<h6 class="fw-bold mb-3">Productos del Pedido</h6>' +
                '<div class="table-responsive"><table class="table table-sm" id="tablaProductos">' +
                '<thead><tr><th>Producto</th><th>Cantidad</th><th>Pts.</th><th>MXN</th><th>Subtotal</th>' +
                (esEditable ? '<th>Editar</th>' : '') + '</tr></thead><tbody>';

            // Productos
            data.detalle.forEach(d => {
                let mxn = d.precio_pesos ? '$' + parseFloat(d.precio_pesos).toLocaleString('es-MX', {minimumFractionDigits:2}) : '-';
                let subtotal = d.cantidad * d.puntos_unitarios;
                let btnEditar = esEditable ? 
                    '<button class="btn btn-sm btn-outline-primary" onclick="editarDetalle(' + d.id + ', ' + d.producto_id + ', \'' + 
                    d.producto_nombre.replace(/'/g, "\\'") + '\', ' + d.cantidad + ', ' + d.puntos_unitarios + ')">Editar</button>' : '';
                
                html += '<tr data-detalle-id="' + d.id + '">' +
                    '<td id="prod_nom_' + d.id + '">' + d.producto_nombre + '</td>' +
                    '<td id="prod_cant_' + d.id + '">' + d.cantidad + '</td>' +
                    '<td>' + d.puntos_unitarios.toLocaleString() + ' pts</td>' +
                    '<td>' + mxn + '</td>' +
                    '<td><strong>' + subtotal.toLocaleString() + ' pts</strong></td>' +
                    '<td>' + btnEditar + '</td></tr>';
            });

            html += '</tbody><tfoot><tr class="table-primary">' +
                '<td colspan="4" class="fw-bold">Total Puntos</td>' +
                '<td class="fw-bold">' + parseInt(p.total_puntos_usados).toLocaleString() + ' pts</td>' +
                (esEditable ? '<td></td>' : '') + '</tr></tfoot></table></div>';

            document.getElementById('contenidoDetallePedido').innerHTML = html;

            // Footer
            let footer = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>';
            let siguiente = p.estado === 'Solicitud' ? 'En camino' : (p.estado === 'En camino' ? 'Listo para recoger' : null);
            if (siguiente) {
                footer += ' <button class="btn btn-primary" onclick="cambiarEstado(\'' + siguiente + '\')">Cambiar a: ' + siguiente + '</button>';
            }
            document.getElementById('footerDetallePedido').innerHTML = footer;
        })
        .catch(() => {
            document.getElementById('contenidoDetallePedido').innerHTML = 
                '<p class="text-danger text-center py-4">Error al cargar el detalle.</p>';
        });
}

function cambiarEstado(nuevoEstado) {
    if (!confirm('¿Cambiar estado a "' + nuevoEstado + '"?')) return;

    fetch('../api/cambiar_estado_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pedido_id: pedidoIdActual, nuevo_estado: nuevoEstado })
    })
    .then(r => r.json())
    .then(data => {
        alert(data.mensaje);
        if (data.success) location.reload();
    })
    .catch(() => alert('Error de conexión.'));
}

function editarDetalle(detalleId, productoId, nombreActual, cantidadActual, puntosActuales) {
    let tr = document.querySelector('tr[data-detalle-id="' + detalleId + '"]');
    if (!tr) return;

    let opciones = '<option value="">Seleccionar...</option>';
    listaProductos.forEach(prod => {
        let selected = prod.id === productoId ? 'selected' : '';
        opciones += '<option value="' + prod.id + '" data-pts="' + prod.precio_puntos + '" ' + selected + '>' + 
            prod.nombre + ' (' + prod.precio_puntos + ' pts)</option>';
    });

    tr.innerHTML = '<td><select class="form-select form-select-sm" id="sel_prod_' + detalleId + '" onchange="actPtos(' + detalleId + ')">' + opciones + '</select></td>' +
        '<td><input type="number" class="form-control form-control-sm" id="inp_cant_' + detalleId + '" value="' + cantidadActual + '" min="1" max="999" style="width:70px"></td>' +
        '<td id="td_pts_' + detalleId + '">' + puntosActuales.toLocaleString() + ' pts</td>' +
        '<td id="td_mxn_' + detalleId + '">-</td>' +
        '<td id="td_sub_' + detalleId + '"><strong>' + (cantidadActual * puntosActuales).toLocaleString() + ' pts</strong></td>' +
        '<td><button class="btn btn-sm btn-success me-1" onclick="guardarEdicion(' + detalleId + ',' + productoId + ')">Guardar</button>' +
        '<button class="btn btn-sm btn-secondary" onclick="cancelarEdicion(' + detalleId + ',\'' + nombreActual.replace(/'/g, "\\'") + '\',' + cantidadActual + ',' + puntosActuales + ')">Cancelar</button></td>';
}

function actPtos(detalleId) {
    let sel = document.getElementById('sel_prod_' + detalleId);
    let opt = sel.options[sel.selectedIndex];
    let pts = parseInt(opt.dataset.pts) || 0;
    let cant = parseInt(document.getElementById('inp_cant_' + detalleId).value) || 1;
    document.getElementById('td_pts_' + detalleId).textContent = pts.toLocaleString() + ' pts';
    document.getElementById('td_sub_' + detalleId).innerHTML = '<strong>' + (cant * pts).toLocaleString() + ' pts</strong>';
}

function cancelarEdicion(detalleId, nombre, cantidad, puntos) {
    let mxn = '-';
    let tr = document.querySelector('tr[data-detalle-id="' + detalleId + '"]');
    if (!tr) return;
    tr.innerHTML = '<td id="prod_nom_' + detalleId + '">' + nombre + '</td>' +
        '<td id="prod_cant_' + detalleId + '">' + cantidad + '</td>' +
        '<td>' + puntos.toLocaleString() + ' pts</td>' +
        '<td>' + mxn + '</td>' +
        '<td><strong>' + (cantidad * puntos).toLocaleString() + ' pts</strong></td>' +
        '<td><button class="btn btn-sm btn-outline-primary" onclick="editarDetalle(' + detalleId + ',0,\'' + nombre.replace(/'/g, "\\'") + '\',' + cantidad + ',' + puntos + ')">Editar</button></td>';
}

function guardarEdicion(detalleId, prodIdAnterior) {
    let nuevoProd = parseInt(document.getElementById('sel_prod_' + detalleId).value);
    let nuevaCant = parseInt(document.getElementById('inp_cant_' + detalleId).value);

    if (!nuevoProd || nuevaCant <= 0) {
        alert('Selecciona producto y cantidad válida.');
        return;
    }

    if (!confirm('¿Guardar cambios?')) return;

    fetch('../api/actualizar_detalle_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ detalle_id: detalleId, producto_id: nuevoProd, cantidad: nuevaCant })
    })
    .then(r => r.json())
    .then(data => {
        alert(data.mensaje);
        if (data.success) verDetallePedido(pedidoIdActual);
    })
    .catch(() => alert('Error de conexión.'));
}
</script>

</body>
</html>