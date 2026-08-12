<?php
/**
 * admin/productos.php
 * CRUD completo de productos para el administrador
 * Crear, leer, actualizar y eliminar productos
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once 'includes/funciones_productos.php';

// Validar que sea administrador
validar_sesion_admin();

$mensaje = '';
$tipo_mensaje = '';
$csrf_token = generar_token_csrf();

// ============================================================
// PROCESAR ACCIONES POST (crear, editar, eliminar)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar token CSRF
    if (!validar_token_csrf()) {
        $mensaje = 'Error de seguridad. Recarga la página.';
        $tipo_mensaje = 'danger';
    } else {

        $accion = $_POST['accion'] ?? '';

        // ---- CREAR PRODUCTO ----
        if ($accion === 'crear') {
            $nombre       = trim($_POST['nombre']       ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $categoria   = (int)($_POST['categoria_id']  ?? 0);
            $precio      = (int)($_POST['precio_puntos']  ?? 0);
            $precio_pesos = (float)($_POST['precio_pesos'] ?? 0);
            $stock       = (int)($_POST['stock']        ?? 0);

            // Validaciones server-side
            if (empty($nombre) || $categoria <= 0 || $precio <= 0 || $stock < 0) {
                $mensaje = 'Completa todos los campos obligatorios correctamente.';
                $tipo_mensaje = 'danger';
            } else {
                // Procesar imagen si se subió
                $imagen_url = 'assets/img/productos/default.png';
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $validacion = validar_imagen($_FILES['imagen']);
                    if (!$validacion['valido']) {
                        $mensaje = $validacion['error'];
                        $tipo_mensaje = 'danger';
                    } else {
                        // Crear producto primero para obtener el ID
                        if (crear_producto($nombre, $descripcion, $categoria, $precio, $stock, $imagen_url, $precio_pesos)) {
                            $nuevo_id = obtener_ultimo_id_insertado();
                            $imagen_url = guardar_imagen($_FILES['imagen'], $nuevo_id);
                            // Actualizar con la imagen correcta
                            actualizar_producto($nuevo_id, $nombre, $descripcion, $categoria, $precio, $stock, $imagen_url, $precio_pesos);
                            $mensaje = 'Producto creado exitosamente.';
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = 'Error al crear el producto.';
                            $tipo_mensaje = 'danger';
                        }
                    }
                } else {
                    // Sin imagen, usar default
                    if (crear_producto($nombre, $descripcion, $categoria, $precio, $stock, $imagen_url, $precio_pesos)) {
                        $mensaje = 'Producto creado exitosamente.';
                        $tipo_mensaje = 'success';
                    } else {
                        $mensaje = 'Error al crear el producto.';
                        $tipo_mensaje = 'danger';
                    }
                }
            }
        }

        // ---- EDITAR PRODUCTO ----
        elseif ($accion === 'editar') {
            $id           = (int)($_POST['producto_id']  ?? 0);
            $nombre      = trim($_POST['nombre']       ?? '');
            $descripcion = trim($_POST['descripcion']  ?? '');
            $categoria   = (int)($_POST['categoria_id']  ?? 0);
            $precio       = (int)($_POST['precio_puntos']  ?? 0);
            $precio_pesos = (float)($_POST['precio_pesos'] ?? 0);
            $stock        = (int)($_POST['stock']         ?? 0);

            if ($id <= 0 || empty($nombre) || $categoria <= 0 || $precio <= 0 || $stock < 0) {
                $mensaje = 'Datos inválidos para actualizar el producto.';
                $tipo_mensaje = 'danger';
            } else {
                // Obtener imagen actual
                $producto_actual = obtener_producto($id);
                $imagen_url = $producto_actual['imagen_url'] ?? 'assets/img/productos/default.png';

                // Si se subió nueva imagen, procesarla
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                    $validacion = validar_imagen($_FILES['imagen']);
                    if ($validacion['valido']) {
                        $imagen_url = guardar_imagen($_FILES['imagen'], $id);
                    }
                }

                if (actualizar_producto($id, $nombre, $descripcion, $categoria, $precio, $stock, $imagen_url, $precio_pesos)) {
                    $mensaje = 'Producto actualizado exitosamente.';
                    $tipo_mensaje = 'success';
                } else {
                    $mensaje = 'Error al actualizar el producto.';
                    $tipo_mensaje = 'danger';
                }
            }
        }

        // ---- ELIMINAR PRODUCTO ----
        elseif ($accion === 'eliminar') {
            $id = (int)($_POST['producto_id'] ?? 0);
            if ($id > 0 && eliminar_producto($id)) {
                $mensaje = 'Producto eliminado correctamente.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al eliminar el producto.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Obtener filtro de categoría
$filtro_categoria = isset($_GET['categoria']) && is_numeric($_GET['categoria'])
    ? (int)$_GET['categoria'] : null;

// Obtener lista de productos y categorías
$productos   = obtener_productos($filtro_categoria);
$categorias  = obtener_categorias();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Gestión de Productos</h2>
            <p class="text-muted mb-0"><?= count($productos) ?> producto(s) encontrado(s)</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProducto" onclick="limpiarModal()">
            + Nuevo Producto
        </button>
    </div>

    <!-- Mensaje de éxito/error -->
    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show mb-4" role="alert">
        <?= sanitizar_salida($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtros por categoría -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted fw-semibold me-2">Filtrar:</span>
                <a href="productos.php" class="btn btn-sm <?= $filtro_categoria === null ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    Todos
                </a>
                <?php foreach ($categorias as $cat): ?>
                <a href="productos.php?categoria=<?= $cat['id'] ?>"
                   class="btn btn-sm <?= $filtro_categoria === (int)$cat['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                    <?= sanitizar_salida($cat['nombre']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tabla de productos -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:60px">ID</th>
                            <th style="width:80px">Imagen</th>
                            <th>Nombre</th>
                            <th style="width:120px">Categoría</th>
                            <th style="width:100px">Precio (pts)</th>
                            <th style="width:120px">Precio (MXN)</th>
                            <th style="width:100px">Stock</th>
                            <th style="width:140px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productos)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                No hay productos registrados
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($productos as $p): ?>
                        <tr>
                            <td><strong>#<?= $p['id'] ?></strong></td>
                            <td>
                                <img src="../<?= sanitizar_salida($p['imagen_url']) ?>"
                                     alt="<?= sanitizar_salida($p['nombre']) ?>"
                                     class="img-thumbnail-sm"
                                     style="width:50px;height:50px;object-fit:cover"
                                     onerror="this.src='../assets/img/productos/default.png'">
                            </td>
                            <td>
                                <div class="fw-semibold"><?= sanitizar_salida($p['nombre']) ?></div>
                                <div class="text-muted small"><?= sanitizar_salida(substr($p['descripcion'] ?? '', 0, 60)) ?>...</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= sanitizar_salida($p['categoria_nombre']) ?>
                                </span>
                            </td>
                            <td><strong class="text-primary"><?= number_format($p['precio_puntos']) ?></strong></td>
                            <td><strong class="text-success">$<?= number_format($p['precio_pesos'] ?? 0, 2) ?></strong></td>
                            <td>
                                <?php if ($p['stock'] <= 0): ?>
                                <span class="badge bg-danger">Agotado</span>
                                <?php elseif ($p['stock'] <= 5): ?>
                                <span class="badge bg-warning text-dark"><?= $p['stock'] ?> (bajo)</span>
                                <?php else: ?>
                                <span class="badge bg-success"><?= $p['stock'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- Botón Editar -->
                                <button class="btn btn-sm btn-warning me-1"
                                        onclick="editarProducto(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)"
                                        title="Editar producto">
                                    Editar
                                </button>
                                <!-- Botón Eliminar -->
                                <button class="btn btn-sm btn-danger"
                                        onclick="confirmarEliminar(<?= $p['id'] ?>, '<?= sanitizar_salida($p['nombre']) ?>')"
                                        title="Eliminar producto">
                                    Eliminar
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

</div><!-- /container -->
</div><!-- /main-content -->

<!-- ============================================================
     MODAL: CREAR / EDITAR PRODUCTO
     ============================================================ -->
<div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalProductoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProductoLabel">Nuevo Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="productos.php" enctype="multipart/form-data" id="formProducto" novalidate>
                <div class="modal-body">
                    <!-- Token CSRF -->
                    <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                    <!-- Acción: crear o editar -->
                    <input type="hidden" name="accion" id="accionProducto" value="crear">
                    <!-- ID del producto (solo en edición) -->
                    <input type="hidden" name="producto_id" id="productoId" value="">

                    <div class="row g-3">
                        <!-- Nombre -->
                        <div class="col-12">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" class="form-control" name="nombre" id="productoNombre"
                                   required maxlength="200" placeholder="Ej: Audífonos Bluetooth">
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="productoDescripcion"
                                      rows="3" maxlength="1000"
                                      placeholder="Describe el producto..."></textarea>
                        </div>

                        <!-- Categoría -->
                        <div class="col-md-4">
                            <label class="form-label">Categoría *</label>
                            <select class="form-select" name="categoria_id" id="productoCategoria" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= sanitizar_salida($cat['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Precio en puntos -->
                        <div class="col-md-4">
                            <label class="form-label">Precio en Puntos *</label>
                            <input type="number" class="form-control" name="precio_puntos" id="productoPrecio"
                                   required min="1" max="999999" placeholder="Ej: 500">
                        </div>

                        <!-- Precio en pesos -->
                        <div class="col-md-4">
                            <label class="form-label">Precio en Pesos MXN</label>
                            <input type="number" class="form-control" name="precio_pesos" id="productoPrecioPesos"
                                   min="0" step="0.01" placeholder="Ej: 150.00">
                        </div>

                        <!-- Stock -->
                        <div class="col-md-4">
                            <label class="form-label">Stock *</label>
                            <input type="number" class="form-control" name="stock" id="productoStock"
                                   required min="0" max="9999" placeholder="Ej: 20">
                        </div>

                        <!-- Imagen -->
                        <div class="col-12">
                            <label class="form-label">Imagen del Producto</label>
                            <input type="file" class="form-control" name="imagen" id="productoImagen"
                                   accept=".jpg,.jpeg,.png,.webp">
                            <div class="form-text">Formatos: JPG, PNG, WEBP. Máximo 2MB.</div>
                            <!-- Preview de imagen actual (en edición) -->
                            <div id="imagenActualDiv" class="mt-2 d-none">
                                <small class="text-muted">Imagen actual:</small><br>
                                <img id="imagenActual" src="" alt="Imagen actual" style="height:80px;border-radius:8px;margin-top:4px">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarProducto">Guardar Producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Estás seguro de eliminar el producto <strong id="nombreProductoEliminar"></strong>?</p>
                <p class="text-danger small">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="productos.php" id="formEliminar">
                    <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="producto_id" id="idProductoEliminar" value="">
                    <button type="submit" class="btn btn-danger">Sí, Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Limpiar el modal para crear un nuevo producto
 */
