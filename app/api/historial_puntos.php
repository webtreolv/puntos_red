<?php
/**
 * api/historial_puntos.php
 * Endpoint para obtener historial de puntos de un usuario
 * Método: GET
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';
require_once '../admin/includes/funciones_usuarios.php';

validar_sesion_admin();

$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$dias       = isset($_GET['dias'])       ? (int)$_GET['dias']       : 30;

if ($usuario_id <= 0) {
    respuesta_json(['success' => false, 'mensaje' => 'ID inválido.'], 400);
}

$historial = obtener_historial_puntos($usuario_id, $dias);

respuesta_json([
    'success'   => true,
    'historial' => $historial
]);
