<?php
/**
 * usuario/index.php
 * Catálogo de productos para el usuario
 * Con filtros por categoría y búsqueda AJAX
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_usuario();

$usuario = obtener_usuario_sesion();

// Obtener categorías para el filtro
$stmt = $pdo->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
$stmt->execute();
$categorias = $stmt->fetchAll();

// Obtener productos (con filtros opcionales)
$categoria_id = isset($_GET['categoria']) && is_numeric($_GET['categoria']) ? (int)$_GET['categoria'] : null;
$busqueda     = trim($_GET['buscar'] ?? '');

// Construir consulta con filtros
$sql    = "SELECT p.*, c.nombre AS categoria_nombre FROM productos p INNER JOIN categorias c ON p.categoria_id = c.id WHERE 1=1";
$params = [];

if ($categoria_id !== null) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoria_id;
}
if (!empty($busqueda)) {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}
$sql .= " ORDER BY p.stock DESC, p.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo - Puntos Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <!-- Banner de puntos disponibles -->
    <div class="alert alert-primary d-flex align-items-center justify-content-between mb-4 rounded-3" role="alert">
        <div>
            <strong>Hola, <?= sanitizar_salida($usuario['nombre']) ?>!</strong>
            Tienes <strong><?= number_format($usuario['puntos']) ?> puntos</strong> disponibles para canjear.
        </div>
        <a href="carrito.php" class="btn btn-primary btn-sm">Ver Carrito</a>
    </div>

    <!-- Filtros de búsqueda -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="index.php" class="row g-3 align-items-end" id="formFiltros">
                <div class="col-md-5">
                    <label class="form-label">Buscar Producto</label>
                    <input type="text" class="form-control" name="buscar" id="inputBuscar"
                           placeholder="Nombre del producto..."
                           value="<?= sanitizar_salida($busqueda) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" name="categoria" id="selectCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoria_id === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= sanitizar_salida($cat['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtros rápidos por categoría -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="index.php" class="btn btn-sm <?= $categoria_id === null && empty($busqueda) ? 'btn-primary' : 'btn-outline-secondary' ?>">
            Todos
        </a>
        <?php foreach ($categorias as $cat): ?>
        <a href="index.php?categoria=<?= $cat['id'] ?>"
           class="btn btn-sm <?= $categoria_id === (int)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= sanitizar_salida($cat['nombre']) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Resultado de búsqueda -->
    <?php if (!empty($busqueda) || $categoria_id !== null): ?>
    <p class="text-muted mb-3">
        <?= count($productos) ?> producto(s) encontrado(s)
        <?= !empty($busqueda) ? 'para "' . sanitizar_salida($busqueda) . '"' : '' ?>
    </p>
    <?php endif; ?>

    <!-- Grid de productos -->
    <div class="row g-4" id="gridProductos">
        <?php if (empty($productos)): ?>
        <div class="col-12 text-center py-5">
            <div style="font-size:64px">🔍</div>
            <h4 class="text-muted">No se encontraron productos</h4>
            <a href="index.php" class="btn btn-primary mt-3">Ver todos los productos</a>
        </div>
        <?php else: ?>
        <?php foreach ($productos as $p): ?>
        <div class="col-xl-3 col-lg-4 col-md-6">
            <div class="product-card card">
                <!-- Imagen del producto -->
                <div class="product-img-placeholder" onclick="verDetalleProducto(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)" style="cursor:pointer">
                    <img src="../<?= sanitizar_salida($p['imagen_url']) ?>"
                         alt="<?= sanitizar_salida($p['nombre']) ?>"
                         class="product-img"
                         onerror="this.parentElement.innerHTML='<div class=\'product-img-placeholder\'>📦</div>'">
                </div>
                <div class="card-body d-flex flex-column">
                    <!-- Categoría -->
                    <div class="mb-1">
                        <span class="badge bg-light text-dark border" style="font-size:11px">
                            <?= sanitizar_salida($p['categoria_nombre']) ?>
                        </span>
                        <?php if ($p['stock'] <= 0): ?>
                        <span class="badge-agotado ms-1">Agotado</span>
                        <?php elseif ($p['stock'] <= 5): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:11px">Últimas unidades</span>
                        <?php endif; ?>
                    </div>

                    <!-- Nombre -->
                    <div class="product-name"><?= sanitizar_salida($p['nombre']) ?></div>

                    <!-- Precio -->
                    <div class="product-price mt-auto">
                        <?= number_format($p['precio_puntos']) ?>
                        <span>puntos</span>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-primary btn-sm flex-grow-1"
                                onclick="verDetalleProducto(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                            Ver Detalle
                        </button>
                        <?php if ($p['stock'] > 0): ?>
                        <button class="btn btn-primary btn-sm"
                                onclick="agregarCarritoRapido(<?= $p['id'] ?>, '<?= sanitizar_salida($p['nombre']) ?>')"
                                title="Agregar al carrito">
                            +
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Modal: Detalle del Producto -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tituloModalProducto">Detalle del Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-5">
                        <img id="imagenModalProducto" src="" alt=""
                             style="width:100%;border-radius:12px;object-fit:cover;max-height:300px"
                             onerror="this.src='../assets/img/productos/default.png'">
                    </div>
                    <div class="col-md-7">
                        <span class="badge bg-light text-dark border mb-2" id="categoriaModalProducto"></span>
                        <h4 class="fw-bold" id="nombreModalProducto"></h4>
                        <p class="text-muted" id="descripcionModalProducto"></p>
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div>
                                <div class="text-muted small">Puntos</div>
                                <div class="fs-3 fw-bold text-primary" id="precioModalProducto"></div>
                            </div>
                            <div>
                                <div class="text-muted small">Stock</div>
                                <div class="fw-semibold" id="stockModalProducto"></div>
                            </div>
                        </div>
                        <!-- Mis puntos vs precio -->
                        <div class="alert alert-info py-2 px-3 mb-3" id="alertaPuntosModal">
                            Tus puntos: <strong><?= number_format($usuario['puntos']) ?></strong>
                        </div>
                        <!-- Cantidad -->
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <label class="form-label mb-0 fw-semibold">Cantidad:</label>
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(-1)">-</button>
                                <input type="number" id="cantidadModal" value="1" min="1"
                                       class="form-control text-center" style="width:70px">
                                <button class="btn btn-outline-secondary btn-sm" onclick="cambiarCantidad(1)">+</button>
                            </div>
                        </div>
                        <div id="subtotalModal" class="text-muted small mb-3"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnAgregarCarritoModal"
                        onclick="agregarAlCarritoDesdeModal()">
                    Agregar al Carrito
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast de notificación -->
<div class="toast-container">
    <div id="toastNotificacion" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body" id="mensajeToast">Producto agregado al carrito</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/ajax.js"></script>
<script>
let productoActual = null;
const puntosUsuario = <?= $usuario['puntos'] ?>;

function verDetalleProducto(producto) {
    productoActual = producto;
    document.getElementById('tituloModalProducto').textContent = producto.nombre;
    document.getElementById('imagenModalProducto').src = '../' + producto.imagen_url;
    document.getElementById('imagenModalProducto').alt = producto.nombre;
    document.getElementById('categoriaModalProducto').textContent = producto.categoria_nombre;
    document.getElementById('nombreModalProducto').textContent = producto.nombre;
    document.getElementById('descripcionModalProducto').textContent = producto.descripcion || 'Sin descripción.';
    document.getElementById('precioModalProducto').textContent = parseInt(producto.precio_puntos).toLocaleString() + ' pts';
    document.getElementById('stockModalProducto').textContent = producto.stock > 0 ? producto.stock + ' disponibles' : 'Agotado';
    document.getElementById('cantidadModal').max = producto.stock;
    document.getElementById('cantidadModal').value = 1;
    actualizarSubtotal();

    // Verificar si tiene puntos suficientes
    const btn = document.getElementById('btnAgregarCarritoModal');
    if (producto.stock <= 0) {
        btn.disabled = true;
        btn.textContent = 'Agotado';
    } else if (puntosUsuario < producto.precio_puntos) {
        btn.disabled = true;
        btn.textContent = 'Puntos insuficientes';
        document.getElementById('alertaPuntosModal').className = 'alert alert-danger py-2 px-3 mb-3';
    } else {
        btn.disabled = false;
        btn.textContent = 'Agregar al Carrito';
        document.getElementById('alertaPuntosModal').className = 'alert alert-info py-2 px-3 mb-3';
    }

    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

function cambiarCantidad(delta) {
    const input = document.getElementById('cantidadModal');
    const nuevo = Math.max(1, Math.min(parseInt(input.max) || 99, parseInt(input.value) + delta));
    input.value = nuevo;
    actualizarSubtotal();
}

function actualizarSubtotal() {
    if (!productoActual) return;
    const cantidad  = parseInt(document.getElementById('cantidadModal').value) || 1;
    const subtotal  = cantidad * productoActual.precio_puntos;
    document.getElementById('subtotalModal').textContent =
        `Subtotal: ${subtotal.toLocaleString()} puntos`;
}

document.getElementById('cantidadModal').addEventListener('input', actualizarSubtotal);

function agregarAlCarritoDesdeModal() {
    if (!productoActual) return;
    const cantidad = parseInt(document.getElementById('cantidadModal').value) || 1;
    agregarCarrito(productoActual.id, cantidad);
    bootstrap.Modal.getInstance(document.getElementById('modalProducto')).hide();
}

function agregarCarritoRapido(productoId, nombre) {
    agregarCarrito(productoId, 1);
}
</script>
</body>
</html>
