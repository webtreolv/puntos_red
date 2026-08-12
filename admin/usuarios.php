<?php
/**
 * admin/usuarios.php
 * Gestión de usuarios: ver, agregar puntos, cambiar contraseña, activar/desactivar
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once 'includes/funciones_usuarios.php';

validar_sesion_admin();

$mensaje = '';
$tipo_mensaje = '';
$csrf_token = generar_token_csrf();

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_token_csrf()) {
        $mensaje = 'Error de seguridad.';
        $tipo_mensaje = 'danger';
    } else {
        $accion = $_POST['accion'] ?? '';

        // Cambiar contraseña
        if ($accion === 'cambiar_contrasena') {
            $uid   = (int)($_POST['usuario_id'] ?? 0);
            $pass1 = $_POST['nueva_pass']    ?? '';
            $pass2 = $_POST['confirmar_pass'] ?? '';

            if ($uid <= 0 || strlen($pass1) < 8) {
                $mensaje = 'La contraseña debe tener al menos 8 caracteres.';
                $tipo_mensaje = 'danger';
            } elseif ($pass1 !== $pass2) {
                $mensaje = 'Las contraseñas no coinciden.';
                $tipo_mensaje = 'danger';
            } elseif (cambiar_contrasena($uid, $pass1)) {
                $mensaje = 'Contraseña actualizada correctamente.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al cambiar la contraseña.';
                $tipo_mensaje = 'danger';
            }
        }

        // Cambiar estado (activar/desactivar)
        elseif ($accion === 'cambiar_estado') {
            $uid    = (int)($_POST['usuario_id'] ?? 0);
            $estado = $_POST['nuevo_estado'] ?? '';
            if ($uid > 0 && cambiar_estado_usuario($uid, $estado)) {
                $mensaje = 'Estado del usuario actualizado.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al cambiar el estado.';
                $tipo_mensaje = 'danger';
            }
        }

        // Crear nuevo usuario
        elseif ($accion === 'crear_usuario') {
            $nombre = trim($_POST['nombre'] ?? '');
            $email  = trim($_POST['email']  ?? '');
            $pass   = $_POST['pass']   ?? '';
            $puntos = (int)($_POST['puntos'] ?? 0);
            $rol    = $_POST['rol'] ?? 'usuario';

            if (empty($nombre) || !validar_email($email) || strlen($pass) < 8) {
                $mensaje = 'Datos inválidos. Verifica nombre, email y contraseña (mín. 8 caracteres).';
                $tipo_mensaje = 'danger';
            } elseif (!in_array($rol, ['admin', 'usuario'])) {
                $mensaje = 'Rol no válido.';
                $tipo_mensaje = 'danger';
            } elseif (crear_usuario($nombre, $email, $pass, $puntos, $rol)) {
                $mensaje = 'Usuario creado exitosamente.';
                $tipo_mensaje = 'success';
            } else {
                $mensaje = 'Error al crear el usuario. El email puede ya estar registrado.';
                $tipo_mensaje = 'danger';
            }
        }
    }
}

// Filtro por rol
$filtro_rol = $_GET['rol'] ?? null;
if (!in_array($filtro_rol, ['admin', 'usuario'])) $filtro_rol = null;

$usuarios = obtener_usuarios($filtro_rol);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Gestión de Usuarios</h2>
            <p class="text-muted mb-0"><?= count($usuarios) ?> usuario(s)</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario">
            + Nuevo Usuario
        </button>
    </div>

    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show mb-4">
        <?= sanitizar_salida($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="d-flex gap-2 align-items-center">
                <span class="text-muted fw-semibold me-2">Filtrar:</span>
                <a href="usuarios.php" class="btn btn-sm <?= $filtro_rol === null ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
                <a href="usuarios.php?rol=usuario" class="btn btn-sm <?= $filtro_rol === 'usuario' ? 'btn-primary' : 'btn-outline-secondary' ?>">Usuarios</a>
                <a href="usuarios.php?rol=admin" class="btn btn-sm <?= $filtro_rol === 'admin' ? 'btn-primary' : 'btn-outline-secondary' ?>">Admins</a>
            </div>
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Puntos</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5">No hay usuarios</td></tr>
                        <?php else: ?>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong>#<?= $u['id'] ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:32px;height:32px;background:linear-gradient(135deg,#dc2626,#ef4444);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:13px;flex-shrink:0">
                                        <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                    </div>
                                    <?= sanitizar_salida($u['nombre']) ?>
                                </div>
                            </td>
                            <td><?= sanitizar_salida($u['email']) ?></td>
                            <td><strong class="text-primary"><?= number_format($u['puntos_disponibles']) ?></strong></td>
                            <td>
                                <span class="badge <?= $u['rol'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>">
                                    <?= $u['rol'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['estado'] === 'activo' ? 'badge-activo' : 'badge-inactivo' ?>">
                                    <?= $u['estado'] ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['fecha_creacion'])) ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <!-- Ver historial -->
                                    <button class="btn btn-sm btn-outline-info"
                                            onclick="verHistorial(<?= $u['id'] ?>, '<?= sanitizar_salida($u['nombre']) ?>')"
                                            title="Ver historial de puntos">
                                        Historial
                                    </button>
                                    <?php if ($u['rol'] === 'usuario'): ?>
                                    <!-- Agregar puntos -->
                                    <button class="btn btn-sm btn-success"
                                            onclick="abrirModalPuntos(<?= $u['id'] ?>, '<?= sanitizar_salida($u['nombre']) ?>', <?= $u['puntos_disponibles'] ?>)"
                                            title="Agregar/restar puntos">
                                        Puntos
                                    </button>
                                    <!-- Cambiar contraseña -->
                                    <button class="btn btn-sm btn-warning"
                                            onclick="abrirModalContrasena(<?= $u['id'] ?>, '<?= sanitizar_salida($u['nombre']) ?>')"
                                            title="Cambiar contraseña">
                                        Contraseña
                                    </button>
                                    <!-- Activar/Desactivar -->
                                    <form method="POST" action="usuarios.php" style="display:inline">
                                        <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="usuario_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $u['estado'] === 'activo' ? 'inactivo' : 'activo' ?>">
                                        <button type="submit" class="btn btn-sm <?= $u['estado'] === 'activo' ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                onclick="return confirm('¿Cambiar estado del usuario?')">
                                            <?= $u['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
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

<!-- Modal: Agregar/Restar Puntos -->
<div class="modal fade" id="modalPuntos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestionar Puntos - <span id="nombreUsuarioPuntos"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Puntos actuales: <strong id="puntosActualesModal" class="text-primary"></strong></p>
                <div id="respuestaPuntos" class="alert d-none mb-3"></div>
                <div class="mb-3">
                    <label class="form-label">Cantidad de Puntos</label>
                    <input type="number" class="form-control" id="cantidadPuntos"
                           placeholder="Positivo para agregar, negativo para restar">
                    <div class="form-text">Usa número negativo para restar puntos.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motivo</label>
                    <select class="form-select" id="motivoPuntos">
                        <option value="campaña">Campaña</option>
                        <option value="promoción">Promoción</option>
                        <option value="ajuste">Ajuste</option>
                        <option value="devolucion">Devolución</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcionPuntos" rows="2"
                              placeholder="Detalle del movimiento..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarPuntos()">Guardar Puntos</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Contraseña -->
<div class="modal fade" id="modalContrasena" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cambiar Contraseña - <span id="nombreUsuarioPass"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="usuarios.php" id="formContrasena">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                    <input type="hidden" name="accion" value="cambiar_contrasena">
                    <input type="hidden" name="usuario_id" id="uidContrasena">
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña</label>
                        <input type="password" class="form-control" name="nueva_pass" id="nuevaPass"
                               required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" name="confirmar_pass" id="confirmarPass"
                               required minlength="8" placeholder="Repetir contraseña">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Historial de Puntos -->
<div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historial de Puntos - <span id="nombreUsuarioHistorial"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="contenidoHistorial">
                    <div class="text-center py-4">Cargando...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Crear Usuario -->
<div class="modal fade" id="modalCrearUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="usuarios.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                    <input type="hidden" name="accion" value="crear_usuario">
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre" required maxlength="150">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="pass" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Puntos Iniciales</label>
                        <input type="number" class="form-control" name="puntos" value="0" min="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol">
                            <option value="usuario">Usuario</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let usuarioIdActual = 0;

function abrirModalPuntos(id, nombre, puntosActuales) {
    usuarioIdActual = id;
    document.getElementById('nombreUsuarioPuntos').textContent = nombre;
    document.getElementById('puntosActualesModal').textContent = puntosActuales.toLocaleString() + ' pts';
    document.getElementById('cantidadPuntos').value = '';
    document.getElementById('motivoPuntos').value = 'campaña';
    document.getElementById('descripcionPuntos').value = '';
    document.getElementById('respuestaPuntos').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('modalPuntos')).show();
}

function guardarPuntos() {
    const cantidad    = parseInt(document.getElementById('cantidadPuntos').value);
    const motivo      = document.getElementById('motivoPuntos').value;
    const descripcion = document.getElementById('descripcionPuntos').value.trim();

    if (isNaN(cantidad) || cantidad === 0) {
        alert('Ingresa una cantidad válida (diferente de 0).');
        return;
    }

    fetch('../api/agregar_puntos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            usuario_id:  usuarioIdActual,
            cantidad:    cantidad,
            motivo:      motivo,
            descripcion: descripcion
        })
    })
    .then(r => r.json())
    .then(data => {
        const div = document.getElementById('respuestaPuntos');
        div.classList.remove('d-none', 'alert-success', 'alert-danger');
        if (data.success) {
            div.classList.add('alert-success');
            div.textContent = data.mensaje + ' Nuevos puntos: ' + data.puntos_nuevos.toLocaleString();
            document.getElementById('puntosActualesModal').textContent = data.puntos_nuevos.toLocaleString() + ' pts';
            setTimeout(() => location.reload(), 1500);
        } else {
            div.classList.add('alert-danger');
            div.textContent = data.mensaje;
        }
    })
    .catch(() => alert('Error de conexión.'));
}

function abrirModalContrasena(id, nombre) {
    document.getElementById('uidContrasena').value = id;
    document.getElementById('nombreUsuarioPass').textContent = nombre;
    document.getElementById('nuevaPass').value = '';
    document.getElementById('confirmarPass').value = '';
    new bootstrap.Modal(document.getElementById('modalContrasena')).show();
}

document.getElementById('formContrasena').addEventListener('submit', function(e) {
    const p1 = document.getElementById('nuevaPass').value;
    const p2 = document.getElementById('confirmarPass').value;
    if (p1 !== p2) {
        e.preventDefault();
        alert('Las contraseñas no coinciden.');
    }
});

function verHistorial(id, nombre) {
    document.getElementById('nombreUsuarioHistorial').textContent = nombre;
    document.getElementById('contenidoHistorial').innerHTML = '<div class="text-center py-4">Cargando...</div>';
    new bootstrap.Modal(document.getElementById('modalHistorial')).show();

    fetch('../api/historial_puntos.php?usuario_id=' + id)
        .then(r => r.json())
        .then(data => {
            if (!data.success || data.historial.length === 0) {
                document.getElementById('contenidoHistorial').innerHTML =
                    '<p class="text-center text-muted py-4">Sin movimientos registrados.</p>';
                return;
            }
            let html = '<div class="table-responsive"><table class="table table-sm">';
            html += '<thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Motivo</th><th>Admin</th></tr></thead><tbody>';
            data.historial.forEach(h => {
                const color = h.cantidad_puntos >= 0 ? 'text-success' : 'text-danger';
                const signo = h.cantidad_puntos >= 0 ? '+' : '';
                html += `<tr>
                    <td>${h.fecha_movimiento}</td>
                    <td><span class="badge bg-secondary">${h.tipo_movimiento}</span></td>
                    <td class="${color} fw-bold">${signo}${parseInt(h.cantidad_puntos).toLocaleString()}</td>
                    <td>${h.motivo || '-'}</td>
                    <td>${h.admin_nombre || 'Sistema'}</td>
                </tr>`;
            });
            html += '</tbody></table></div>';
            document.getElementById('contenidoHistorial').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('contenidoHistorial').innerHTML =
                '<p class="text-center text-danger py-4">Error al cargar el historial.</p>';
        });
}
</script>
</body>
</html>
