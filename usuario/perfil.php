<?php
/**
 * usuario/perfil.php
 * Perfil del usuario: datos, puntos y transacciones
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_usuario();

$usuario = obtener_usuario_sesion();

// Obtener últimas 5 transacciones de puntos
$stmt = $pdo->prepare(
    "SELECT hp.*, u.nombre as admin_nombre
     FROM historial_puntos hp
     LEFT JOIN usuarios u ON hp.admin_id = u.id
     WHERE hp.usuario_id = ?
     ORDER BY hp.fecha_movimiento DESC
     LIMIT 5"
);
$stmt->execute([$usuario['id']]);
$transacciones = $stmt->fetchAll();

// Obtener fecha de registro (de la tabla usuarios)
$stmt = $pdo->prepare("SELECT fecha_creacion FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$fecha_registro = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Puntos Red</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Mi Perfil</h2>
        <p class="text-muted mb-0">Gestiona tu información y puntos</p>
    </div>

    <div class="row g-4">

        <!-- Columna izquierda: Datos del usuario -->
        <div class="col-lg-4">
            <!-- Tarjeta de puntos disponibles -->
            <div class="card text-center mb-4" style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white;">
                <div class="card-body py-4">
                    <div class="mb-2" style="font-size: 48px;">🎯</div>
                    <div class="text-white-50 small">PUNTOS DISPONIBLES</div>
                    <div class="display-4 fw-bold my-2">
                        <?= number_format($usuario['puntos']) ?>
                    </div>
                    <div class="small text-white-50">puntos canjeables</div>
                </div>
            </div>

            <!-- Datos del perfil -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Información Personal</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Nombre completo</label>
                        <div class="fw-semibold"><?= sanitizar_salida($usuario['nombre']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Correo electrónico</label>
                        <div class="fw-semibold"><?= sanitizar_salida($usuario['email']) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">Fecha de registro</label>
                        <div class="fw-semibold">
                            <?= $fecha_registro ? date('d/m/Y', strtotime($fecha_registro)) : 'No disponible' ?>
                        </div>
                    </div>
                    <hr>
                    <button class="btn btn-outline-primary w-100"
                            onclick="abrirModalPassword()">
                        🔑 Cambiar Contraseña
                    </button>
                </div>
            </div>
        </div>

        <!-- Columna derecha: Historial de transacciones -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Últimas Transacciones</h5>
                    <a href="#" class="btn btn-sm btn-outline-secondary">Ver todo</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transacciones)): ?>
                    <div class="text-center py-5 text-muted">
                        <div style="font-size: 48px;">📊</div>
                        <p class="mt-2">No hay transacciones registradas</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Puntos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transacciones as $t): ?>
                                <tr>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y', strtotime($t['fecha_movimiento'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $es_entrada = $t['cantidad_puntos'] > 0;
                                        $badge_tipo = $es_entrada ? 'bg-success' : 'bg-danger';
                                        $texto_tipo = $es_entrada ? 'Entrada' : 'Gasto';
                                        ?>
                                        <span class="badge <?= $badge_tipo ?>">
                                            <?= $texto_tipo ?>
                                        </span>
                                    </td>
                                    <td class="small">
                                        <?= sanitizar_salida($t['descripcion'] ?: $t['motivo']) ?>
                                    </td>
                                    <td class="text-end fw-semibold <?= $es_entrada ? 'text-success' : 'text-danger' ?>">
                                        <?= $es_entrada ? '+' : '' ?><?= number_format($t['cantidad_puntos']) ?> pts
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Información adicional -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">¿Cómo funcionan los puntos?</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">🛒</div>
                            <div class="fw-semibold mt-2">Canjea Productos</div>
                            <p class="text-muted small mb-0">
                                Usa tus puntos para obtener productos del catálogo
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">📦</div>
                            <div class="fw-semibold mt-2">Recibe tus Pedidos</div>
                            <p class="text-muted small mb-0">
                                Una vez confirmado, recibe tus productos
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">✨</div>
                            <div class="fw-semibold mt-2">Gana Más Puntos</div>
                            <p class="text-muted small mb-0">
                                Los administradores pueden darte puntos extra
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
</div>

<!-- Modal: Cambiar Contraseña -->
<div class="modal fade" id="modalPassword" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCambiarPassword" onsubmit="cambiarPassword(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <input type="password" class="form-control" name="password_actual" required
                               minlength="6" placeholder="Ingresa tu contraseña actual">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" class="form-control" name="password_nueva" required
                               minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" class="form-control" name="password_confirmar" required
                               minlength="6" placeholder="Repite la nueva contraseña">
                    </div>
                    <div id="mensajePassword" class="alert d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnCambiarPassword">
                        Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/**
 * Función: abrirModalPassword()
 * Abre el modal para cambiar contraseña
 */
function abrirModalPassword() {
    document.getElementById('mensajePassword').className = 'alert d-none';
    document.getElementById('formCambiarPassword').reset();
    new bootstrap.Modal(document.getElementById('modalPassword')).show();
}

/**
 * Función: cambiarPassword(event)
 * Envía el formulario de cambio de contraseña via AJAX
 */
function cambiarPassword(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    const passwordNueva = formData.get('password_nueva');
    const passwordConfirmar = formData.get('password_confirmar');

    if (passwordNueva !== passwordConfirmar) {
        mostrarMensajePassword('Las contraseñas no coinciden.', 'danger');
        return;
    }

    if (passwordNueva.length < 6) {
        mostrarMensajePassword('La contraseña debe tener al menos 6 caracteres.', 'danger');
        return;
    }

    const btn = document.getElementById('btnCambiarPassword');
    btn.disabled = true;
    btn.textContent = 'Cambiando...';

    const base = window.location.pathname.includes('/usuario/') ? '../' : '';

    fetch(base + 'api/cambiar_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            password_actual: formData.get('password_actual'),
            password_nueva: passwordNueva
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarMensajePassword(data.mensaje, 'success');
            setTimeout(() => {
                bootstrap.Modal.getInstance(document.getElementById('modalPassword')).hide();
            }, 1500);
        } else {
            mostrarMensajePassword(data.mensaje, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensajePassword('Error de conexión. Intenta de nuevo.', 'danger');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Cambiar Contraseña';
    });
}

function mostrarMensajePassword(mensaje, tipo) {
    const div = document.getElementById('mensajePassword');
    div.className = 'alert alert-' + tipo;
    div.textContent = mensaje;
    div.classList.remove('d-none');
}
</script>
</body>
</html>