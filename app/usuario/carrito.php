<?php
/**
 * usuario/carrito.php
 * Vista del carrito de compras del usuario
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_usuario();

$usuario = obtener_usuario_sesion();

// Obtener items del carrito con datos del producto
$stmt = $pdo->prepare(
    "SELECT c.id AS carrito_id, c.cantidad, c.fecha_agregado,
            p.id AS producto_id, p.nombre, p.descripcion,
            p.precio_puntos, p.stock, p.imagen_url,
            cat.nombre AS categoria_nombre,
            (c.cantidad * p.precio_puntos) AS subtotal
     FROM carrito c
     INNER JOIN productos p ON c.producto_id = p.id
     INNER JOIN categorias cat ON p.categoria_id = cat.id
     WHERE c.usuario_id = ?
     ORDER BY c.fecha_agregado DESC"
);
$stmt->execute([$usuario['id']]);
$items_carrito = $stmt->fetchAll();

// Calcular total
$total_puntos = array_sum(array_column($items_carrito, 'subtotal'));
$puntos_disponibles = $usuario['puntos'];
$puede_comprar = $puntos_disponibles >= $total_puntos && !empty($items_carrito);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Carrito - Puntos Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Mi Carrito</h2>
        <p class="text-muted mb-0">
            <?= count($items_carrito) ?> producto(s) en tu carrito
        </p>
    </div>

    <?php if (empty($items_carrito)): ?>
    <!-- Carrito vacío -->
    <div class="text-center py-5">
        <div style="font-size:80px">🛒</div>
        <h3 class="text-muted mt-3">Tu carrito está vacío</h3>
        <p class="text-muted">Agrega productos desde el catálogo para comenzar.</p>
        <a href="index.php" class="btn btn-primary mt-2">Ver Catálogo</a>
    </div>

    <?php else: ?>
    <div class="row g-4">

        <!-- Tabla del carrito -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="tablaCarrito">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Puntos</th>
                                    <th style="width:140px">Cantidad</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_carrito as $item): ?>
                                <tr id="fila-<?= $item['carrito_id'] ?>">
                                    <!-- Imagen + nombre -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="../<?= sanitizar_salida($item['imagen_url']) ?>"
                                                 alt="<?= sanitizar_salida($item['nombre']) ?>"
                                                 class="img-thumbnail-sm"
                                                 onerror="this.src='../assets/img/productos/default.png'">
                                            <div>
                                                <div class="fw-semibold"><?= sanitizar_salida($item['nombre']) ?></div>
                                                <small class="text-muted"><?= sanitizar_salida($item['categoria_nombre']) ?></small>
                                                <?php if ($item['stock'] < $item['cantidad']): ?>
                                                <div class="text-danger small">⚠ Stock insuficiente</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Precio unitario -->
                                    <td class="align-middle">
                                        <span class="text-primary fw-semibold">
                                            <?= number_format($item['precio_puntos']) ?> pts
                                        </span>
                                    </td>
                                    <!-- Input de cantidad -->
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center gap-1">
                                            <button class="btn btn-outline-secondary btn-sm"
                                                    onclick="cambiarCantidadItem(<?= $item['carrito_id'] ?>, <?= $item['precio_puntos'] ?>, -1, <?= $item['stock'] ?>)">
                                                -
                                            </button>
                                            <input type="number"
                                                   id="cantidad-<?= $item['carrito_id'] ?>"
                                                   value="<?= $item['cantidad'] ?>"
                                                   min="1"
                                                   max="<?= $item['stock'] ?>"
                                                   class="form-control form-control-sm text-center"
                                                   style="width:55px"
                                                   onchange="actualizarDesdeInput(<?= $item['carrito_id'] ?>, <?= $item['precio_puntos'] ?>, <?= $item['stock'] ?>)">
                                            <button class="btn btn-outline-secondary btn-sm"
                                                    onclick="cambiarCantidadItem(<?= $item['carrito_id'] ?>, <?= $item['precio_puntos'] ?>, 1, <?= $item['stock'] ?>)">
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <!-- Subtotal -->
                                    <td class="align-middle">
                                        <strong class="text-primary" id="subtotal-<?= $item['carrito_id'] ?>">
                                            <?= number_format($item['subtotal']) ?> pts
                                        </strong>
                                    </td>
                                    <!-- Botón eliminar -->
                                    <td class="align-middle">
                                        <button class="btn btn-sm btn-outline-danger"
                                                onclick="eliminarItemCarrito(<?= $item['carrito_id'] ?>)"
                                                title="Eliminar del carrito">
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        ← Seguir Comprando
                    </a>
                    <button class="btn btn-outline-danger btn-sm"
                            onclick="vaciarCarrito()">
                        Vaciar Carrito
                    </button>
                </div>
            </div>
        </div>

        <!-- Resumen del pedido -->
        <div class="col-lg-4">
            <div class="carrito-resumen">
                <h5 class="fw-bold mb-4">Resumen del Pedido</h5>

                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Productos</span>
                    <span><?= count($items_carrito) ?></span>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Total a pagar</span>
                    <span class="total-puntos" id="totalPuntosResumen">
                        <?= number_format($total_puntos) ?> pts
                    </span>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Tus puntos</span>
                    <span class="fw-semibold" id="puntosDisponiblesResumen">
                        <?= number_format($puntos_disponibles) ?> pts
                    </span>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <span class="text-muted">Puntos restantes</span>
                    <span class="fw-semibold <?= $puede_comprar ? 'text-success' : 'text-danger' ?>"
                          id="puntosRestantesResumen">
                        <?= number_format($puntos_disponibles - $total_puntos) ?> pts
                    </span>
                </div>

                <?php if (!$puede_comprar && $puntos_disponibles < $total_puntos): ?>
                <div class="alert alert-danger py-2 px-3 mb-3" id="alertaPuntos">
                    <small>
                        <strong>Puntos insuficientes.</strong><br>
                        Te faltan <?= number_format($total_puntos - $puntos_disponibles) ?> puntos.
                    </small>
                </div>
                <?php else: ?>
                <div class="alert alert-success py-2 px-3 mb-3 d-none" id="alertaPuntos"></div>
                <?php endif; ?>

                <!-- Botón confirmar pedido -->
                <button class="btn btn-primary w-100 py-3 fw-bold"
                        id="btnConfirmarPedido"
                        <?= !$puede_comprar ? 'disabled' : '' ?>
                        onclick="abrirModalConfirmar()">
                    <?= $puede_comprar ? 'Confirmar Pedido' : 'Puntos Insuficientes' ?>
                </button>

                <p class="text-muted small text-center mt-3">
                    Al confirmar, se descontarán los puntos de tu cuenta.
                </p>
            </div>
        </div>

    </div>
    <?php endif; ?>

</div>
</div>

<!-- Modal de confirmación del pedido -->
<div class="modal fade" id="modalConfirmar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div style="font-size:48px">🛍️</div>
                <p class="mt-3">¿Confirmas tu pedido por</p>
                <p class="fs-4 fw-bold text-primary"><?= number_format($total_puntos) ?> puntos?</p>
                <p class="text-muted small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarFinal"
                        onclick="procesarConfirmacion()">
                    Sí, Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ajax.js"></script>
<script>
const puntosDisponibles = <?= $puntos_disponibles ?>;

/**
 * Actualiza el resumen del carrito en tiempo real
 */
