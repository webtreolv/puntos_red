/**
 * assets/js/ajax.js
 * Funciones AJAX con Fetch API para Puntos Red
 * Maneja: carrito, notificaciones, filtros de productos
 */

/**
 * Función: mostrarToast(mensaje, tipo)
 * Muestra una notificación tipo toast en la esquina superior derecha
 *
 * @param {string} mensaje Texto a mostrar
 * @param {string} tipo    'success', 'danger', 'warning', 'info'
 */
function mostrarToast(mensaje, tipo = 'success') {
    const colores = {
        success: '#10b981',
        danger:  '#ef4444',
        warning: '#f59e0b',
        info:    '#06b6d4'
    };

    // Buscar o crear el contenedor de toasts
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    // Crear el toast
    const toastEl = document.createElement('div');
    toastEl.className = 'toast show align-items-center text-white border-0';
    toastEl.style.backgroundColor = colores[tipo] || colores.info;
    toastEl.style.minWidth = '280px';
    toastEl.style.borderRadius = '10px';
    toastEl.style.marginBottom = '8px';
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-semibold">${mensaje}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    onclick="this.closest('.toast').remove()"></button>
        </div>`;

    container.appendChild(toastEl);

    // Auto-eliminar después de 3 segundos
    setTimeout(() => {
        if (toastEl.parentNode) toastEl.remove();
    }, 3000);
}

/**
 * Función: actualizarBadgeCarrito(cantidad)
 * Actualiza el badge de cantidad en el ícono del carrito en el navbar
 *
 * @param {number} cantidad Nueva cantidad de items en el carrito
 */
function actualizarBadgeCarrito(cantidad) {
    const badge = document.getElementById('badge-carrito');
    if (!badge) return;

    if (cantidad > 0) {
        badge.textContent = cantidad;
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

/**
 * Función: agregarCarrito(productoId, cantidad)
 * Agrega un producto al carrito via AJAX
 *
 * @param {number} productoId ID del producto
 * @param {number} cantidad   Cantidad a agregar
 */
function agregarCarrito(productoId, cantidad = 1) {
    // Validar parámetros
    if (!productoId || productoId <= 0) {
        mostrarToast('ID de producto inválido.', 'danger');
        return;
    }
    if (!cantidad || cantidad <= 0) {
        mostrarToast('La cantidad debe ser mayor a 0.', 'danger');
        return;
    }

    // Determinar ruta base según la ubicación actual
    const esAdmin   = window.location.pathname.includes('/admin/');
    const esUsuario = window.location.pathname.includes('/usuario/');
    const base      = (esAdmin || esUsuario) ? '../' : '';

    // Realizar petición POST al endpoint del carrito
    fetch(base + 'api/carrito.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            accion:      'agregar',
            producto_id: productoId,
            cantidad:    cantidad
        })
    })
    .then(response => {
        if (!response.ok) throw new Error('Error HTTP: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            mostrarToast('✓ ' + data.mensaje, 'success');
            actualizarBadgeCarrito(data.cantidad_carrito);
        } else {
            mostrarToast(data.mensaje, 'danger');
        }
    })
    .catch(error => {
        console.error('Error agregarCarrito:', error);
        mostrarToast('Error de conexión. Intenta de nuevo.', 'danger');
    });
}

/**
 * Función: eliminarCarrito(carritoId, callback)
 * Elimina un item del carrito via AJAX
 *
 * @param {number}   carritoId ID del item en la tabla carrito
 * @param {Function} callback  Función a ejecutar tras eliminar (para actualizar UI)
 */
function eliminarCarrito(carritoId, callback = null) {
    if (!carritoId || carritoId <= 0) {
        mostrarToast('ID de carrito inválido.', 'danger');
        return;
    }

    if (!confirm('¿Eliminar este producto del carrito?')) return;

    const base = window.location.pathname.includes('/usuario/') ? '../' : '';

    fetch(base + 'api/carrito.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            accion:     'eliminar',
            carrito_id: carritoId
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarToast('Producto eliminado del carrito.', 'warning');
            actualizarBadgeCarrito(data.cantidad_carrito);
            if (callback) callback(data);
            else location.reload(); // Recargar si no hay callback
        } else {
            mostrarToast(data.mensaje, 'danger');
        }
    })
    .catch(error => {
        console.error('Error eliminarCarrito:', error);
        mostrarToast('Error de conexión.', 'danger');
    });
}

/**
 * Función: actualizarCantidadCarrito(carritoId, cantidad, callback)
 * Actualiza la cantidad de un item en el carrito via AJAX
 *
 * @param {number}   carritoId ID del item en carrito
 * @param {number}   cantidad  Nueva cantidad
 * @param {Function} callback  Función a ejecutar con los datos de respuesta
 */
function actualizarCantidadCarrito(carritoId, cantidad, callback = null) {
    if (!carritoId || carritoId <= 0) return;
    if (!cantidad || cantidad <= 0) {
        mostrarToast('La cantidad debe ser mayor a 0.', 'danger');
        return;
    }

    const base = window.location.pathname.includes('/usuario/') ? '../' : '';

    fetch(base + 'api/carrito.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            accion:     'actualizar',
            carrito_id: carritoId,
            cantidad:   cantidad
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            actualizarBadgeCarrito(data.cantidad_carrito);
            if (callback) callback(data);
        } else {
            mostrarToast(data.mensaje, 'danger');
            // Revertir el input al valor anterior si hay error
            if (callback) callback(null);
        }
    })
    .catch(error => {
        console.error('Error actualizarCantidadCarrito:', error);
        mostrarToast('Error de conexión.', 'danger');
    });
}

/**
 * Función: confirmarPedido(callback)
 * Confirma el pedido actual del carrito via AJAX
 *
 * @param {Function} callback Función a ejecutar con el resultado
 */
function confirmarPedido(callback = null) {
    const base = window.location.pathname.includes('/usuario/') ? '../' : '';

    fetch(base + 'api/confirmar_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({}) // El usuario_id viene de la sesión PHP
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarToast('¡Pedido confirmado! #' + data.pedido_id, 'success');
            actualizarBadgeCarrito(0);
            if (callback) callback(data);
            else {
                // Redirigir a mis pedidos después de 1.5 segundos
                setTimeout(() => {
                    window.location.href = base + 'usuario/mis_pedidos.php';
                }, 1500);
            }
        } else {
            mostrarToast(data.mensaje, 'danger');
            if (callback) callback(null);
        }
    })
    .catch(error => {
        console.error('Error confirmarPedido:', error);
        mostrarToast('Error de conexión al confirmar el pedido.', 'danger');
    });
}

/**
 * Función: filtrarProductos(categoria, busqueda)
 * Filtra productos sin recargar la página (navegación con URL)
 *
 * @param {string} categoria ID de categoría o vacío para todas
 * @param {string} busqueda  Texto de búsqueda
 */
function filtrarProductos(categoria = '', busqueda = '') {
    const params = new URLSearchParams();
    if (categoria) params.set('categoria', categoria);
    if (busqueda)  params.set('buscar', busqueda);

    // Navegar a la URL con los filtros
    const base = window.location.pathname.includes('/usuario/') ? '' : 'usuario/';
    window.location.href = window.location.pathname + '?' + params.toString();
}

// Inicializar eventos cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    // Auto-cerrar alertas después de 5 segundos
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });
});
