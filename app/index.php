<?php
/**
 * index.php (raíz)
 * Punto de entrada principal - redirige según sesión activa
 */
require_once 'config/seguridad.php';

if (isset($_SESSION['usuario_id']) && isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'admin') {
        redirigir('admin/index.php');
    } else {
        redirigir('usuario/index.php');
    }
} else {
    redirigir('login.php');
}
