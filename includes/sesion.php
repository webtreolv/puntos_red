<?php
/**
 * includes/sesion.php
 * Gestión y validación de sesiones de usuario
 * Incluir este archivo en TODAS las páginas protegidas
 */

// Incluir configuración de seguridad (también inicia la sesión)
require_once __DIR__ . '/../config/seguridad.php';

// Tiempo máximo de inactividad en segundos (30 minutos)
define('SESSION_TIMEOUT', 1800);

/**
 * Función: validar_sesion()
 * Verifica que el usuario tenga una sesión válida activa
 * Si no tiene sesión, redirige al login
 */
function validar_sesion() {
    // Verificar que existan los datos básicos de sesión
    if (
        !isset($_SESSION['usuario_id']) ||
        !isset($_SESSION['rol']) ||
        !isset($_SESSION['nombre'])
    ) {
        // Limpiar cualquier dato de sesión corrupto
        session_unset();
        session_destroy();
        // Redirigir al login con mensaje de error
        header('Location: ' . obtener_ruta_login() . '?error=sesion_invalida');
        exit();
    }

    // Verificar que el ID de usuario sea un número válido
    if (!is_numeric($_SESSION['usuario_id']) || $_SESSION['usuario_id'] <= 0) {
        session_unset();
        session_destroy();
        header('Location: ' . obtener_ruta_login() . '?error=sesion_invalida');
        exit();
    }

    // Verificar que el rol sea válido
    if (!in_array($_SESSION['rol'], ['admin', 'usuario'])) {
        session_unset();
        session_destroy();
        header('Location: ' . obtener_ruta_login() . '?error=rol_invalido');
        exit();
    }

    // Verificar timeout de sesión por inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        $tiempo_inactivo = time() - $_SESSION['ultima_actividad'];
        if ($tiempo_inactivo > SESSION_TIMEOUT) {
            // Sesión expirada por inactividad
            session_unset();
            session_destroy();
            header('Location: ' . obtener_ruta_login() . '?error=sesion_expirada');
            exit();
        }
    }

    // Actualizar tiempo de última actividad
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Función: validar_sesion_admin()
 * Verifica que el usuario sea administrador
 * Redirige si no tiene permisos de admin
 */
function validar_sesion_admin() {
    validar_sesion(); // Primero validar que tenga sesión

    if ($_SESSION['rol'] !== 'admin') {
        // No es admin, redirigir a su área correspondiente
        header('Location: ' . obtener_ruta_base() . 'usuario/index.php?error=sin_permisos');
        exit();
    }
}

/**
 * Función: validar_sesion_usuario()
 * Verifica que el usuario tenga rol de usuario normal
 */
function validar_sesion_usuario() {
    validar_sesion(); // Primero validar que tenga sesión

    if ($_SESSION['rol'] !== 'usuario') {
        // Es admin, redirigir a su área
        header('Location: ' . obtener_ruta_base() . 'admin/index.php');
        exit();
    }
}

/**
 * Función: verificar_rol_admin()
 * Retorna true si el usuario actual es administrador
 *
 * @return bool
 */
function verificar_rol_admin() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}

/**
 * Función: verificar_rol_usuario()
 * Retorna true si el usuario actual es usuario normal
 *
 * @return bool
 */
function verificar_rol_usuario() {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'usuario';
}

/**
 * Función: obtener_usuario_sesion()
 * Retorna los datos del usuario en sesión como array
 *
 * @return array Datos del usuario
 */
function obtener_usuario_sesion() {
    return [
        'id'     => $_SESSION['usuario_id'] ?? 0,
        'nombre' => $_SESSION['nombre']     ?? '',
        'email'  => $_SESSION['email']      ?? '',
        'rol'    => $_SESSION['rol']        ?? '',
        'puntos' => $_SESSION['puntos']     ?? 0,
    ];
}

/**
 * Función: obtener_ruta_login()
 * Retorna la ruta al archivo de login según la ubicación actual
 *
 * @return string Ruta al login.php
 */
function obtener_ruta_login() {
    // Detectar si estamos en una subcarpeta
    $script = $_SERVER['SCRIPT_FILENAME'];
    if (strpos($script, '/admin/') !== false || strpos($script, '\\admin\\') !== false) {
        return '../login.php';
    }
    if (strpos($script, '/usuario/') !== false || strpos($script, '\\usuario\\') !== false) {
        return '../login.php';
    }
    if (strpos($script, '/api/') !== false || strpos($script, '\\api\\') !== false) {
        return '../login.php';
    }
    return 'login.php';
}

/**
 * Función: obtener_ruta_base()
 * Retorna la ruta base del proyecto según la ubicación actual
 *
 * @return string Ruta base
 */
function obtener_ruta_base() {
    $script = $_SERVER['SCRIPT_FILENAME'];
    if (
        strpos($script, '/admin/') !== false || strpos($script, '\\admin\\') !== false ||
        strpos($script, '/usuario/') !== false || strpos($script, '\\usuario\\') !== false ||
        strpos($script, '/api/') !== false || strpos($script, '\\api\\') !== false
    ) {
        return '../';
    }
    return '';
}
