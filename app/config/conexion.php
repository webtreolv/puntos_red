<?php
/**
 * config/conexion.php
 * Conexión a la base de datos usando PDO
 */

// ===============================
// Cargar variables de entorno (.env)
// ===============================
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// ===============================
// Configuración de la Base de Datos
// ===============================

function obtener_variable_entorno($key1, $key2, $default) {
    $posibles = [$key1, $key2, 'APPSETTING_' . $key1, 'APPSETTING_' . $key2, 'MYSQLCONNSTR_' . $key1];
    foreach ($posibles as $k) {
        $val = getenv($k);
        if ($val !== false && $val !== '') return $val;
        if (isset($_SERVER[$k]) && $_SERVER[$k] !== '') return $_SERVER[$k];
        if (isset($_ENV[$k]) && $_ENV[$k] !== '') return $_ENV[$k];
    }
    return $default;
}

define('DB_HOST', obtener_variable_entorno('DB_HOST', 'DBHOST', 'mysql'));
define('DB_USER', obtener_variable_entorno('DB_USER', 'DBUSER', 'root'));
define('DB_PASSWORD', obtener_variable_entorno('DB_PASSWORD', 'DBPASSWORD', 'root'));
define('DB_NAME', obtener_variable_entorno('DB_NAME', 'DBNAME', 'puntos_red'));
define('DB_CHARSET', obtener_variable_entorno('DB_CHARSET', 'DBCHARSET', 'utf8mb4'));

// ===============================
// Variable global
// ===============================

$pdo = null;

// ===============================
// Obtener conexión
// ===============================

function obtener_conexion()
{
    global $pdo;

    // Reutilizar la conexión si ya existe
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        // Configuración de SSL requerida por Azure Database for MySQL
        $certPath = __DIR__ . '/../DigiCertGlobalRootG2.crt.pem';
        if (file_exists($certPath) && strpos(DB_HOST, 'azure.com') !== false) {
            $opciones[PDO::MYSQL_ATTR_SSL_CA] = $certPath;
            $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $pdo = new PDO(
            $dsn,
            DB_USER,
            DB_PASSWORD,
            $opciones
        );

        return $pdo;

    } catch (Throwable $e) {

        // TEMPORAL PARA DEPURAR AZURE
        $env_keys_server = implode(', ', array_keys($_SERVER));
        $env_keys_env = implode(', ', array_keys($_ENV));
        die("
        <h2>Error de conexión PDO</h2>

        <b>Mensaje:</b><br>
        {$e->getMessage()}<br><br>

        <b>Host intentado:</b> " . DB_HOST . "<br>
        <b>Usuario intentado:</b> " . DB_USER . "<br>
        <b>Base intentada:</b> " . DB_NAME . "<br>
        <b>Charset intentado:</b> " . DB_CHARSET . "<br><br>

        <b>Llaves en \$_SERVER:</b> " . htmlspecialchars($env_keys_server) . "<br><br>
        <b>Llaves en \$_ENV:</b> " . htmlspecialchars($env_keys_env) . "<br><br>

        <b>Archivo:</b><br>
        {$e->getFile()}<br><br>

        <b>Línea:</b><br>
        {$e->getLine()}
        ");
    }
}

// ===============================
// Verificar conexión
// ===============================

function verificar_conexion()
{
    try {

        $conn = obtener_conexion();

        $conn->query("SELECT 1");

        return true;

    } catch (Throwable $e) {

        error_log($e->getMessage());

        return false;
    }
}

// ===============================
// Inicializar conexión
// ===============================

$pdo = obtener_conexion();
