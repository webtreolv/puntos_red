<?php
/**
 * api/detalle_pedido.php
 * Endpoint para obtener detalle de un pedido
 * Método: GET
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;

if ($pedido_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'ID inválido.'], 400);
}

// Verificar que el usuario tiene acceso al pedido
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'] ?? 'usuario';

// Si es admin puede ver cualquier pedido, si es usuario solo el suyo
if ($rol === 'admin') {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email
         FROM pedidos p
         INNER JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.id = ?"
    );
    $stmt->execute([$pedido_id]);
} else {
    $stmt = $pdo->prepare(
        "SELECT p.*, u.nombre AS usuario_nombre, u.email AS usuario_email
         FROM pedidos p
         INNER JOIN usuarios u ON p.usuario_id = u.id
         WHERE p.id = ? AND p.usuario_id = ?"
    );
    $stmt->execute([$pedido_id, $usuario_id]);
}

$pedido = $stmt->fetch();

if (!$pedido) {
    respuesta_json(['success' => false, 'mensaje' => 'Pedido no encontrado.'], 404);
}

// Obtener detalle del pedido
$stmt = $pdo->prepare(
    "SELECT dp.id, dp.producto_id, p.nombre AS producto_nombre, dp.cantidad, dp.puntos_unitarios, p.precio_pesos
     FROM detalle_pedidos dp
     INNER JOIN productos p ON dp.producto_id = p.id
     WHERE dp.pedido_id = ?"
);
$stmt->execute([$pedido_id]);
$detalle = $stmt->fetchAll();

// Obtener lista de productos para edición (solo admin)
$productos = [];
if ($rol === 'admin') {
    $stmt = $pdo->prepare(
        "SELECT id, nombre, precio_puntos, precio_pesos FROM productos WHERE stock > 0 ORDER BY nombre"
    );
    $stmt->execute();
    $productos = $stmt->fetchAll();
}

// Formatear fecha
$pedido['fecha_pedido'] = date('Y-m-d H:i:s', strtotime($pedido['fecha_pedido']));

respuesta_json([
    'success' => true,
    'pedido'   => $pedido,
    'detalle'  => $detalle,
    'productos' => $productos
]);