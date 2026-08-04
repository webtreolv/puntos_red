<?php
/**
 * api/buscar_usuarios.php
 * Buscar usuarios en la base de datos
 * Método: GET
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

$termino = isset($_GET['q']) ? trim($_GET['q']) : '';
$excluidos = isset($_GET['excluir']) ? explode(',', $_GET['excluir']) : [];

if (strlen($termino) < 1) {
    respuesta_json(['success' => false, 'mensaje' => 'Término de búsqueda vacío.'], 400);
}

// Buscar usuarios que coincidan con el término
$sql = "SELECT id, nombre, email, puntos_disponibles, estado 
        FROM usuarios 
        WHERE rol = 'usuario' 
        AND (nombre LIKE ? OR email LIKE ? OR CAST(id AS CHAR) LIKE ?)
        ORDER BY nombre ASC
        LIMIT 20";

$busqueda = "%$termino%";
$stmt = $pdo->prepare($sql);
$stmt->execute([$busqueda, $busqueda, $busqueda]);
$usuarios = $stmt->fetchAll();

// Filtrar usuarios ya seleccionados
$usuarios = array_filter($usuarios, function($u) use ($excluidos) {
    return !in_array($u['id'], $excluidos);
});

respuesta_json(['success' => true, 'usuarios' => array_values($usuarios)]);