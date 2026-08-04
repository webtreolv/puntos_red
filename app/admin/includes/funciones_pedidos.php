<?php
/**
 * admin/includes/funciones_pedidos.php
 * Funciones de base de datos para gestión de pedidos
 */

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/seguridad.php';

// Estados válidos del sistema
define('ESTADOS_PEDIDO', ['Solicitud', 'En camino', 'Listo para recoger']);

// Transiciones de estado permitidas
define('TRANSICIONES_ESTADO', [
    'Solicitud'  => ['En camino'],
    'En camino'  => ['Listo para recoger'],
    'Listo para recoger' => [] // Estado final, no puede avanzar
]);

/**
 * Función: obtener_pedidos($estado)
 * Obtiene todos los pedidos, opcionalmente filtrados por estado
 *
 * @param string|null $estado Estado para filtrar o null para todos
 * @return array Lista de pedidos con datos del usuario
 */
function obtener_pedidos($estado = null) {
    global $pdo;
    try {
        if ($estado !== null && in_array($estado, ESTADOS_PEDIDO)) {
            $stmt = $pdo->prepare(
                "SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                        COUNT(dp.id) AS cantidad_productos
                 FROM pedidos p
                 INNER JOIN usuarios u ON p.usuario_id = u.id
                 LEFT JOIN detalle_pedidos dp ON p.id = dp.pedido_id
                 WHERE p.estado = ?
                 GROUP BY p.id
                 ORDER BY p.fecha_pedido DESC"
            );
            $stmt->execute([$estado]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email,
                        COUNT(dp.id) AS cantidad_productos
                 FROM pedidos p
                 INNER JOIN usuarios u ON p.usuario_id = u.id
                 LEFT JOIN detalle_pedidos dp ON p.id = dp.pedido_id
                 GROUP BY p.id
                 ORDER BY p.fecha_pedido DESC"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_pedidos: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_pedido($id)
 * Obtiene un pedido específico con datos del usuario
 *
 * @param int $id ID del pedido
 * @return array|false Datos del pedido o false
 */
function obtener_pedido($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email
             FROM pedidos p
             INNER JOIN usuarios u ON p.usuario_id = u.id
             WHERE p.id = ?
             LIMIT 1"
        );
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error obtener_pedido: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: obtener_detalle_pedido($pedido_id)
 * Obtiene los productos de un pedido específico
 *
 * @param int $pedido_id ID del pedido
 * @return array Lista de productos del pedido
 */
function obtener_detalle_pedido($pedido_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT dp.*, pr.nombre AS producto_nombre, pr.imagen_url
             FROM detalle_pedidos dp
             INNER JOIN productos pr ON dp.producto_id = pr.id
             WHERE dp.pedido_id = ?
             ORDER BY pr.nombre"
        );
        $stmt->execute([(int)$pedido_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_detalle_pedido: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: cambiar_estado_pedido($pedido_id, $nuevo_estado)
 * Cambia el estado de un pedido validando la transición
 *
 * @param int    $pedido_id   ID del pedido
 * @param string $nuevo_estado Nuevo estado
 * @return array ['success' => bool, 'mensaje' => string]
 */
function cambiar_estado_pedido($pedido_id, $nuevo_estado) {
    global $pdo;
    try {
        // Obtener estado actual del pedido
        $pedido = obtener_pedido($pedido_id);
        if (!$pedido) {
            return ['success' => false, 'mensaje' => 'Pedido no encontrado.'];
        }

        // Validar que la transición sea permitida
        if (!validar_transicion_estado($pedido['estado'], $nuevo_estado)) {
            return [
                'success' => false,
                'mensaje' => "No se puede cambiar de '{$pedido['estado']}' a '$nuevo_estado'."
            ];
        }

        // Actualizar estado en BD
        $stmt = $pdo->prepare("UPDATE pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, (int)$pedido_id]);

        return ['success' => true, 'mensaje' => 'Estado actualizado correctamente.'];

    } catch (PDOException $e) {
        error_log('Error cambiar_estado_pedido: ' . $e->getMessage());
        return ['success' => false, 'mensaje' => 'Error al actualizar el estado.'];
    }
}

/**
 * Función: validar_transicion_estado($estado_actual, $nuevo_estado)
 * Verifica si la transición de estado es válida
 *
 * @param string $estado_actual Estado actual del pedido
 * @param string $nuevo_estado  Estado al que se quiere cambiar
 * @return bool true si la transición es válida
 */
function validar_transicion_estado($estado_actual, $nuevo_estado) {
    $transiciones = TRANSICIONES_ESTADO;
    // Verificar que el estado actual existe y el nuevo estado está en sus transiciones permitidas
    return isset($transiciones[$estado_actual]) &&
           in_array($nuevo_estado, $transiciones[$estado_actual]);
}

/**
 * Función: obtener_reporte_puntos_otorgados($fecha_inicio, $fecha_fin)
 * Obtiene reporte de puntos otorgados en un período
 *
 * @param string $fecha_inicio Fecha inicio (Y-m-d)
 * @param string $fecha_fin    Fecha fin (Y-m-d)
 * @return array Datos del reporte
 */
function obtener_reporte_puntos_otorgados($fecha_inicio, $fecha_fin) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT h.*, u.nombre AS usuario_nombre, a.nombre AS admin_nombre
             FROM historial_puntos h
             INNER JOIN usuarios u ON h.usuario_id = u.id
             LEFT JOIN usuarios a ON h.admin_id = a.id
             WHERE h.tipo_movimiento = 'asignacion'
             AND DATE(h.fecha_movimiento) BETWEEN ? AND ?
             ORDER BY h.fecha_movimiento DESC"
        );
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error reporte_puntos_otorgados: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_reporte_puntos_gastados($fecha_inicio, $fecha_fin)
 * Obtiene reporte de puntos gastados en un período
 *
 * @param string $fecha_inicio Fecha inicio (Y-m-d)
 * @param string $fecha_fin    Fecha fin (Y-m-d)
 * @return array Datos del reporte
 */
function obtener_reporte_puntos_gastados($fecha_inicio, $fecha_fin) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT h.*, u.nombre AS usuario_nombre
             FROM historial_puntos h
             INNER JOIN usuarios u ON h.usuario_id = u.id
             WHERE h.tipo_movimiento = 'gasto'
             AND DATE(h.fecha_movimiento) BETWEEN ? AND ?
             ORDER BY h.fecha_movimiento DESC"
        );
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error reporte_puntos_gastados: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_top_usuarios_puntos_gastados($limite)
 * Obtiene el ranking de usuarios que más puntos han gastado
 *
 * @param int $limite Número de usuarios a retornar
 * @return array Ranking de usuarios
 */
function obtener_top_usuarios_puntos_gastados($limite = 10) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT u.nombre, u.email,
                    ABS(SUM(h.cantidad_puntos)) AS total_gastado
             FROM historial_puntos h
             INNER JOIN usuarios u ON h.usuario_id = u.id
             WHERE h.tipo_movimiento = 'gasto'
             GROUP BY h.usuario_id, u.nombre, u.email
             ORDER BY total_gastado DESC
             LIMIT ?"
        );
        $stmt->execute([(int)$limite]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error top_usuarios: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_puntos_otorgados_por_motivo($fecha_inicio, $fecha_fin)
 * Obtiene cuántos puntos se otorgaron por cada motivo
 *
 * @param string $fecha_inicio Fecha inicio (Y-m-d)
 * @param string $fecha_fin    Fecha fin (Y-m-d)
 * @return array Datos agrupados por motivo
 */
function obtener_puntos_otorgados_por_motivo($fecha_inicio, $fecha_fin) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(h.motivo, 'Sin motivo') AS motivo,
                    SUM(ABS(h.cantidad_puntos)) AS total_puntos,
                    COUNT(*) AS cantidad_movimientos
             FROM historial_puntos h
             WHERE h.tipo_movimiento = 'asignacion'
             AND DATE(h.fecha_movimiento) BETWEEN ? AND ?
             GROUP BY h.motivo
             ORDER BY total_puntos DESC"
        );
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_puntos_otorgados_por_motivo: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_dinero_gastado_por_puntos_otorgados($fecha_inicio, $fecha_fin)
 * Obtiene cuánto dinero (en pesos) se gastó por puntos otorgados, agrupado por motivo
 *
 * @param string $fecha_inicio Fecha inicio (Y-m-d)
 * @param string $fecha_fin    Fecha fin (Y-m-d)
 * @return array Datos de dinero gastado por motivo
 */
function obtener_dinero_gastado_por_puntos_otorgados($fecha_inicio, $fecha_fin) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(h.motivo, 'Sin motivo') AS motivo,
                    SUM(ABS(h.cantidad_puntos)) AS total_puntos,
                    SUM(COALESCE(h.monto_pesos, 0)) AS total_dinero,
                    COUNT(*) AS cantidad_movimientos
             FROM historial_puntos h
             WHERE h.tipo_movimiento = 'gasto'
             AND DATE(h.fecha_movimiento) BETWEEN ? AND ?
             GROUP BY h.motivo
             ORDER BY total_dinero DESC"
        );
        $stmt->execute([$fecha_inicio, $fecha_fin]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_dinero_gastado_por_puntos_otorgados: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_estadisticas_generales($fecha_inicio, $fecha_fin)
 * Obtiene estadísticas generales del sistema
 *
 * @param string $fecha_inicio Fecha inicio (Y-m-d)
 * @param string $fecha_fin    Fecha fin (Y-m-d)
 * @return array Estadísticas
 */
function obtener_estadisticas_generales($fecha_inicio, $fecha_fin) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT 
                (SELECT COALESCE(SUM(ABS(cantidad_puntos)), 0) FROM historial_puntos WHERE tipo_movimiento = 'asignacion' AND DATE(fecha_movimiento) BETWEEN ? AND ?) AS total_otorgados,
                (SELECT COALESCE(SUM(ABS(cantidad_puntos)), 0) FROM historial_puntos WHERE tipo_movimiento = 'gasto' AND DATE(fecha_movimiento) BETWEEN ? AND ?) AS total_gastados,
                (SELECT COALESCE(SUM(monto_pesos), 0) FROM historial_puntos WHERE tipo_movimiento = 'gasto' AND DATE(fecha_movimiento) BETWEEN ? AND ?) AS total_dinero_gastado,
                (SELECT COUNT(DISTINCT usuario_id) FROM historial_puntos WHERE DATE(fecha_movimiento) BETWEEN ? AND ?) AS usuarios_activos,
                (SELECT COUNT(*) FROM pedidos WHERE estado = 'Solicitud' AND DATE(fecha_pedido) BETWEEN ? AND ?) AS pedidos_solicitud,
                (SELECT COUNT(*) FROM pedidos WHERE estado = 'En camino' AND DATE(fecha_pedido) BETWEEN ? AND ?) AS pedidos_en_camino,
                (SELECT COUNT(*) FROM pedidos WHERE estado = 'Listo para recoger' AND DATE(fecha_pedido) BETWEEN ? AND ?) AS pedidos_listos"
        );
        $stmt->execute([$fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin, $fecha_inicio, $fecha_fin]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error obtener_estadisticas_generales: ' . $e->getMessage());
        return ['total_otorgados' => 0, 'total_gastados' => 0, 'total_dinero_gastado' => 0, 'usuarios_activos' => 0, 'pedidos_solicitud' => 0, 'pedidos_en_camino' => 0, 'pedidos_listos' => 0];
    }
}
