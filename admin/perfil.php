<?php
/**
 * admin/perfil.php
 * Perfil del administrador: datos y configuración
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_admin();

$usuario = obtener_usuario_sesion();

$stmt = $pdo->prepare("SELECT fecha_creacion FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$fecha_registro = $stmt->fetchColumn();

// Obtener estadísticas rápidas del admin
$stmt = $pdo->prepare("SELECT COUNT(*) as total_usuarios FROM usuarios WHERE rol = 'usuario' AND estado = 'activo'");
$stmt->execute();
$total_usuarios = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total_pedidos FROM pedidos WHERE estado = 'Solicitud'");
$stmt->execute();
$pedidos_pendientes = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Puntos Red Admin</title>
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
        <p class="text-muted mb-0">Gestiona tu información de administrador</p>
    </div>

    <div class="row g-4">

        <!-- Columna izquierda: Datos del admin -->
        <div class="col-lg-4">
            <!-- Tarjeta de info -->
            <div class="card text-center mb-4" style="background: linear-gradient(135deg, #dc2626, #ef4444); color: white;">
                <div class="card-body py-4">
                    <div class="perfil-avatar mb-3" style="width:80px;height:80px;font-size:32px;background:rgba(255,255,255,0.2);">
                        <?= strtoupper(substr($usuario['nombre'], 0, 1)) ?>
                    </div>
                    <div class="display-5 fw-bold">
                        <?= sanitizar_salida($usuario['nombre']) ?>
                    </div>
                    <div class="small text-white-50 mt-1">Administrador</div>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Estadísticas del Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="display-5 fw-bold text-primary"><?= number_format($total_usuarios) ?></div>
                            <div class="text-muted small">Usuarios Activos</div>
                        </div>
                        <div class="col-6">
                            <div class="display-5 fw-bold text-warning"><?= number_format($pedidos_pendientes) ?></div>
                            <div class="text-muted small">Pedidos Pendientes</div>
                        </div>
                    </div>
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

        <!-- Columna derecha: Información del sistema -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">Información del Sistema</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-4 bg-light rounded-3">
                                <div style="font-size: 32px;">👥</div>
                                <div class="fw-semibold mt-2">Gestión de Usuarios</div>
                                <p class="text-muted small mb-0">
                                    Administra usuarios registrados, consulta sus puntos y historial
                                </p>
                                <a href="usuarios.php" class="btn btn-sm btn-primary mt-2">Ir a Usuarios</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 bg-light rounded-3">
                                <div style="font-size: 32px;">📦</div>
                                <div class="fw-semibold mt-2">Gestión de Productos</div>
                                <p class="text-muted small mb-0">
                                    Agrega, modifica o elimina productos del catálogo
                                </p>
                                <a href="productos.php" class="btn btn-sm btn-primary mt-2">Ir a Productos</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 bg-light rounded-3">
                                <div style="font-size: 32px;">🛒</div>
                                <div class="fw-semibold mt-2">Gestión de Pedidos</div>
                                <p class="text-muted small mb-0">
                                    Procesa y administra los pedidos de los usuarios
                                </p>
                                <a href="pedidos.php" class="btn btn-sm btn-primary mt-2">Ir a Pedidos</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-4 bg-light rounded-3">
                                <div style="font-size: 32px;">⭐</div>
                                <div class="fw-semibold mt-2">Asignación de Puntos</div>
                                <p class="text-muted small mb-0">
                                    Asigna puntos a usuarios de forma individual o masiva
                                </p>
                                <a href="puntos_masivos.php" class="btn btn-sm btn-primary mt-2">Ir a Puntos</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold">¿Qué hace un administrador?</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">👥</div>
                            <div class="fw-semibold mt-2">Gestiona Usuarios</div>
                            <p class="text-muted small mb-0">
                                Controla las cuentas de los usuarios del sistema
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">📦</div>
                            <div class="fw-semibold mt-2">Administra Productos</div>
                            <p class="text-muted small mb-0">
                                Mantén el catálogo actualizado
                            </p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div style="font-size: 32px;">🛒</div>
                            <div class="fw-semibold mt-2">Procesa Pedidos</div>
                            <p class="text-muted small mb-0">
                                Aprueba y gestiona los pedidos
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
function abrirModalPassword() {
    document.getElementById('mensajePassword').className = 'alert d-none';
    document.getElementById('formCambiarPassword').reset();
    new bootstrap.Modal(document.getElementById('modalPassword')).show();
}

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

    fetch('../api/cambiar_password.php', {
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