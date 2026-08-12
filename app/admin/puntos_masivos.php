<?php
/**
 * admin/puntos_masivos.php
 * Asignación masiva de puntos a múltiples usuarios
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once 'includes/funciones_usuarios.php';

validar_sesion_admin();

$mensaje = '';
$tipo_mensaje = '';
$csrf_token = generar_token_csrf();
$resultado_masivo = null;

// Procesar asignación masiva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validar_token_csrf()) {
        $mensaje = 'Error de seguridad.';
        $tipo_mensaje = 'danger';
    } else {
        $usuarios_ids = $_POST['usuarios_ids'] ?? [];
        $cantidad     = (int)($_POST['cantidad']    ?? 0);
        $motivo       = trim($_POST['motivo']       ?? '');
        $descripcion  = trim($_POST['descripcion']  ?? '');
        $admin_id     = $_SESSION['usuario_id'];

        // Validaciones
        if (empty($usuarios_ids)) {
            $mensaje = 'Selecciona al menos un usuario.';
            $tipo_mensaje = 'danger';
        } elseif ($cantidad === 0) {
            $mensaje = 'La cantidad de puntos no puede ser 0.';
            $tipo_mensaje = 'danger';
        } else {
            $exitosos = 0;
            $fallidos = 0;
            $errores  = [];

            // Procesar cada usuario seleccionado
            foreach ($usuarios_ids as $uid) {
                $uid = (int)$uid;
                if ($uid <= 0) continue;

                $res = agregar_puntos($uid, $cantidad, $motivo, $descripcion, $admin_id);
                if ($res['success']) {
                    $exitosos++;
                } else {
                    $fallidos++;
                    $errores[] = "Usuario #$uid: " . $res['mensaje'];
                }
            }

            $resultado_masivo = [
                'exitosos' => $exitosos,
                'fallidos' => $fallidos,
                'errores'  => $errores
            ];

            if ($exitosos > 0) {
                $mensaje = "Puntos asignados a $exitosos usuario(s) exitosamente.";
                $tipo_mensaje = 'success';
            }
            if ($fallidos > 0) {
                $mensaje .= " $fallidos usuario(s) con error.";
                $tipo_mensaje = $exitosos > 0 ? 'warning' : 'danger';
            }
        }
    }
}

// Obtener solo usuarios normales para la tabla
$usuarios = obtener_usuarios('usuario');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Puntos Masivos - Puntos Red Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="../assets/css/estilos.css" rel="stylesheet">
</head>
<body>

<?php require_once '../includes/navbar.php'; ?>

<div class="main-content">
<div class="container-fluid px-4">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Puntos Masivos</h2>
        <p class="text-muted mb-0">Asigna puntos a múltiples usuarios simultáneamente</p>
    </div>

    <?php if (!empty($mensaje)): ?>
    <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show mb-4">
        <?= sanitizar_salida($mensaje) ?>
        <?php if (!empty($resultado_masivo['errores'])): ?>
        <ul class="mt-2 mb-0">
            <?php foreach ($resultado_masivo['errores'] as $err): ?>
            <li><?= sanitizar_salida($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Buscador de usuarios -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label">Buscar Usuario</label>
                    <input type="text" class="form-control" id="busquedaUsuario" 
                           placeholder="Buscar por nombre o email..." 
                           onkeyup="buscarUsuarioEnBD(this.value)">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">
                        Limpiar
                    </button>
                </div>
            </div>
            <!-- Resultados de búsqueda en BD -->
            <div id="resultadosBusqueda" class="mt-3" style="display:none">
                <div class="border rounded p-2" style="max-height:200px;overflow-y:auto">
                    <table class="table table-sm table-hover mb-0" id="tablaResultados">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Puntos</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyResultados"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Tabla de usuarios con checkboxes -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Seleccionar Usuarios (<span id="contadorSeleccionados">0</span> seleccionados)</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="seleccionarTodos()">
                            Seleccionar Todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deseleccionarTodos()">
                            Limpiar
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px">
                                        <input type="checkbox" id="checkTodos" onchange="toggleTodos(this)"
                                               class="form-check-input">
                                    </th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Puntos Actuales</th>
                                    <th style="width:80px">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tablaUsuarios">
                                <tr id="filaVacia">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Busca usuarios arriba y agrégalos a la lista
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Formulario de asignación -->
        <div class="col-lg-4">
            <div class="card" style="position:sticky;top:80px">
                <div class="card-header">
                    <span>Configurar Asignación</span>
                </div>
                <form method="POST" action="puntos_masivos.php" id="formMasivo">
                    <div class="card-body">
                        <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">
                        <!-- Los IDs de usuarios se agregan dinámicamente -->
                        <div id="inputsUsuarios"></div>

                        <div class="mb-3">
                            <label class="form-label">Cantidad de Puntos *</label>
                            <input type="number" class="form-control" name="cantidad" id="cantidadMasiva"
                                   required placeholder="Ej: 100 (negativo para restar)">
                            <div class="form-text">Usa número negativo para restar puntos.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo *</label>
                            <div class="d-flex gap-2">
                                <select class="form-select" name="motivo" id="selectMotivo" required>
                                    <option value="">Seleccionar...</option>
                                </select>
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoMotivo">
                                    + Nuevo
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="3"
                                      placeholder="Detalle de la asignación..."></textarea>
                        </div>

                        <!-- Resumen de selección -->
                        <div class="alert alert-info py-2 px-3 mb-3" id="resumenSeleccion">
                            <small>Selecciona usuarios de la tabla</small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary w-100" id="btnAsignar" disabled
                                onclick="return confirmarAsignacion()">
                            Asignar Puntos
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
</div>

<!-- Modal: Gestor de Motivos -->
<div class="modal fade" id="modalNuevoMotivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gestionar Motivos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Formulario para crear/editar -->
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="motivoNombre" 
                               placeholder="Nombre del motivo" maxlength="50">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary w-100" id="btnGuardarMotivo" onclick="guardarMotivo()">Agregar</button>
                    </div>
                </div>
                <input type="hidden" id="motivoIdEditando" value="">
                <!-- Tabla de motivos -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th style="width:150px">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaMotivos"></tbody>
                    </table>
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
// Cargar motivos al iniciar
document.addEventListener('DOMContentLoaded', function() {
    cargarMotivos();
});

function cargarMotivos() {
    fetch('../api/motivos.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            
            const select = document.getElementById('selectMotivo');
            const tbody = document.getElementById('tablaMotivos');
            
            // Limpiar
            select.innerHTML = '<option value="">Seleccionar...</option>';
            tbody.innerHTML = '';
            
            data.motivos.forEach(m => {
                // Agregar al select
                const option = document.createElement('option');
                option.value = m.nombre;
                option.textContent = m.nombre;
                select.appendChild(option);
                
                // Agregar a la tabla
                const estadoClass = m.estado === 'activo' ? 'badge-activo' : 'badge-inactivo';
                tbody.innerHTML += `
                    <tr>
                        <td><span class="motivo-nombre">${m.nombre}</span></td>
                        <td><span class="badge ${estadoClass}">${m.estado}</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="editarMotivo(${m.id}, '${m.nombre.replace(/'/g, "\\'")}')">✏️</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="eliminarMotivo(${m.id})">🗑️</button>
                        </td>
                    </tr>
                `;
            });
        });
}

function abrirGestorMotivos() {
    cargarMotivos();
    document.getElementById('motivoNombre').value = '';
    document.getElementById('motivoIdEditando').value = '';
    document.getElementById('btnGuardarMotivo').textContent = 'Agregar';
    new bootstrap.Modal(document.getElementById('modalNuevoMotivo')).show();
}

function guardarMotivo() {
    const nombre = document.getElementById('motivoNombre').value.trim();
    const idEditando = document.getElementById('motivoIdEditando').value;
    
    if (!nombre) {
        alert('Ingresa el nombre del motivo.');
        return;
    }
    
    const accion = idEditando ? 'editar' : 'crear';
    const data = { accion, nombre };
    if (idEditando) data.id = parseInt(idEditando);
    
    fetch('../api/motivos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            cargarMotivos();
            document.getElementById('motivoNombre').value = '';
            document.getElementById('motivoIdEditando').value = '';
            document.getElementById('btnGuardarMotivo').textContent = 'Agregar';
        } else {
            alert(res.mensaje);
        }
    });
}

function editarMotivo(id, nombre) {
    document.getElementById('motivoNombre').value = nombre;
    document.getElementById('motivoIdEditando').value = id;
    document.getElementById('btnGuardarMotivo').textContent = 'Actualizar';
}

function eliminarMotivo(id) {
    if (!confirm('¿Eliminar este motivo?')) return;
    
    fetch('../api/motivos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'eliminar', id })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            cargarMotivos();
        } else {
            alert(res.mensaje);
        }
    });
}
</script>
<script>
// IDs ya seleccionados para excluir de búsquedas
let idsSeleccionados = [];

function actualizarContador() {
    const checks = document.querySelectorAll('.check-usuario:checked');
    const total  = checks.length;
    document.getElementById('contadorSeleccionados').textContent = total;

    // Actualizar lista de IDs seleccionados
    idsSeleccionados = Array.from(checks).map(c => parseInt(c.value));

    // Actualizar inputs ocultos con los IDs seleccionados
    const container = document.getElementById('inputsUsuarios');
    container.innerHTML = '';
    checks.forEach(c => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'usuarios_ids[]';
        input.value = c.value;
        container.appendChild(input);
    });

    // Actualizar resumen
    const cantidad = parseInt(document.getElementById('cantidadMasiva').value) || 0;
    document.getElementById('resumenSeleccion').innerHTML =
        `<small><strong>${total}</strong> usuario(s) seleccionado(s)<br>
         Cantidad: <strong>${cantidad !== 0 ? (cantidad > 0 ? '+' : '') + cantidad.toLocaleString() : 'no definida'}</strong> puntos</small>`;

    // Habilitar/deshabilitar botón
    document.getElementById('btnAsignar').disabled = total === 0;
}

function seleccionarTodos() {
    document.querySelectorAll('.check-usuario:not(:disabled)').forEach(c => c.checked = true);
    document.getElementById('checkTodos').checked = true;
    actualizarContador();
}

function deseleccionarTodos() {
    document.querySelectorAll('.check-usuario').forEach(c => c.checked = false);
    document.getElementById('checkTodos').checked = false;
    actualizarContador();
}

function toggleTodos(checkbox) {
    if (checkbox.checked) seleccionarTodos();
    else deseleccionarTodos();
}

function confirmarAsignacion() {
    const total    = document.querySelectorAll('.check-usuario:checked').length;
    const cantidad = parseInt(document.getElementById('cantidadMasiva').value) || 0;
    if (total === 0) { alert('Selecciona al menos un usuario.'); return false; }
    if (cantidad === 0) { alert('Ingresa una cantidad válida.'); return false; }
    return confirm(`¿Asignar ${cantidad > 0 ? '+' : ''}${cantidad.toLocaleString()} puntos a ${total} usuario(s)?`);
}

// Actualizar resumen cuando cambia la cantidad
document.getElementById('cantidadMasiva').addEventListener('input', actualizarContador);

// Buscar usuarios en la base de datos
let timeoutBusqueda = null;
function buscarUsuarioEnBD(termino) {
    clearTimeout(timeoutBusqueda);
    const resultadosDiv = document.getElementById('resultadosBusqueda');
    const tbody = document.getElementById('tbodyResultados');
    
    if (termino.trim().length < 2) {
        resultadosDiv.style.display = 'none';
        return;
    }
    
    // Excluir IDs já seleccionados
    const excluir = idsSeleccionados.join(',');
    
    timeoutBusqueda = setTimeout(() => {
        fetch(`../api/buscar_usuarios.php?q=${encodeURIComponent(termino)}&excluir=${excluir}`)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    resultadosDiv.style.display = 'none';
                    return;
                }
                
                tbody.innerHTML = '';
                if (data.usuarios.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-muted text-center">Sin resultados</td></tr>';
                } else {
                    data.usuarios.forEach(u => {
                        const yaSeleccionado = idsSeleccionados.includes(u.id);
                        const estadoClass = u.estado === 'activo' ? 'badge-activo' : 'badge-inactivo';
                        const btnAccion = yaSeleccionado 
                            ? '<span class="text-muted">Ya seleccionado</span>'
                            : `<button type="button" class="btn btn-sm btn-primary" onclick="agregarUsuario(${u.id}, '${u.nombre.replace(/'/g, "\\'")}', '${u.email.replace(/'/g, "\\'")}', ${u.puntos_disponibles}, '${u.estado}')">+ Agregar</button>`;
                        
                        const fila = `<tr>
                            <td>${u.id}</td>
                            <td>${u.nombre}</td>
                            <td>${u.email}</td>
                            <td>${u.puntos_disponibles.toLocaleString()} pts</td>
                            <td><span class="badge ${estadoClass}">${u.estado}</span></td>
                            <td>${btnAccion}</td>
                        </tr>`;
                        tbody.innerHTML += fila;
                    });
                }
                resultadosDiv.style.display = 'block';
            })
            .catch(() => {
                resultadosDiv.style.display = 'none';
            });
    }, 300);
}

// Quitar usuario de la selección
function quitarUsuario(id) {
    const checkbox = document.querySelector(`.check-usuario[value="${id}"]`);
    if (checkbox) {
        checkbox.checked = false;
        // Ocultar la fila completa
        const fila = checkbox.closest('tr');
        fila.style.display = 'none';
        actualizarContador();
    }
}

function agregarUsuario(id, nombre, email, puntos, estado) {
    // Verificar si ya está seleccionado
    if (idsSeleccionados.includes(id)) {
        alert('El usuario #' + id + ' ya está seleccionado.');
        return;
    }
    
    if (estado !== 'activo') {
        alert('El usuario #' + id + ' está inactivo.');
        return;
    }
    
    // Agregar a la tabla si no existe
    let checkbox = document.querySelector(`.check-usuario[value="${id}"]`);
    if (!checkbox) {
        // Agregar fila a la tabla con botón Quitar
        const tbody = document.getElementById('tablaUsuarios');
        const tr = document.createElement('tr');
        tr.className = 'fila-usuario';
        tr.dataset.nombre = nombre;
        tr.dataset.email = email;
        tr.innerHTML = `
            <td><input type="checkbox" class="form-check-input check-usuario" value="${id}" checked onchange="actualizarContador()"></td>
            <td>${nombre}</td>
            <td>${email}</td>
            <td><strong class="text-primary">${puntos.toLocaleString()} pts</strong></td>
            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="quitarUsuario(${id})">Quitar</button></td>
        `;
        tbody.appendChild(tr);
    } else {
        checkbox.checked = true;
        // También mostrar la fila si estaba oculta
        const fila = checkbox.closest('tr');
        fila.style.display = '';
    }
    
    actualizarContador();
    alert('Usuario #' + id + ' agregado a la selección.');
    
    // Limpiar búsqueda
    document.getElementById('busquedaUsuario').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
}

// Limpiar filtros
function limpiarFiltros() {
    document.getElementById('busquedaUsuario').value = '';
    document.getElementById('resultadosBusqueda').style.display = 'none';
    
    // Mostrar todas las filas de la tabla
    document.querySelectorAll('.fila-usuario').forEach(fila => {
        fila.style.display = '';
    });
}
</script>
</body>
</html>
