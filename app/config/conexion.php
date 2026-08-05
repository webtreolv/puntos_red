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

define('DB_HOST', getenv('DB_HOST') ?: (getenv('DBHOST') ?: 'mysql'));
define('DB_USER', getenv('DB_USER') ?: (getenv('DBUSER') ?: 'root'));
$dbPassword = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DBPASSWORD') !== false ? getenv('DBPASSWORD') : 'root');
define('DB_PASSWORD', $dbPassword);
define('DB_NAME', getenv('DB_NAME') ?: (getenv('DBNAME') ?: 'puntos_red'));
define('DB_CHARSET', getenv('DB_CHARSET') ?: (getenv('DBCHARSET') ?: 'utf8mb4'));

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
        die("
        <h2>Error de conexión PDO</h2>

        <b>Mensaje:</b><br>
        {$e->getMessage()}<br><br>

        <b>Host:</b> " . DB_HOST . "<br>
        <b>Usuario:</b> " . DB_USER . "<br>
        <b>Base:</b> " . DB_NAME . "<br>
        <b>Charset:</b> " . DB_CHARSET . "<br><br>

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
