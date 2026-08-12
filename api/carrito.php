<?php
/**
 * api/carrito.php
 * Endpoint JSON para gestión del carrito de compras
 * Acciones: agregar, eliminar, actualizar
 * Método: POST | Content-Type: application/json
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

// Solo usuarios normales pueden usar el carrito
validar_sesion_usuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta_json(['success' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);

if (!$datos) {
    respuesta_json(['success' => false, 'mensaje' => 'JSON inválido.'], 400);
}

$accion      = trim($datos['accion']      ?? '');
$producto_id = isset($datos['producto_id']) ? (int)$datos['producto_id'] : 0;
$cantidad    = isset($datos['cantidad'])    ? (int)$datos['cantidad']    : 1;
$carrito_id  = isset($datos['carrito_id']) ? (int)$datos['carrito_id']  : 0;
$usuario_id  = $_SESSION['usuario_id'];

/**
 * Función auxiliar: obtener_total_carrito($usuario_id)
 * Calcula el total de puntos del carrito y cantidad de items
 */
function obtener_total_carrito($usuario_id, $pdo) {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(c.cantidad * p.precio_puntos), 0) AS total_puntos,
                COALESCE(SUM(c.cantidad), 0) AS cantidad_items
         FROM carrito c
         INNER JOIN productos p ON c.producto_id = p.id
         WHERE c.usuario_id = ?"
    );
    $stmt->execute([$usuario_id]);
    return $stmt->fetch();
}

// ============================================================
// ACCIÓN: AGREGAR AL CARRITO
// ============================================================
if ($accion === 'agregar') {
    if ($producto_id <= 0 || $cantidad <= 0) {
        respuesta_json(['success' => false, 'mensaje' => 'Datos inválidos.'], 400);
    }

    // Verificar que el producto existe y tiene stock suficiente
    $stmt = $pdo->prepare("SELECT id, nombre, precio_puntos, stock FROM productos WHERE id = ? LIMIT 1");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch();

    if (!$producto) {
        respuesta_json(['success' => false, 'mensaje' => 'Producto no encontrado.'], 404);
    }

    if ($producto['stock'] <= 0) {
        respuesta_json(['success' => false, 'mensaje' => 'Producto agotado.'], 400);
    }

    // Verificar si ya está en el carrito
    $stmt = $pdo->prepare("SELECT id, cantidad FROM carrito WHERE usuario_id = ? AND producto_id = ?");
    $stmt->execute([$usuario_id, $producto_id]);
    $item_existente = $stmt->fetch();

    if ($item_existente) {
        // Actualizar cantidad si ya existe
        $nueva_cantidad = $item_existente['cantidad'] + $cantidad;
        if ($nueva_cantidad > $producto['stock']) {
            $nueva_cantidad = $producto['stock']; // No superar el stock
        }
        $stmt = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ?");
        $stmt->execute([$nueva_cantidad, $item_existente['id']]);
    } else {
        // Insertar nuevo item en carrito
        $cantidad_real = min($cantidad, $producto['stock']); // No superar stock
        $stmt = $pdo->prepare(
            "INSERT INTO carrito (usuario_id, producto_id, cantidad) VALUES (?, ?, ?)"
        );
        $stmt->execute([$usuario_id, $producto_id, $cantidad_real]);
    }

    $totales = obtener_total_carrito($usuario_id, $pdo);
    respuesta_json([
        'success'        => true,
        'mensaje'        => 'Producto agregado al carrito.',
        'cantidad_carrito' => (int)$totales['cantidad_items'],
        'total_puntos'   => (int)$totales['total_puntos']
    ]);
}

// ============================================================
// ACCIÓN: ELIMINAR DEL CARRITO
// ============================================================
elseif ($accion === 'eliminar') {
    if ($carrito_id <= 0) {
        respuesta_json(['success' => false, 'mensaje' => 'ID de carrito inválido.'], 400);
    }

    // Verificar que el item pertenece al usuario (seguridad)
    $stmt = $pdo->prepare("DELETE FROM carrito WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$carrito_id, $usuario_id]);

    if ($stmt->rowCount() === 0) {
        respuesta_json(['success' => false, 'mensaje' => 'Item no encontrado.'], 404);
    }

    $totales = obtener_total_carrito($usuario_id, $pdo);
    respuesta_json([
        'success'        => true,
        'mensaje'        => 'Producto eliminado del carrito.',
        'cantidad_carrito' => (int)$totales['cantidad_items'],
        'total_puntos'   => (int)$totales['total_puntos']
    ]);
}

// ============================================================
// ACCIÓN: ACTUALIZAR CANTIDAD
// ============================================================
elseif ($accion === 'actualizar') {
    if ($carrito_id <= 0 || $cantidad <= 0) {
        respuesta_json(['success' => false, 'mensaje' => 'Datos inválidos.'], 400);
    }

    // Obtener el item del carrito con datos del producto
    $stmt = $pdo->prepare(
        "SELECT c.id, c.producto_id, p.stock, p.precio_puntos
         FROM carrito c
         INNER JOIN productos p ON c.producto_id = p.id
         WHERE c.id = ? AND c.usuario_id = ?"
    );
    $stmt->execute([$carrito_id, $usuario_id]);
    $item = $stmt->fetch();

    if (!$item) {
        respuesta_json(['success' => false, 'mensaje' => 'Item no encontrado.'], 404);
    }

    // Validar que la cantidad no supere el stock
    if ($cantidad > $item['stock']) {
        respuesta_json([
            'success' => false,
            'mensaje' => "Solo hay {$item['stock']} unidades disponibles."
        ], 400);
    }

    // Actualizar cantidad
    $stmt = $pdo->prepare("UPDATE carrito SET cantidad = ? WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$cantidad, $carrito_id, $usuario_id]);

    $totales = obtener_total_carrito($usuario_id, $pdo);
    $subtotal = $cantidad * $item['precio_puntos'];

    respuesta_json([
        'success'        => true,
        'mensaje'        => 'Cantidad actualizada.',
        'subtotal'       => $subtotal,
        'cantidad_carrito' => (int)$totales['cantidad_items'],
        'total_puntos'   => (int)$totales['total_puntos']
    ]);
}

else {
    respuesta_json(['success' => false, 'mensaje' => 'Acción no válida.'], 400);
}
