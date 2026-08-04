<?php
/**
 * api/agregar_puntos.php
 * Endpoint JSON para agregar/restar puntos a un usuario
 * Método: POST | Content-Type: application/json
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once '../admin/includes/funciones_usuarios.php';

// Solo admins pueden usar este endpoint
validar_sesion_admin();

// Solo aceptar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta_json(['success' => false, 'mensaje' => 'Método no permitido.'], 405);
}

// Leer y decodificar el cuerpo JSON de la petición
$body = file_get_contents('php://input');
$datos = json_decode($body, true);

// Validar que el JSON sea válido
if (!$datos) {
    respuesta_json(['success' => false, 'mensaje' => 'Datos JSON inválidos.'], 400);
}

// Extraer y validar parámetros
$usuario_id  = isset($datos['usuario_id'])  ? (int)$datos['usuario_id']  : 0;
$cantidad    = isset($datos['cantidad'])    ? (int)$datos['cantidad']    : 0;
$motivo      = trim($datos['motivo']      ?? '');
$descripcion = trim($datos['descripcion'] ?? '');

// Validaciones
if ($usuario_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'ID de usuario inválido.'], 400);
}

if ($cantidad === 0) {
    respuesta_json(['success' => false, 'mensaje' => 'La cantidad no puede ser 0.'], 400);
}

// Motivos válidos
$motivos_validos = ['campaña', 'promoción', 'ajuste', 'devolucion', 'otro', 'bienvenida', 'compra'];
if (!in_array($motivo, $motivos_validos)) {
    $motivo = 'ajuste'; // Motivo por defecto si no es válido
}

// Obtener ID del admin de la sesión
$admin_id = $_SESSION['usuario_id'];

// Ejecutar la función de agregar puntos
$resultado = agregar_puntos($usuario_id, $cantidad, $motivo, $descripcion, $admin_id);

// Retornar respuesta JSON
respuesta_json($resultado);
