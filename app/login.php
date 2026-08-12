<?php
/**
 * login.php
 * Página de inicio de sesión del sistema Puntos Red
 * Protegida contra: SQL Injection, XSS, CSRF, Fuerza Bruta
 */

// Incluir configuración de seguridad (también inicia sesión)
require_once 'config/seguridad.php';
require_once 'config/conexion.php';

// Si ya tiene sesión activa, redirigir según su rol
if (isset($_SESSION['usuario_id']) && isset($_SESSION['rol'])) {
    if ($_SESSION['rol'] === 'admin') {
        redirigir('admin/index.php');
    } else {
        redirigir('usuario/index.php');
    }
}

// Variables para mensajes de error/éxito
$error   = '';
$success = '';

// Verificar mensajes de redirección en URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'sesion_invalida':  $error = 'Tu sesión no es válida. Inicia sesión nuevamente.'; break;
        case 'sesion_expirada':  $error = 'Tu sesión ha expirado por inactividad.'; break;
        case 'sin_permisos':     $error = 'No tienes permisos para acceder a esa sección.'; break;
        case 'rol_invalido':     $error = 'Rol de usuario no reconocido.'; break;
    }
}
if (isset($_GET['logout'])) {
    $success = 'Has cerrado sesión correctamente.';
}

// ============================================================
// PROCESAMIENTO DEL FORMULARIO DE LOGIN (método POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Validar token CSRF para prevenir ataques Cross-Site Request Forgery
    if (!validar_token_csrf()) {
        $error = 'Error de seguridad. Por favor recarga la página e intenta de nuevo.';
    } else {

        // 2. Obtener y limpiar datos del formulario
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        // 3. Validaciones básicas del lado servidor
        if (empty($email) || empty($password)) {
            $error = 'Por favor completa todos los campos.';
        } elseif (!validar_email($email)) {
            $error = 'El formato del correo electrónico no es válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña debe tener al menos 8 caracteres.';
        } else {

            // 4. Buscar usuario en BD usando PREPARED STATEMENT (previene SQL Injection)
            // NUNCA concatenar variables directamente en SQL
            $stmt = $pdo->prepare(
                "SELECT id, nombre, email, contrasena_hash, puntos_disponibles, rol, estado
                 FROM usuarios
                 WHERE email = ?
                 LIMIT 1"
            );
            $stmt->execute([$email]); // El parámetro se pasa separado del SQL
            $usuario = $stmt->fetch(); // Obtener resultado como array asociativo

            // 5. Verificar usuario y contraseña
            // IMPORTANTE: Siempre verificar contraseña aunque el usuario no exista
            // para prevenir timing attacks (ataques de tiempo)
            if ($usuario && verificar_contrasena($password, $usuario['contrasena_hash'])) {

                // 6. Verificar que el usuario esté activo
                if ($usuario['estado'] !== 'activo') {
                    // Mensaje genérico para no revelar si la cuenta existe
                    $error = 'Credenciales incorrectas o cuenta inactiva.';
                } else {

                    // 7. Regenerar ID de sesión para prevenir Session Fixation
                    session_regenerate_id(true);

                    // 8. Guardar datos del usuario en sesión
                    $_SESSION['usuario_id']      = (int)$usuario['id'];
                    $_SESSION['nombre']          = $usuario['nombre'];
                    $_SESSION['email']           = $usuario['email'];
                    $_SESSION['rol']             = $usuario['rol'];
                    $_SESSION['puntos']          = (int)$usuario['puntos_disponibles'];
                    $_SESSION['ultima_actividad'] = time();

                    // 9. Redirigir según el rol del usuario
                    if ($usuario['rol'] === 'admin') {
                        redirigir('admin/index.php');
                    } else {
                        redirigir('usuario/index.php');
                    }
                }

            } else {
                // Mensaje genérico: NO revelar si el email existe o no
                // Esto previene enumeración de usuarios
                $error = 'Correo electrónico o contraseña incorrectos.';
            }
        }
    }
}