function actualizarResumen(totalPuntos, cantidadItems) {
    const restantes = puntosDisponibles - totalPuntos;
    document.getElementById('totalPuntosResumen').textContent = totalPuntos.toLocaleString() + ' pts';
    document.getElementById('puntosRestantesResumen').textContent = restantes.toLocaleString() + ' pts';

    const btnConfirmar = document.getElementById('btnConfirmarPedido');
    const alertaPuntos = document.getElementById('alertaPuntos');

    if (restantes < 0) {
        document.getElementById('puntosRestantesResumen').className = 'fw-semibold text-danger';
        btnConfirmar.disabled = true;
        btnConfirmar.textContent = 'Puntos Insuficientes';
        alertaPuntos.className = 'alert alert-danger py-2 px-3 mb-3';
        alertaPuntos.innerHTML = `<small><strong>Puntos insuficientes.</strong><br>Te faltan ${Math.abs(restantes).toLocaleString()} puntos.</small>`;
    } else {
        document.getElementById('puntosRestantesResumen').className = 'fw-semibold text-success';
        btnConfirmar.disabled = cantidadItems === 0;
        btnConfirmar.textContent = cantidadItems > 0 ? 'Confirmar Pedido' : 'Carrito Vacío';
        alertaPuntos.className = 'alert alert-success py-2 px-3 mb-3 d-none';
    }
}

