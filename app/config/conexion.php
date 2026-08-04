<?php
/**
 * config/conexion.php
 * Conexión a la base de datos usando PDO (PHP Data Objects)
 * PDO permite usar prepared statements para prevenir SQL Injection
 */

// Definir constantes de conexión (no mostrar en producción)
define('DB_HOST',     'puntosred-mysql');      // Nombre del servicio en docker-compose.yml
define('DB_USER',     'root');
define('DB_PASSWORD', 'root');       // La contraseña que configuraste en docker-compose.yml
define('DB_NAME',     'puntos_red');
define('DB_CHARSET',  'utf8mb4');

/**
 * Variable global de conexión PDO
 * Se inicializa una sola vez y se reutiliza en todo el proyecto
 */
$pdo = null;

/**
 * Función: obtener_conexion()
 * Retorna la conexión PDO activa (patrón Singleton)
 * Si no existe, la crea; si ya existe, la reutiliza
 *
 * @return PDO Objeto de conexión a la base de datos
 */
function obtener_conexion() {
    global $pdo; // Acceder a la variable global

    // Si ya existe una conexión activa, retornarla directamente
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // Construir el DSN (Data Source Name) para MySQL
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        // Opciones de configuración para PDO
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Lanzar excepciones en errores
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Retornar arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Usar prepared statements reales
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",      // Forzar charset UTF8MB4
        ];

        // Crear la conexión PDO
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $opciones);

        return $pdo; // Retornar la conexión exitosa

    } catch (PDOException $e) {
        // En producción: NO mostrar detalles del error al usuario
        // Solo registrar en log del servidor
        error_log('Error de conexión BD: ' . $e->getMessage()); // Guardar en log del servidor

        // Mostrar mensaje genérico al usuario (sin revelar detalles técnicos)
        die(json_encode([
            'success' => false,
            'mensaje' => 'Error de conexión al servidor. Intente más tarde.'
        ]));
    }
}

/**
 * Función: verificar_conexion()
 * Verifica que la conexión a la BD esté activa
 * Útil para diagnóstico en desarrollo
 *
 * @return bool true si la conexión es exitosa
 */
function verificar_conexion() {
    try {
        $conn = obtener_conexion(); // Intentar obtener conexión
        $conn->query('SELECT 1');   // Ejecutar consulta simple de prueba
        return true;                // Conexión exitosa
    } catch (PDOException $e) {
        error_log('Verificación de conexión fallida: ' . $e->getMessage());
        return false; // Conexión fallida
    }
}

// Inicializar la conexión al incluir este archivo
// Esto asegura que $pdo esté disponible en todos los archivos que incluyan conexion.php
$pdo = obtener_conexion();
