# Documentación de Despliegue en Azure (App Service + Key Vault + MySQL)

Este documento describe las adaptaciones realizadas en la arquitectura del proyecto para que funcione correctamente tanto en un entorno local (XAMPP) como en producción (Azure Container App Service) utilizando prácticas de seguridad avanzadas.

## 1. ¿Qué adaptamos para que funcionara?

* **Integración de Entornos (Local vs. Producción):** 
  Modificamos el archivo `config/conexion.php` para que la aplicación detecte de forma automática en qué entorno se está ejecutando. Si detecta la variable de entorno `ContainerArgs`, asume que está en Azure y procede a conectarse al Key Vault. Si no existe, asume que está en local (XAMPP) y utiliza credenciales estándar (`localhost`, `root`, etc.).

* **Autenticación con Azure Key Vault (Managed Identity):**
  En lugar de almacenar las contraseñas en texto plano, la aplicación ahora realiza una petición por medio de cURL hacia `IDENTITY_ENDPOINT` (provisto por Azure) para obtener un token OAuth de identidad administrada (`access_token`). Con este token, la app consulta el Azure Key Vault para descargar de forma segura los secretos de la base de datos.

* **Conexión PDO Segura con SSL:**
  Al conectarse a la base de datos de Azure MySQL, se configuró el uso forzoso del certificado `DigiCertGlobalRootG2.crt.pem` mediante el atributo `PDO::MYSQL_ATTR_SSL_CA`, garantizando que el tráfico entre el App Service y la base de datos esté encriptado.

* **Estructura del Proyecto y Docker:**
  - El código se agrupó dentro de la carpeta `app/`.
  - Se agregó un archivo `index.php` en la raíz que redirecciona automáticamente hacia `app/` para que el punto de entrada funcione correctamente.
  - Se añadieron y ajustaron archivos para Docker (`Dockerfile`, `.dockerignore`, `docker-compose.yml`), asegurando que Apache tuviera copiado su archivo de configuración `000-default.conf` y el de PHP (`php.ini`).
  - El Dockerfile instala extensiones esenciales: `pdo`, `pdo_mysql`, `mysqli` y activa `mod_rewrite`.

* **Prevención de Errores 404 en Key Vault:**
  Se dejaron fijos valores constantes que nunca cambian, como el puerto (`3306`) y la codificación (`utf8mb4`). Esto se hizo para evitar que la aplicación busque secretos inexistentes en el Key Vault y lance errores de tipo "SecretNotFound".

---

## 2. Parámetros y Archivos SIEMPRE Requeridos en Estos Proyectos

Para replicar esta arquitectura en proyectos futuros, **siempre debes asegurarte de contar con lo siguiente**:

### A. Certificados de Seguridad
* `DigiCertGlobalRootG2.crt.pem`: Necesario y obligatorio para establecer una conexión encriptada hacia los servidores de bases de datos administrados por Azure (MySQL Flexible Server).

### B. Secretos en el Azure Key Vault
La bóveda de Azure DEBE contener los siguientes secretos con sus respectivos valores para que la conexión a la base de datos sea exitosa:
* `DBHOST` (Ej: mi-servidor.mysql.database.azure.com)
* `DBUSER` (El nombre de usuario administrador)
* `DBPASSWORD` (La contraseña del usuario)
* `DBNAME` (El nombre de la base de datos)

### C. Configuración del App Service (Variables de Entorno)
El servicio de Azure debe inyectar las siguientes variables de entorno para que el código funcione:
* `ContainerArgs`: Debe ser un string en formato JSON con la información básica, por ejemplo:
  `{"KeyVaultUri":"https://tu-vault.vault.azure.net/","Nickname":"...","HashKey":"..."}`
* `IDENTITY_ENDPOINT` e `IDENTITY_HEADER`: Para que el código obtenga el token de la identidad administrada.

### D. Archivos de Infraestructura
* **`Dockerfile`**: Basado en `php:8.X-apache`, instalando las extensiones `mysqli` y `pdo_mysql`.
* **Archivos Apache/PHP**: Un archivo de host virtual (`000-default.conf`) que defina la raíz del sitio web y un `php.ini` configurado para producción.
* **`.dockerignore`**: Para omitir carpetas pesadas (como `.git/`, `node_modules/`, etc.) y acelerar el tiempo de construcción de la imagen en Azure Container Registry (ACR).

---

## 3. Comparación de Código: Local vs. Adaptación para Azure

Para que esta arquitectura funcione de manera híbrida, el cambio principal ocurre en el archivo de base de datos (`conexion.php`). A continuación se muestra cómo era originalmente (limitado a entorno local) y cómo es la nueva adaptación.

### A. Código Original (Local / XAMPP)
Anteriormente, las credenciales se colocaban directamente en el código de conexión de manera fija.

```php
// conexion.php (Versión Local Clásica)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'puntos_red');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASSWORD
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
```

### B. Código Adaptado (Híbrido: Local + Azure Key Vault)
En la nueva versión, la aplicación busca primero si existe la variable `ContainerArgs`. Si la encuentra, solicita las credenciales seguras al Key Vault; de lo contrario, regresa a la configuración de XAMPP local.

**Cambios específicos aplicados:**
1. **Detección del Entorno:** El bloque `if ($containerArgsString !== '')` diferencia si estamos en Azure o en Local.
2. **Key Vault:** Se integró la función `get_secret('...')` para que la contraseña y usuario se descarguen de manera segura (evitando credenciales hardcodeadas en producción).
3. **Manejo de Errores HTTP:** Se modificó `get_secret` para que si ocurre un error 404, no detenga la app.
4. **Constantes Fijas:** Se dejaron fijos el puerto (`3306`) y charset (`utf8mb4`) directamente en el bloque de Azure para omitir llamadas innecesarias al Key Vault que causaban errores `SecretNotFound`.
5. **Certificado SSL:** Se le indicó explícitamente a PDO que use el certificado SSL (`MYSQL_ATTR_SSL_CA`) si detecta Azure, para asegurar la encriptación de datos.

```php
// conexion.php (Nueva Versión Adaptada)

// 1. Detectar si estamos en Azure (existe la variable ContainerArgs)
$containerArgsString = getenv('ContainerArgs') ?: '';

if ($containerArgsString !== '') {
    // ESTAMOS EN AZURE: Obtener credenciales del Key Vault
    define('DB_HOST', get_secret('DBHOST'));
    define('DB_USER', get_secret('DBUSER'));
    define('DB_PASSWORD', get_secret('DBPASSWORD'));
    define('DB_NAME', get_secret('DBNAME'));
    
    // Parámetros fijos para evitar consultar secretos inexistentes y errores 404
    define('DB_PORT', '3306');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // ESTAMOS EN LOCAL (XAMPP): Usar credenciales estándar
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'puntos_red');
    define('DB_PORT', '3306');
    define('DB_CHARSET', 'utf8mb4');
}

// 2. Conexión PDO aplicando certificado SSL si estamos en Azure
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Si detectamos Azure, obligamos a usar el certificado local para encriptar
    if ($containerArgsString !== '') {
        $opciones[PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/../DigiCertGlobalRootG2.crt.pem';
        $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $opciones);

} catch (PDOException $e) {
    die(json_encode(["success" => false, "mensaje" => "Error de conexión al servidor."]));
}
```
