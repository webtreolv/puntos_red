<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| CONEXIÓN PUNTOS RED
|--------------------------------------------------------------------------
|
| Arquitectura Danfoss AppHub:
|
| ContainerArgs
|      ↓
| KeyVaultUri / Nickname / HashKey
|      ↓
| Managed Identity
|      ↓
| Azure Key Vault
|      ↓
| DBHOST / DBUSER / DBPASSWORD / DBNAME / DBCHARSET
|      ↓
| PDO
|      ↓
| Azure MySQL
|
*/


/*
|--------------------------------------------------------------------------
| Obtener variable de entorno
|--------------------------------------------------------------------------
*/

function env_value(string $name): string
{
    $value = getenv($name);

    if ($value !== false && $value !== '') {
        return (string)$value;
    }


    if (
        isset($_ENV[$name]) &&
        $_ENV[$name] !== ''
    ) {
        return (string)$_ENV[$name];
    }


    if (
        isset($_SERVER[$name]) &&
        $_SERVER[$name] !== ''
    ) {
        return (string)$_SERVER[$name];
    }


    return '';
}


/*
|--------------------------------------------------------------------------
| ContainerArgs
|--------------------------------------------------------------------------
*/

$containerArgsString =
    env_value('ContainerArgs');


$containerArgs = [];


if ($containerArgsString !== '') {

    $decoded =
        json_decode(
            $containerArgsString,
            true
        );


    if (is_array($decoded)) {

        $containerArgs =
            $decoded;
    }
}


/*
|--------------------------------------------------------------------------
| Configuración AppHub
|--------------------------------------------------------------------------
*/

$KEYVAULT_URI =
    (string)(
        $containerArgs['KeyVaultUri']
        ?? ''
    );


$NICKNAME =
    (string)(
        $containerArgs['Nickname']
        ?? ''
    );


$HASH_KEY =
    (string)(
        $containerArgs['HashKey']
        ?? ''
    );


/*
|--------------------------------------------------------------------------
| Managed Identity
|--------------------------------------------------------------------------
*/

$IDENTITY_ENDPOINT =
    env_value(
        'IDENTITY_ENDPOINT'
    );


$IDENTITY_HEADER =
    env_value(
        'IDENTITY_HEADER'
    );


/*
|--------------------------------------------------------------------------
| Obtener secreto
|--------------------------------------------------------------------------
*/

function get_secret(
    string $secretName,
    string $default = ''
): string {

    global
        $KEYVAULT_URI,
        $NICKNAME,
        $HASH_KEY,
        $IDENTITY_ENDPOINT,
        $IDENTITY_HEADER;


    /*
     * ------------------------------------------------------
     * Validar Key Vault
     * ------------------------------------------------------
     */

    if ($KEYVAULT_URI === '') {

        throw new RuntimeException(
            'ContainerArgs no contiene KeyVaultUri.'
        );
    }


    /*
     * ------------------------------------------------------
     * Validar Nickname
     * ------------------------------------------------------
     */

    if ($NICKNAME === '') {

        throw new RuntimeException(
            'ContainerArgs no contiene Nickname.'
        );
    }


    /*
     * ------------------------------------------------------
     * Validar HashKey
     * ------------------------------------------------------
     */

    if ($HASH_KEY === '') {

        throw new RuntimeException(
            'ContainerArgs no contiene HashKey.'
        );
    }


    /*
     * ------------------------------------------------------
     * Validar Managed Identity
     * ------------------------------------------------------
     */

    if ($IDENTITY_ENDPOINT === '') {

        throw new RuntimeException(
            'IDENTITY_ENDPOINT no disponible.'
        );
    }


    if ($IDENTITY_HEADER === '') {

        throw new RuntimeException(
            'IDENTITY_HEADER no disponible.'
        );
    }


    /*
     * ------------------------------------------------------
     * Nombre del secreto
     * ------------------------------------------------------
     *
     * EXACTAMENTE como constants.ts:
     *
     * `${nickName}-${hashKey}-${secretName}`
     */

    $realSecretName =
        $NICKNAME .
        '-' .
        $HASH_KEY .
        '-' .
        $secretName;


    /*
     * ------------------------------------------------------
     * Token Managed Identity
     * ------------------------------------------------------
     *
     * NO se envía client_id.
     */

    $tokenUrl =
        $IDENTITY_ENDPOINT .
        '?resource=' .
        urlencode(
            'https://vault.azure.net'
        ) .
        '&api-version=2019-08-01';


    $ch =
        curl_init(
            $tokenUrl
        );


    if ($ch === false) {

        throw new RuntimeException(
            'No se pudo inicializar CURL.'
        );
    }


    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [

                'X-IDENTITY-HEADER: ' .
                $IDENTITY_HEADER

            ],

            CURLOPT_TIMEOUT => 15,

        ]
    );


    $tokenResponse =
        curl_exec($ch);


    $tokenHttpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $tokenCurlError =
        curl_error($ch);


    curl_close($ch);


    /*
     * ------------------------------------------------------
     * Error CURL
     * ------------------------------------------------------
     */

    if ($tokenResponse === false) {

        throw new RuntimeException(
            'Error Managed Identity: ' .
            $tokenCurlError
        );
    }


    /*
     * ------------------------------------------------------
     * Error HTTP
     * ------------------------------------------------------
     */

    if ($tokenHttpCode !== 200) {

        $detalle =
            $tokenResponse;


        if ($detalle === '') {

            $detalle =
                'Sin cuerpo de respuesta.';
        }


        throw new RuntimeException(
            'Managed Identity respondió HTTP ' .
            $tokenHttpCode .
            '. Respuesta: ' .
            substr(
                $detalle,
                0,
                500
            )
        );
    }


    /*
     * ------------------------------------------------------
     * Decodificar token
     * ------------------------------------------------------
     */

    $tokenData =
        json_decode(
            $tokenResponse,
            true
        );


    $accessToken =
        $tokenData['access_token']
        ?? '';


    if ($accessToken === '') {

        throw new RuntimeException(
            'Managed Identity no devolvió access_token.'
        );
    }


    /*
     * ------------------------------------------------------
     * URL Key Vault
     * ------------------------------------------------------
     */

    $secretUrl =
        rtrim(
            $KEYVAULT_URI,
            '/'
        ) .
        '/secrets/' .
        rawurlencode(
            $realSecretName
        ) .
        '?api-version=7.4';


    /*
     * ------------------------------------------------------
     * Consultar Key Vault
     * ------------------------------------------------------
     */

    $ch =
        curl_init(
            $secretUrl
        );


    if ($ch === false) {

        throw new RuntimeException(
            'No se pudo inicializar CURL para Key Vault.'
        );
    }


    curl_setopt_array(
        $ch,
        [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_HTTPHEADER => [

                'Authorization: Bearer ' .
                $accessToken,

                'Content-Type: application/json'

            ],

            CURLOPT_TIMEOUT => 15,

        ]
    );


    $response =
        curl_exec($ch);


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $curlError =
        curl_error($ch);


    curl_close($ch);


    /*
     * ------------------------------------------------------
     * Error CURL
     * ------------------------------------------------------
     */

    if ($response === false) {

        throw new RuntimeException(
            'Error CURL Key Vault: ' .
            $curlError
        );
    }


    /*
     * ------------------------------------------------------
     * Error Key Vault
     * ------------------------------------------------------
     */

    if ($httpCode !== 200) {

        if ($httpCode === 404 && $default !== '') {
            return $default;
        }

        $detalle =
            $response;


        if ($detalle === '') {

            $detalle =
                'Sin cuerpo de respuesta.';
        }


        throw new RuntimeException(
            'Key Vault respondió HTTP ' .
            $httpCode .
            '. Respuesta: ' .
            substr(
                $detalle,
                0,
                500
            )
        );
    }


    /*
     * ------------------------------------------------------
     * Obtener secreto
     * ------------------------------------------------------
     */

    $data =
        json_decode(
            $response,
            true
        );


    $value =
        $data['value']
        ?? '';


    if ($value === '') {

        throw new RuntimeException(
            "El secreto {$secretName} está vacío."
        );
    }


    return (string)$value;
}