function limpiarModal() {
    document.getElementById('modalProductoLabel').textContent = 'Nuevo Producto';
    document.getElementById('accionProducto').value = 'crear';
    document.getElementById('productoId').value = '';
    document.getElementById('formProducto').reset();
    document.getElementById('imagenActualDiv').classList.add('d-none');
    document.getElementById('btnGuardarProducto').textContent = 'Guardar Producto';
}

/**
 * Llenar el modal con datos del producto para editar
 * @param {Object} producto - Datos del producto desde PHP
 */
function editarProducto(producto) {
    document.getElementById('modalProductoLabel').textContent = 'Editar Producto';
    document.getElementById('accionProducto').value = 'editar';
    document.getElementById('productoId').value = producto.id;
    document.getElementById('productoNombre').value = producto.nombre;
    document.getElementById('productoDescripcion').value = producto.descripcion || '';
    document.getElementById('productoCategoria').value = producto.categoria_id;
    document.getElementById('productoPrecio').value = producto.precio_puntos;
    document.getElementById('productoPrecioPesos').value = producto.precio_pesos || '';
    document.getElementById('productoStock').value = producto.stock;
    document.getElementById('btnGuardarProducto').textContent = 'Actualizar Producto';

    // Mostrar imagen actual
    if (producto.imagen_url) {
        document.getElementById('imagenActual').src = '../' + producto.imagen_url;
        document.getElementById('imagenActualDiv').classList.remove('d-none');
    }

    // Abrir el modal
    new bootstrap.Modal(document.getElementById('modalProducto')).show();
}

