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
