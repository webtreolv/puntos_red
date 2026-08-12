<?php
/**
 * api/obtener_usuario_id.php
 * Obtener usuario por ID desde la base de datos
 * Método: GET
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

$usuario_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($usuario_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'ID inválido.'], 400);
}

// Buscar usuario por ID
$stmt = $pdo->prepare(
    "SELECT id, nombre, email, puntos_disponibles, estado 
     FROM usuarios 
     WHERE id = ? AND rol = 'usuario'
     LIMIT 1"
);
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    respuesta_json(['success' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
}

respuesta_json(['success' => true, 'usuario' => $usuario]);