/**
 * Mostrar modal de confirmación de eliminación
 * @param {number} id - ID del producto
 * @param {string} nombre - Nombre del producto
 */
function confirmarEliminar(id, nombre) {
    document.getElementById('idProductoEliminar').value = id;
    document.getElementById('nombreProductoEliminar').textContent = nombre;
    new bootstrap.Modal(document.getElementById('modalEliminar')).show();
}

// Validación client-side del formulario de producto
document.getElementById('formProducto').addEventListener('submit', function(e) {
    const nombre      = document.getElementById('productoNombre').value.trim();
    const categoria = document.getElementById('productoCategoria').value;
    const precio    = parseInt(document.getElementById('productoPrecio').value);
    const stock     = parseInt(document.getElementById('productoStock').value);

    if (!nombre || !categoria || isNaN(precio) || precio < 1 || isNaN(stock) || stock < 0) {
        e.preventDefault();
        alert('Por favor completa todos los campos obligatorios correctamente.');
        return;
    }

    // Validar imagen si se seleccionó
    const imagenInput = document.getElementById('productoImagen');
    if (imagenInput.files.length > 0) {
        const archivo = imagenInput.files[0];
        const maxSize = 2 * 1024 * 1024; // 2MB
        const tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

        if (archivo.size > maxSize) {
            e.preventDefault();
            alert('La imagen no debe superar 2MB.');
            return;
        }
        if (!tiposPermitidos.includes(archivo.type)) {
            e.preventDefault();
            alert('Solo se permiten imágenes JPG, PNG o WEBP.');
            return;
        }
    }
});
</script>

</body>
</html>
