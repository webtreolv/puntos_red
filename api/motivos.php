<?php
/**
 * api/motivos.php
 * CRUD para motivos de asignación de puntos
 * Métodos: GET, POST
 */

require_once '../includes/sesion.php';
require_once '../config/conexion.php';

validar_sesion_admin();

$metodo = $_SERVER['REQUEST_METHOD'];

// GET: Listar motivos
if ($metodo === 'GET') {
    $stmt = $pdo->prepare("SELECT id, nombre, estado FROM motivos ORDER BY nombre");
    $stmt->execute();
    $motivos = $stmt->fetchAll();
    respuesta_json(['success' => true, 'motivos' => $motivos]);
}

// POST: Crear, editar, eliminar
if ($metodo === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $accion = $data['accion'] ?? '';
    
    if ($accion === 'crear') {
        $nombre = trim($data['nombre'] ?? '');
        if (empty($nombre)) {
            respuesta_json(['success' => false, 'mensaje' => 'Nombre requerido.'], 400);
        }
        
        $stmt = $pdo->prepare("SELECT id FROM motivos WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetch()) {
            respuesta_json(['success' => false, 'mensaje' => 'El motivo ya existe.'], 400);
        }
        
        $stmt = $pdo->prepare("INSERT INTO motivos (nombre) VALUES (?)");
        $stmt->execute([$nombre]);
        
        respuesta_json(['success' => true, 'mensaje' => 'Motivo creado.', 'id' => $pdo->lastInsertId()]);
    }
    
    if ($accion === 'editar') {
        $id = (int)($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        
        if ($id <= 0 || empty($nombre)) {
            respuesta_json(['success' => false, 'mensaje' => 'Datos inválidos.'], 400);
        }
        
        $stmt = $pdo->prepare("UPDATE motivos SET nombre = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);
        
        respuesta_json(['success' => true, 'mensaje' => 'Motivo actualizado.']);
    }
    
    if ($accion === 'eliminar') {
        $id = (int)($data['id'] ?? 0);
        
        if ($id <= 0) {
            respuesta_json(['success' => false, 'mensaje' => 'ID inválido.'], 400);
        }
        
        $stmt = $pdo->prepare("DELETE FROM motivos WHERE id = ?");
        $stmt->execute([$id]);
        
        respuesta_json(['success' => true, 'mensaje' => 'Motivo eliminado.']);
    }
    
    if ($accion === 'cambiar_estado') {
        $id = (int)($data['id'] ?? 0);
        $estado = $data['estado'] ?? 'inactivo';
        
        if ($id <= 0) {
            respuesta_json(['success' => false, 'mensaje' => 'ID inválido.'], 400);
        }
        
        $stmt = $pdo->prepare("UPDATE motivos SET estado = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);
        
        respuesta_json(['success' => true, 'mensaje' => 'Estado actualizado.']);
    }
    
    respuesta_json(['success' => false, 'mensaje' => 'Acción no reconocida.'], 400);
}