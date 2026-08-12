<?php
/**
 * includes/navbar.php
 * Barra de navegación dinámica según el rol del usuario
 * Incluir después de validar_sesion() en cada página
 */

// Obtener datos del usuario en sesión
$usuario_sesion = obtener_usuario_sesion();
$nombre_usuario = sanitizar_salida($usuario_sesion['nombre']);
$puntos_usuario = number_format($usuario_sesion['puntos']);
$rol_usuario    = $usuario_sesion['rol'];

// Determinar prefijo de rutas según ubicación del archivo
$base = obtener_ruta_base();

// Obtener cantidad de productos en carrito (solo para usuarios)
$cantidad_carrito = 0;
if ($rol_usuario === 'usuario' && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) as total FROM carrito WHERE usuario_id = ?");
        $stmt->execute([$usuario_sesion['id']]);
        $cantidad_carrito = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $cantidad_carrito = 0; // Si hay error, mostrar 0
    }
}
?>
<!-- ============================================================
     NAVBAR BOOTSTRAP 5 - PUNTOS RED
     Menú dinámico según rol: admin o usuario
     ============================================================ -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">

        <!-- Logo / Marca -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $base ?>index.php">
            <img src="<?= $base ?>assets/img/menu.png" alt="Puntos Red" style="width: 80px; height: 50px; object-fit: contain;">
        </a>

        <!-- Botón hamburguesa para móvil -->
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navbarPrincipal"
                aria-controls="navbarPrincipal" aria-expanded="false"
                aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Menú colapsable -->
        <div class="collapse navbar-collapse" id="navbarPrincipal">

            <?php if ($rol_usuario === 'admin'): ?>
            <!-- ================================================
                 MENÚ ADMINISTRADOR
                 ================================================ -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : '' ?>"
                       href="<?= $base ?>admin/index.php">
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : '' ?>"
                       href="<?= $base ?>admin/productos.php">
                        Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'active' : '' ?>"
                       href="<?= $base ?>admin/usuarios.php">
                        Usuarios
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'puntos_masivos.php' ? 'active' : '' ?>"
                       href="<?= $base ?>admin/puntos_masivos.php">
                        Puntos Masivos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>"
                       href="<?= $base ?>admin/pedidos.php">
                        Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reportes.php' ? 'active' : '' ?>"
                       href="<?= $base ?>admin/reportes.php">
                        Reportes
                    </a>
                </li>
            </ul>

            <?php else: ?>
            <!-- ================================================
                 MENÚ USUARIO NORMAL
                 ================================================ -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' && strpos($_SERVER['PHP_SELF'], 'usuario') !== false ? 'active' : '' ?>"
                       href="<?= $base ?>usuario/index.php">
                        Inicio
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'mis_pedidos.php' ? 'active' : '' ?>"
                       href="<?= $base ?>usuario/mis_pedidos.php">
                        Mis Pedidos
                    </a>
                </li>
                <li class="nav-item">
                    <!-- Carrito con badge de cantidad -->
                    <a class="nav-link position-relative <?= basename($_SERVER['PHP_SELF']) === 'carrito.php' ? 'active' : '' ?>"
                       href="<?= $base ?>usuario/carrito.php">
                        Carrito
                        <?php if ($cantidad_carrito > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              id="badge-carrito">
                            <?= $cantidad_carrito ?>
                        </span>
                        <?php else: ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none"
                              id="badge-carrito">0</span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
            <?php endif; ?>

            <!-- Sección derecha: usuario + puntos + perfil + logout -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-2">

                <?php if ($rol_usuario === 'usuario'): ?>
                <!-- Mostrar puntos disponibles solo para usuarios -->
                <li class="nav-item">
                    <span class="navbar-text text-warning fw-bold">
                        <?= $puntos_usuario ?> pts
                    </span>
                </li>
                <?php endif; ?>

                <!-- Dropdown de perfil -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                       href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle">
                            <?= strtoupper(substr($nombre_usuario, 0, 1)) ?>
                        </div>
                        <span class="d-none d-lg-inline"><?= $nombre_usuario ?></span>
                        <?php if ($rol_usuario === 'admin'): ?>
                        <span class="badge bg-danger d-none d-lg-inline">Admin</span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li>
                            <a class="dropdown-item" href="<?= $base ?><?= $rol_usuario ?>/perfil.php">
                                Mi Perfil
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= $base ?><?= $rol_usuario ?>/logout.php">
                                Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>

        </div><!-- /collapse -->
    </div><!-- /container -->
</nav>

<!-- Estilos específicos del navbar -->
<style>
/* Logo circular con iniciales */
.logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, #dc2626, #ef4444);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.logo-text {
    color: white;
    font-weight: 800;
    font-size: 14px;
    letter-spacing: 1px;
}
/* Avatar circular con inicial del nombre */
.avatar-circle {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #dc2626, #ef4444);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
}
/* Enlace activo en navbar */
.navbar-dark .navbar-nav .nav-link.active {
    color: #fca5a5 !important;
    border-bottom: 2px solid #ef4444;
}
</style>
