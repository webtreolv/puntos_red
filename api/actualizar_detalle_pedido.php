<?php
/**
 * api/actualizar_detalle_pedido.php
 * Endpoint para actualizar un detalle de pedido (admin)
 * Método: POST
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

// Solo admin puede modificar detalles de pedidos
validar_sesion_admin();

$input = json_decode(file_get_contents('php://input'), true);

$detalle_id = (int)($input['detalle_id'] ?? 0);
$nuevo_producto_id = (int)($input['producto_id'] ?? 0);
$nueva_cantidad = (int)($input['cantidad'] ?? 0);

if ($detalle_id <= 0 || $nueva_cantidad <= 0 || $nuevo_producto_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'Datos inválidos.'], 400);
}

// Obtener el detalle actual
$stmt = $pdo->prepare("SELECT * FROM detalle_pedidos WHERE id = ?");
$stmt->execute([$detalle_id]);
$detalle_actual = $stmt->fetch();

if (!$detalle_actual) {
    respuesta_json(['success' => false, 'mensaje' => 'Detalle no encontrado.'], 404);
}

// Obtener el pedido para verificar estado
$stmt = $pdo->prepare("SELECT estado FROM pedidos WHERE id = ?");
$stmt->execute([$detalle_actual['pedido_id']]);
$pedido = $stmt->fetch();

if (!$pedido || !in_array($pedido['estado'], ['Solicitud'])) {
    respuesta_json(['success' => false, 'mensaje' => 'Solo se puede editar en estado "Solicitud".'], 400);
}

// Obtener el nuevo producto
$stmt = $pdo->prepare("SELECT precio_puntos, stock FROM productos WHERE id = ?");
$stmt->execute([$nuevo_producto_id]);
$producto = $stmt->fetch();

if (!$producto || $producto['stock'] < $nueva_cantidad) {
    respuesta_json(['success' => false, 'mensaje' => 'Stock insuficiente del producto seleccionado.'], 400);
}

// Actualizar detalle
$stmt = $pdo->prepare(
    "UPDATE detalle_pedidos SET producto_id = ?, cantidad = ?, puntos_unitarios = ? WHERE id = ?"
);
$stmt->execute([
    $nuevo_producto_id,
    $nueva_cantidad,
    $producto['precio_puntos'],
    $detalle_id
]);

// Recalcular total del pedido
$stmt = $pdo->prepare(
    "SELECT SUM(cantidad * puntos_unitarios) as total FROM detalle_pedidos WHERE pedido_id = ?"
);
$stmt->execute([$detalle_actual['pedido_id']]);
$total = $stmt->fetch();

$stmt = $pdo->prepare("UPDATE pedidos SET total_puntos_usados = ? WHERE id = ?");
$stmt->execute([$total['total'], $detalle_actual['pedido_id']]);

respuesta_json(['success' => true, 'mensaje' => 'Detalle actualizado correctamente.']);