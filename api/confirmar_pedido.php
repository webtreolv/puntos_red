<?php
/**
 * api/confirmar_pedido.php
 * Endpoint para confirmar el pedido del carrito
 * Usa transacción para garantizar consistencia de datos
 * Método: POST
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_usuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta_json(['success' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$usuario_id = $_SESSION['usuario_id'];

try {
    // ============================================================
    // PASO 1: Obtener items del carrito del usuario
    // ============================================================
    $stmt = $pdo->prepare(
        "SELECT c.id AS carrito_id, c.cantidad, p.id AS producto_id,
                p.nombre, p.precio_puntos, p.stock
         FROM carrito c
         INNER JOIN productos p ON c.producto_id = p.id
         WHERE c.usuario_id = ?"
    );
    $stmt->execute([$usuario_id]);
    $items_carrito = $stmt->fetchAll();

    if (empty($items_carrito)) {
        respuesta_json(['success' => false, 'mensaje' => 'Tu carrito está vacío.'], 400);
    }

    // ============================================================
    // PASO 2: Calcular total de puntos necesarios
    // ============================================================
    $total_puntos = 0;
    foreach ($items_carrito as $item) {
        $total_puntos += $item['cantidad'] * $item['precio_puntos'];
    }

    // ============================================================
    // PASO 3: Verificar que el usuario tiene puntos suficientes
    // ============================================================
    $stmt = $pdo->prepare("SELECT puntos_disponibles FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch();

    if (!$usuario || $usuario['puntos_disponibles'] < $total_puntos) {
        respuesta_json([
            'success' => false,
            'mensaje' => "Puntos insuficientes. Necesitas $total_puntos pts, tienes {$usuario['puntos_disponibles']} pts."
        ], 400);
    }

    // ============================================================
    // PASO 4: Verificar stock de todos los productos
    // ============================================================
    foreach ($items_carrito as $item) {
        if ($item['stock'] < $item['cantidad']) {
            respuesta_json([
                'success' => false,
                'mensaje' => "Stock insuficiente para '{$item['nombre']}'. Solo hay {$item['stock']} unidades."
            ], 400);
        }
    }

    // ============================================================
    // PASO 5: Iniciar transacción (todo o nada)
    // ============================================================
    $pdo->beginTransaction();

    // Crear el pedido
    $stmt = $pdo->prepare(
        "INSERT INTO pedidos (usuario_id, estado, total_puntos_usados)
         VALUES (?, 'Solicitud', ?)"
    );
    $stmt->execute([$usuario_id, $total_puntos]);
    $pedido_id = (int)$pdo->lastInsertId();

    // Insertar detalle del pedido y actualizar stock
    foreach ($items_carrito as $item) {
        // Insertar en detalle_pedidos
        $stmt = $pdo->prepare(
            "INSERT INTO detalle_pedidos (pedido_id, producto_id, cantidad, puntos_unitarios)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $pedido_id,
            $item['producto_id'],
            $item['cantidad'],
            $item['precio_puntos']
        ]);

        // Reducir stock del producto
        $stmt = $pdo->prepare(
            "UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?"
        );
        $stmt->execute([$item['cantidad'], $item['producto_id'], $item['cantidad']]);

        if ($stmt->rowCount() === 0) {
            // Stock insuficiente al momento de confirmar (condición de carrera)
            $pdo->rollBack();
            respuesta_json([
                'success' => false,
                'mensaje' => "Error de stock para '{$item['nombre']}'. Intenta de nuevo."
            ], 400);
        }
    }

    // Descontar puntos del usuario
    $puntos_restantes = $usuario['puntos_disponibles'] - $total_puntos;
    $stmt = $pdo->prepare("UPDATE usuarios SET puntos_disponibles = ? WHERE id = ?");
    $stmt->execute([$puntos_restantes, $usuario_id]);

    // Registrar en historial de puntos (movimiento de gasto)
    $stmt = $pdo->prepare(
        "INSERT INTO historial_puntos (usuario_id, admin_id, cantidad_puntos, tipo_movimiento, motivo, descripcion)
         VALUES (?, NULL, ?, 'gasto', 'compra', ?)"
    );
    $stmt->execute([
        $usuario_id,
        -$total_puntos, // Negativo porque es un gasto
        "Pedido #$pedido_id confirmado"
    ]);

    // Vaciar el carrito del usuario
    $stmt = $pdo->prepare("DELETE FROM carrito WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);

    // Actualizar puntos en sesión
    $_SESSION['puntos'] = $puntos_restantes;

    $pdo->commit(); // Confirmar todos los cambios

    respuesta_json([
        'success'         => true,
        'mensaje'         => 'Pedido confirmado exitosamente.',
        'pedido_id'       => $pedido_id,
        'puntos_restantes' => $puntos_restantes
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Revertir si hay error
    }
    error_log('Error confirmar_pedido: ' . $e->getMessage());
    respuesta_json(['success' => false, 'mensaje' => 'Error al procesar el pedido. Intenta de nuevo.'], 500);
}
