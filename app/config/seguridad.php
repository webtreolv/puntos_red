<?php
/**
 * config/seguridad.php
 * Funciones de seguridad para proteger la aplicación contra:
 * - SQL Injection (mediante prepared statements)
 * - XSS - Cross Site Scripting (mediante htmlspecialchars)
 * - CSRF - Cross Site Request Forgery (mediante tokens)
 * - Ataques de fuerza bruta (mediante bcrypt)
 */

// Iniciar sesión si no está iniciada (necesario para tokens CSRF)
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sesión segura antes de iniciarla
    ini_set('session.cookie_httponly', 1);    // Cookie no accesible por JavaScript
    ini_set('session.cookie_secure', 0);      // En producción cambiar a 1 (HTTPS)
    ini_set('session.use_strict_mode', 1);    // Rechazar IDs de sesión no generados por el servidor
    session_start();
}

/**
 * Función: limpiar_entrada($dato)
 * Limpia y sanitiza datos de entrada del usuario
 * Previene XSS eliminando caracteres HTML peligrosos
 *
 * @param string $dato Dato a limpiar
 * @return string Dato limpio y seguro
 */
function limpiar_entrada($dato) {
    if (is_array($dato)) {
        // Si es un array, limpiar cada elemento recursivamente
        return array_map('limpiar_entrada', $dato);
    }
    $dato = trim($dato);                                    // Eliminar espacios al inicio y final
    $dato = stripslashes($dato);                            // Eliminar barras invertidas escapadas
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');  // Convertir caracteres HTML especiales
    return $dato;
}

/**
 * Función: validar_email($email)
 * Valida que el formato del email sea correcto
 * Usa filter_var que es más seguro que regex manual
 *
 * @param string $email Email a validar
 * @return bool true si el email es válido
 */
function validar_email($email) {
    $email = trim($email); // Eliminar espacios
    // filter_var con FILTER_VALIDATE_EMAIL verifica formato RFC 5322
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Función: encriptar_contrasena($pass)
 * Encripta una contraseña usando bcrypt (PASSWORD_BCRYPT)
 * bcrypt es resistente a ataques de fuerza bruta por su costo computacional
 *
 * @param string $pass Contraseña en texto plano
 * @return string Hash bcrypt de la contraseña
 */
function encriptar_contrasena($pass) {
    // PASSWORD_BCRYPT genera un hash de 60 caracteres
    // El costo por defecto es 10 (2^10 = 1024 iteraciones)
    return password_hash($pass, PASSWORD_BCRYPT);
}

/**
 * Función: verificar_contrasena($pass, $hash)
 * Verifica si una contraseña coincide con su hash bcrypt
 * password_verify es resistente a timing attacks
 *
 * @param string $pass Contraseña en texto plano ingresada por el usuario
 * @param string $hash Hash almacenado en la base de datos
 * @return bool true si la contraseña es correcta
 */
function verificar_contrasena($pass, $hash) {
    // password_verify compara de forma segura (tiempo constante)
    return password_verify($pass, $hash);
}

/**
 * Función: generar_token_csrf()
 * Genera un token aleatorio para proteger formularios contra CSRF
 * CSRF: un sitio malicioso envía peticiones en nombre del usuario autenticado
 *
 * @return string Token hexadecimal de 64 caracteres
 */
function generar_token_csrf() {
    // Si ya existe un token en sesión, reutilizarlo
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes genera bytes criptográficamente seguros
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 32 bytes = 64 caracteres hex
    }
    return $_SESSION['csrf_token'];
}

/**
 * Función: validar_token_csrf($token)
 * Verifica que el token CSRF del formulario coincida con el de la sesión
 *
 * @param string $token Token recibido del formulario
 * @return bool true si el token es válido
 */
function validar_token_csrf($token = null) {
    // Si no se pasa token, buscarlo en POST
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }

    // Verificar que exista token en sesión y que coincida
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false; // No hay token para comparar
    }

    // hash_equals previene timing attacks al comparar strings
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Función: sanitizar_salida($texto)
 * Sanitiza texto para mostrarlo de forma segura en HTML
 * Previene XSS al escapar caracteres especiales HTML
 *
 * @param string $texto Texto a sanitizar
 * @return string Texto seguro para mostrar en HTML
 */
function sanitizar_salida($texto) {
    // ENT_QUOTES convierte tanto comillas simples como dobles
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Función: validar_numero_entero($valor, $min = 0, $max = PHP_INT_MAX)
 * Valida que un valor sea un número entero dentro de un rango
 *
 * @param mixed $valor Valor a validar
 * @param int $min Valor mínimo permitido
 * @param int $max Valor máximo permitido
 * @return bool true si es un entero válido en el rango
 */
function validar_numero_entero($valor, $min = 0, $max = PHP_INT_MAX) {
    return filter_var($valor, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => $min, 'max_range' => $max]
    ]) !== false;
}

/**
 * Función: redirigir($url)
 * Redirige al usuario a una URL y termina la ejecución
 *
 * @param string $url URL de destino
 */
function redirigir($url) {
    header('Location: ' . $url); // Enviar cabecera de redirección
    exit(); // Terminar ejecución del script actual
}

/**
 * Función: es_peticion_ajax()
 * Detecta si la petición actual es una llamada AJAX
 *
 * @return bool true si es petición AJAX
 */
function es_peticion_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Función: respuesta_json($data, $codigo_http = 200)
 * Envía una respuesta JSON y termina la ejecución
 *
 * @param array $data Datos a enviar como JSON
 * @param int $codigo_http Código de respuesta HTTP
 */
function respuesta_json($data, $codigo_http = 200) {
    http_response_code($codigo_http);                    // Establecer código HTTP
    header('Content-Type: application/json; charset=UTF-8'); // Tipo de contenido JSON
    echo json_encode($data, JSON_UNESCAPED_UNICODE);     // Codificar y enviar JSON
    exit(); // Terminar ejecución
}
