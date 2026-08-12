<?php
/**
 * usuario/logout.php
 * Cierre de sesión seguro para el usuario normal
 * Destruye completamente la sesión y redirige al login
 */

// Iniciar sesión para poder destruirla
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Limpiar todas las variables de sesión
$_SESSION = [];

// 2. Eliminar la cookie de sesión del navegador
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destruir la sesión completamente en el servidor
session_destroy();

// 4. Redirigir al login con mensaje de confirmación
header('Location: ../login.php?logout=1');
exit();