// Generar token CSRF para el formulario
$csrf_token = generar_token_csrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Puntos Red</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           ESTILOS DE LA PÁGINA DE LOGIN
           ============================================================ */
        :root {
            --primary:    #dc2626;
            --primary-light: #ef4444;
            --secondary:  #64748b;
            --success:    #10b981;
            --danger:     #b91c1c;
            --light:      #f8fafc;
            --dark:       #0f172a;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.2) 0%, rgba(92, 17, 17, 0.2) 50%, rgba(220, 38, 38, 0.2) 100%),
                        url('assets/img/login.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Tarjeta principal del login */
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Encabezado de la tarjeta */
        .login-header {
            background: linear-gradient(135deg, var(--dark), var(--primary));
            padding: 40px 40px 30px;
            text-align: center;
            color: white;
        }

        /* Logo circular */
        .login-logo {
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,0.15);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255,255,255,0.2);
            overflow: hidden;
        }

        .login-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Ocultar texto cuando hay logo */
        .login-header:has(.login-logo) .login-texto {
            display: none;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 4px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.75;
            margin: 0;
        }

        /* Texto Puntos Red (reemplaza imagen logo.png) */
        .login-texto {
            font-size: 32px !important;
            font-weight: 800 !important;
            color: #dc2626 !important;
            margin-bottom: 16px !important;
            text-shadow: none !important;
        }

        /* Cuerpo del formulario */
        .login-body {
            padding: 36px 40px 40px;
        }

        /* Etiquetas de campos */
        .form-label {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
            margin-bottom: 6px;
        }

        /* Inputs del formulario */
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.2s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
            background: white;
            outline: none;
        }

        /* Botón de envío */
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.5px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Alertas de error/éxito */
        .alert {
            border-radius: 10px;
            font-size: 14px;
            border: none;
            padding: 12px 16px;
        }

        /* Pie de la tarjeta */
        .login-footer {
            background: #f8fafc;
            padding: 16px 40px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: var(--secondary);
        }

        /* Responsive: ajustar padding en móvil */
        @media (max-width: 480px) {
            .login-body, .login-header { padding-left: 24px; padding-right: 24px; }
            .login-footer { padding-left: 24px; padding-right: 24px; }
        }
    </style>
</head>
<body>

    <div class="login-card">

        <!-- Encabezado con logo -->
        <div class="login-header">
            <div class="login-logo">
                <img src="assets/img/icono.png" alt="Puntos Red">
            </div>
        </div>

        <!-- Cuerpo del formulario -->
        <div class="login-body">

            <!-- Mensaje de error (si existe) -->
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger mb-4" role="alert">
                <?= sanitizar_salida($error) ?>
            </div>
            <?php endif; ?>

            <!-- Mensaje de éxito (si existe) -->
            <?php if (!empty($success)): ?>
            <div class="alert alert-success mb-4" role="alert">
                <?= sanitizar_salida($success) ?>
            </div>
            <?php endif; ?>

            <!-- Formulario de login -->
            <!-- method="post" para no exponer datos en URL -->
            <!-- novalidate: usamos validación HTML5 personalizada -->
            <form method="POST" action="login.php" novalidate id="formLogin">

                <!-- Token CSRF oculto para proteger contra ataques CSRF -->
                <input type="hidden" name="csrf_token" value="<?= sanitizar_salida($csrf_token) ?>">

                <!-- Campo Email -->
                <div class="mb-4">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        placeholder="correo@ejemplo.com"
                        required
                        autocomplete="email"
                        maxlength="200"
                        value="<?= isset($_POST['email']) ? sanitizar_salida($_POST['email']) : '' ?>"
                    >
                    <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
                </div>

                <!-- Campo Contraseña -->
                <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="position-relative">
                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            placeholder="Mínimo 8 caracteres"
                            required
                            minlength="8"
                            maxlength="100"
                            autocomplete="current-password"
                        >
                        <!-- Botón para mostrar/ocultar contraseña -->
                        <button type="button"
                                class="btn btn-sm position-absolute end-0 top-50 translate-middle-y me-2 text-secondary border-0 bg-transparent"
                                onclick="togglePassword()"
                                id="btnTogglePass"
                                title="Mostrar/ocultar contraseña">
                            👁
                        </button>
                    </div>
                    <div class="invalid-feedback">La contraseña debe tener al menos 8 caracteres.</div>
                </div>

                <!-- Botón de envío -->
                <button type="submit" class="btn-login mt-2" id="btnLogin">
                    Iniciar Sesión
                </button>

            </form>
        </div>

        <!-- Pie de la tarjeta -->
        <div class="login-footer">
            &copy; <?= date('Y') ?> Puntos Red &mdash; Sistema de Fidelidad
        </div>

    </div><!-- /login-card -->

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        /**
         * Validación client-side del formulario de login
         * Complementa la validación server-side (no la reemplaza)
         */
        document.getElementById('formLogin').addEventListener('submit', function(e) {
            const email    = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            let valido = true;

            // Validar email con regex básico
            const regexEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !regexEmail.test(email)) {
                document.getElementById('email').classList.add('is-invalid');
                valido = false;
            } else {
                document.getElementById('email').classList.remove('is-invalid');
            }

            // Validar longitud de contraseña
            if (!password || password.length < 8) {
                document.getElementById('password').classList.add('is-invalid');
                valido = false;
            } else {
                document.getElementById('password').classList.remove('is-invalid');
            }

            if (!valido) {
                e.preventDefault(); // Detener envío si hay errores
            } else {
                // Deshabilitar botón para prevenir doble envío
                document.getElementById('btnLogin').disabled = true;
                document.getElementById('btnLogin').textContent = 'Verificando...';
            }
        });

        /**
         * Mostrar/ocultar contraseña al hacer clic en el ojo
         */
        function togglePassword() {
            const input = document.getElementById('password');
            const btn   = document.getElementById('btnTogglePass');
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = '🙈';
            } else {
                input.type = 'password';
                btn.textContent = '👁';
            }
        }
    </script>

</body>
</html>
