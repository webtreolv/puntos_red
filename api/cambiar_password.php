<?php
/**
 * api/cambiar_password.php
 * Endpoint para cambiar la contraseña del usuario logueado
 * Método: POST | Content-Type: application/json
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once '../config/seguridad.php';

validar_sesion_usuario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta_json(['success' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$body  = file_get_contents('php://input');
$datos = json_decode($body, true);

if (!$datos) {
    respuesta_json(['success' => false, 'mensaje' => 'JSON inválido.'], 400);
}

$password_actual = $datos['password_actual'] ?? '';
$password_nueva = $datos['password_nueva'] ?? '';
$usuario_id = $_SESSION['usuario_id'];

// Validar que no estén vacíos
if (empty($password_actual) || empty($password_nueva)) {
    respuesta_json(['success' => false, 'mensaje' => 'Completa todos los campos.'], 400);
}

// Validar longitud mínima
if (strlen($password_nueva) < 6) {
    respuesta_json(['success' => false, 'mensaje' => 'La contraseña debe tener al menos 6 caracteres.'], 400);
}

// Obtener la contraseña actual del usuario desde la base de datos
$stmt = $pdo->prepare("SELECT contrasena_hash FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    respuesta_json(['success' => false, 'mensaje' => 'Usuario no encontrado.'], 404);
}

// Verificar que la contraseña actual es correcta
if (!verificar_contrasena($password_actual, $usuario['contrasena_hash'])) {
    respuesta_json(['success' => false, 'mensaje' => 'La contraseña actual es incorrecta.'], 400);
}

// Encriptar la nueva contraseña
$nuevo_hash = encriptar_contrasena($password_nueva);

// Actualizar en la base de datos
$stmt = $pdo->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE id = ?");
$stmt->execute([$nuevo_hash, $usuario_id]);

if ($stmt->rowCount() === 0) {
    respuesta_json(['success' => false, 'mensaje' => 'Error al actualizar la contraseña.'], 500);
}

respuesta_json([
    'success' => true,
    'mensaje' => 'Contraseña cambiada exitosamente.'
]);