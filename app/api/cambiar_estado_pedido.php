<?php
/**
 * api/cambiar_estado_pedido.php
 * Endpoint JSON para cambiar el estado de un pedido
 * Método: POST | Content-Type: application/json
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once '../admin/includes/funciones_pedidos.php';

validar_sesion_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta_json(['success' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);

if (!$datos) {
    respuesta_json(['success' => false, 'mensaje' => 'JSON inválido.'], 400);
}

$pedido_id    = isset($datos['pedido_id'])    ? (int)$datos['pedido_id']    : 0;
$nuevo_estado = trim($datos['nuevo_estado'] ?? '');

if ($pedido_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'ID de pedido inválido.'], 400);
}

if (!in_array($nuevo_estado, ESTADOS_PEDIDO)) {
    respuesta_json(['success' => false, 'mensaje' => 'Estado no válido.'], 400);
}

$resultado = cambiar_estado_pedido($pedido_id, $nuevo_estado);
respuesta_json($resultado);
