<?php
/**
 * admin/includes/funciones_usuarios.php
 * Funciones de base de datos para gestión de usuarios y puntos
 */

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/seguridad.php';

/**
 * Función: obtener_usuarios($rol)
 * Obtiene todos los usuarios, opcionalmente filtrados por rol
 *
 * @param string|null $rol 'admin', 'usuario' o null para todos
 * @return array Lista de usuarios
 */
function obtener_usuarios($rol = null) {
    global $pdo;
    try {
        if ($rol !== null && in_array($rol, ['admin', 'usuario'])) {
            $stmt = $pdo->prepare(
                "SELECT id, nombre, email, puntos_disponibles, rol, estado, fecha_creacion
                 FROM usuarios WHERE rol = ? ORDER BY nombre"
            );
            $stmt->execute([$rol]);
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, nombre, email, puntos_disponibles, rol, estado, fecha_creacion
                 FROM usuarios ORDER BY rol, nombre"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_usuarios: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_usuario($id)
 * Obtiene un usuario específico por ID
 *
 * @param int $id ID del usuario
 * @return array|false Datos del usuario o false
 */
function obtener_usuario($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT id, nombre, email, puntos_disponibles, rol, estado, fecha_creacion
             FROM usuarios WHERE id = ? LIMIT 1"
        );
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error obtener_usuario: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: agregar_puntos($usuario_id, $cantidad, $motivo, $descripcion, $admin_id)
 * Agrega o resta puntos a un usuario y registra en historial
 * Usa transacción para garantizar consistencia
 *
 * @param int    $usuario_id  ID del usuario
 * @param int    $cantidad    Puntos a agregar (positivo) o restar (negativo)
 * @param string $motivo      Motivo del movimiento
 * @param string $descripcion Descripción detallada
 * @param int    $admin_id    ID del admin que realiza la acción
 * @return array ['success' => bool, 'mensaje' => string, 'puntos_nuevos' => int]
 */
function agregar_puntos($usuario_id, $cantidad, $motivo, $descripcion, $admin_id) {
    global $pdo;
    try {
        // Iniciar transacción para garantizar que ambas operaciones se completen
        $pdo->beginTransaction();

        // Obtener puntos actuales del usuario (con bloqueo para evitar condiciones de carrera)
        $stmt = $pdo->prepare("SELECT puntos_disponibles FROM usuarios WHERE id = ? FOR UPDATE");
        $stmt->execute([(int)$usuario_id]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $pdo->rollBack();
            return ['success' => false, 'mensaje' => 'Usuario no encontrado.'];
        }

        $puntos_actuales = (int)$usuario['puntos_disponibles'];
        $puntos_nuevos   = $puntos_actuales + (int)$cantidad;

        // Validar que no queden puntos negativos
        if ($puntos_nuevos < 0) {
            $pdo->rollBack();
            return [
                'success' => false,
                'mensaje' => "El usuario solo tiene $puntos_actuales puntos. No se puede restar " . abs($cantidad) . "."
            ];
        }

        // Determinar tipo de movimiento según la cantidad
        $tipo = $cantidad >= 0 ? 'asignacion' : 'ajuste';
        if ($motivo === 'devolucion') $tipo = 'devolucion';

        // Actualizar puntos del usuario
        $stmt = $pdo->prepare("UPDATE usuarios SET puntos_disponibles = ? WHERE id = ?");
        $stmt->execute([$puntos_nuevos, (int)$usuario_id]);

        // Registrar en historial de puntos
        $stmt = $pdo->prepare(
            "INSERT INTO historial_puntos (usuario_id, admin_id, cantidad_puntos, tipo_movimiento, motivo, descripcion)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$usuario_id,
            (int)$admin_id,
            (int)$cantidad,
            $tipo,
            $motivo,
            $descripcion
        ]);

        $pdo->commit(); // Confirmar transacción

        return [
            'success'      => true,
            'mensaje'      => 'Puntos actualizados correctamente.',
            'puntos_nuevos' => $puntos_nuevos
        ];

    } catch (PDOException $e) {
        $pdo->rollBack(); // Revertir cambios si hay error
        error_log('Error agregar_puntos: ' . $e->getMessage());
        return ['success' => false, 'mensaje' => 'Error al procesar los puntos.'];
    }
}

/**
 * Función: cambiar_contrasena($usuario_id, $nueva_pass)
 * Cambia la contraseña de un usuario
 *
 * @param int    $usuario_id ID del usuario
 * @param string $nueva_pass Nueva contraseña en texto plano
 * @return bool true si se cambió correctamente
 */
function cambiar_contrasena($usuario_id, $nueva_pass) {
    global $pdo;
    try {
        // Encriptar la nueva contraseña con bcrypt
        $hash = encriptar_contrasena($nueva_pass);
        $stmt = $pdo->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE id = ?");
        return $stmt->execute([$hash, (int)$usuario_id]);
    } catch (PDOException $e) {
        error_log('Error cambiar_contrasena: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: obtener_historial_puntos($usuario_id, $dias)
 * Obtiene el historial de puntos de un usuario
 *
 * @param int $usuario_id ID del usuario
 * @param int $dias       Número de días hacia atrás (default 30)
 * @return array Historial de movimientos
 */
function obtener_historial_puntos($usuario_id, $dias = 30) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT h.*, a.nombre AS admin_nombre
             FROM historial_puntos h
             LEFT JOIN usuarios a ON h.admin_id = a.id
             WHERE h.usuario_id = ?
             AND h.fecha_movimiento >= DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY h.fecha_movimiento DESC"
        );
        $stmt->execute([(int)$usuario_id, (int)$dias]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_historial_puntos: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: cambiar_estado_usuario($usuario_id, $nuevo_estado)
 * Activa o desactiva un usuario
 *
 * @param int    $usuario_id   ID del usuario
 * @param string $nuevo_estado 'activo' o 'inactivo'
 * @return bool true si se cambió correctamente
 */
function cambiar_estado_usuario($usuario_id, $nuevo_estado) {
    global $pdo;
    try {
        if (!in_array($nuevo_estado, ['activo', 'inactivo'])) {
            return false; // Estado no válido
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET estado = ? WHERE id = ? AND rol = 'usuario'");
        return $stmt->execute([$nuevo_estado, (int)$usuario_id]);
    } catch (PDOException $e) {
        error_log('Error cambiar_estado_usuario: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: crear_usuario($nombre, $email, $pass, $puntos, $rol)
 * Crea un nuevo usuario en el sistema
 *
 * @param string $nombre Nombre completo
 * @param string $email  Correo electrónico
 * @param string $pass   Contraseña en texto plano
 * @param int    $puntos Puntos iniciales
 * @param string $rol    'admin' o 'usuario'
 * @return bool true si se creó correctamente
 */
function crear_usuario($nombre, $email, $pass, $puntos = 0, $rol = 'usuario') {
    global $pdo;
    try {
        $hash = encriptar_contrasena($pass);
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, email, contrasena_hash, puntos_disponibles, rol)
             VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            trim($nombre),
            strtolower(trim($email)),
            $hash,
            (int)$puntos,
            $rol
        ]);
    } catch (PDOException $e) {
        error_log('Error crear_usuario: ' . $e->getMessage());
        return false;
    }
}