/**
 * Cambia la cantidad de un item con los botones +/-
 */
function cambiarCantidadItem(carritoId, precioPuntos, delta, stockMax) {
    const input = document.getElementById('cantidad-' + carritoId);
    const nuevaCantidad = Math.max(1, Math.min(stockMax, parseInt(input.value) + delta));
    input.value = nuevaCantidad;
    actualizarDesdeInput(carritoId, precioPuntos, stockMax);
}

/**
 * Actualiza la cantidad desde el input directamente
 */
function actualizarDesdeInput(carritoId, precioPuntos, stockMax) {
    const input = document.getElementById('cantidad-' + carritoId);
    let cantidad = parseInt(input.value);

    if (isNaN(cantidad) || cantidad < 1) { cantidad = 1; input.value = 1; }
    if (cantidad > stockMax) { cantidad = stockMax; input.value = stockMax; }

    actualizarCantidadCarrito(carritoId, cantidad, function(data) {
        if (data) {
            // Actualizar subtotal de la fila
            const subtotal = cantidad * precioPuntos;
            document.getElementById('subtotal-' + carritoId).textContent =
                subtotal.toLocaleString() + ' pts';
            actualizarResumen(data.total_puntos, data.cantidad_carrito);
        }
    });
}

/**
 * Elimina un item del carrito y actualiza la UI
 */
function eliminarItemCarrito(carritoId) {
    eliminarCarrito(carritoId, function(data) {
        // Eliminar la fila de la tabla
        const fila = document.getElementById('fila-' + carritoId);
        if (fila) fila.remove();

        // Si el carrito quedó vacío, recargar para mostrar mensaje
        if (data.cantidad_carrito === 0) {
            location.reload();
        } else {
            actualizarResumen(data.total_puntos, data.cantidad_carrito);
        }
    });
}

/**
 * Vacía todo el carrito
 */
function vaciarCarrito() {
    if (!confirm('¿Vaciar todo el carrito?')) return;
    location.href = '../api/vaciar_carrito.php';
}

/**
 * Abre el modal de confirmación del pedido
 */
function abrirModalConfirmar() {
    new bootstrap.Modal(document.getElementById('modalConfirmar')).show();
}

/**
 * Procesa la confirmación del pedido
 */
function procesarConfirmacion() {
    const btn = document.getElementById('btnConfirmarFinal');
    btn.disabled = true;
    btn.textContent = 'Procesando...';

    confirmarPedido(function(data) {
        if (data) {
            bootstrap.Modal.getInstance(document.getElementById('modalConfirmar')).hide();
            setTimeout(() => {
                window.location.href = 'mis_pedidos.php?nuevo=' + data.pedido_id;
            }, 1000);
        } else {
            btn.disabled = false;
            btn.textContent = 'Sí, Confirmar';
        }
    });
}
</script>
</body>
</html>