/*
|--------------------------------------------------------------------------
| Configuración MySQL
|--------------------------------------------------------------------------
*/

if ($containerArgsString !== '') {
    define('DB_HOST', get_secret('DBHOST'));
    define('DB_PORT', '3306');
    define('DB_USER', get_secret('DBUSER'));
    define('DB_PASSWORD', get_secret('DBPASSWORD'));
    define('DB_NAME', get_secret('DBNAME'));
    define('DB_CHARSET', 'utf8mb4');
} else {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_USER', 'root');
    define('DB_PASSWORD', '');
    define('DB_NAME', 'puntos_red');
    define('DB_CHARSET', 'utf8mb4');
}


/*
|--------------------------------------------------------------------------
| PDO
|--------------------------------------------------------------------------
*/

$pdo = null;


/*
|--------------------------------------------------------------------------
| Obtener conexión
|--------------------------------------------------------------------------
*/

function obtener_conexion(): PDO
{
    global $pdo;


    /*
     * Reutilizar conexión
     */

    if ($pdo instanceof PDO) {

        return $pdo;
    }


    /*
     * DSN
     */

    $dsn =
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );


    /*
     * Opciones PDO
     */

    $opciones = [

        PDO::ATTR_ERRMODE =>
            PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE =>
            PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES =>
            false,

        PDO::MYSQL_ATTR_INIT_COMMAND =>
            'SET NAMES ' .
            DB_CHARSET,

    ];


    /*
     * Certificado Azure
     */

    $certificado =
        __DIR__ .
        '/../DigiCertGlobalRootG2.crt.pem';


    if (
        is_readable($certificado) &&
        str_contains(
            strtolower(DB_HOST),
            'mysql.database.azure.com'
        )
    ) {

        $opciones[
            PDO::MYSQL_ATTR_SSL_CA
        ] =
            $certificado;


        $opciones[
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
        ] =
            true;
    }


    /*
     * Conectar
     */

    try {

        $pdo =
            new PDO(
                $dsn,
                DB_USER,
                DB_PASSWORD,
                $opciones
            );


        return $pdo;


    } catch (PDOException $e) {

        error_log(
            'No se pudo conectar a MySQL: ' .
            $e->getMessage()
        );


        throw new RuntimeException(
            'No fue posible conectar con Azure MySQL.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Verificar conexión
|--------------------------------------------------------------------------
*/

function verificar_conexion(): bool
{
    try {

        obtener_conexion()
            ->query(
                'SELECT 1'
            );


        return true;


    } catch (Throwable $e) {

        error_log(
            'Error verificando conexión: ' .
            $e->getMessage()
        );


        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Inicializar conexión
|--------------------------------------------------------------------------
*/

$pdo =
    obtener_conexion();