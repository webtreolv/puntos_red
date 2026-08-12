<?php
/**
 * admin/includes/funciones_productos.php
 * Funciones de base de datos para gestión de productos
 * Todas usan prepared statements para prevenir SQL Injection
 */

require_once __DIR__ . '/../../config/conexion.php';
require_once __DIR__ . '/../../config/seguridad.php';

/**
 * Función: obtener_productos($categoria_id)
 * Obtiene todos los productos, opcionalmente filtrados por categoría
 *
 * @param int|null $categoria_id ID de categoría para filtrar (null = todos)
 * @return array Lista de productos con nombre de categoría
 */
function obtener_productos($categoria_id = null) {
    global $pdo;
    try {
        if ($categoria_id !== null && is_numeric($categoria_id)) {
            // Filtrar por categoría específica
            $stmt = $pdo->prepare(
                "SELECT p.*, c.nombre AS categoria_nombre
                 FROM productos p
                 INNER JOIN categorias c ON p.categoria_id = c.id
                 WHERE p.categoria_id = ?
                 ORDER BY p.fecha_creacion DESC"
            );
            $stmt->execute([(int)$categoria_id]);
        } else {
            // Obtener todos los productos
            $stmt = $pdo->prepare(
                "SELECT p.*, c.nombre AS categoria_nombre
                 FROM productos p
                 INNER JOIN categorias c ON p.categoria_id = c.id
                 ORDER BY c.nombre, p.nombre"
            );
            $stmt->execute();
        }
        return $stmt->fetchAll(); // Retornar array de productos
    } catch (PDOException $e) {
        error_log('Error obtener_productos: ' . $e->getMessage());
        return []; // Retornar array vacío en caso de error
    }
}

/**
 * Función: obtener_producto($id)
 * Obtiene un producto específico por su ID
 *
 * @param int $id ID del producto
 * @return array|false Datos del producto o false si no existe
 */
function obtener_producto($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, c.nombre AS categoria_nombre
             FROM productos p
             INNER JOIN categorias c ON p.categoria_id = c.id
             WHERE p.id = ?
             LIMIT 1"
        );
        $stmt->execute([(int)$id]);
        return $stmt->fetch(); // Retorna false si no encuentra el producto
    } catch (PDOException $e) {
        error_log('Error obtener_producto: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: crear_producto($datos)
 * Inserta un nuevo producto en la base de datos
 *
 * @param string $nombre       Nombre del producto
 * @param string $descripcion  Descripción del producto
 * @param int    $categoria_id ID de la categoría
 * @param int    $precio       Precio en puntos
 * @param int    $stock        Cantidad disponible
 * @param string $imagen_url   Ruta de la imagen
 * @return bool true si se creó correctamente
 */
function crear_producto($nombre, $descripcion, $categoria_id, $precio, $stock, $imagen_url, $precio_pesos = 0) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, precio_pesos, stock, imagen_url)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        return $stmt->execute([
            trim($nombre),
            trim($descripcion),
            (int)$categoria_id,
            (int)$precio,
            (float)$precio_pesos,
            (int)$stock,
            $imagen_url
        ]);
    } catch (PDOException $e) {
        error_log('Error crear_producto: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: actualizar_producto($id, ...)
 * Actualiza los datos de un producto existente
 *
 * @param int    $id           ID del producto a actualizar
 * @param string $nombre       Nuevo nombre
 * @param string $descripcion  Nueva descripción
 * @param int    $categoria_id Nueva categoría
 * @param int    $precio       Nuevo precio en puntos
 * @param int    $stock        Nuevo stock
 * @param string $imagen_url   Nueva imagen (o la misma si no cambió)
 * @return bool true si se actualizó correctamente
 */
function actualizar_producto($id, $nombre, $descripcion, $categoria_id, $precio, $stock, $imagen_url, $precio_pesos = 0) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "UPDATE productos
             SET nombre = ?, descripcion = ?, categoria_id = ?,
                 precio_puntos = ?, precio_pesos = ?, stock = ?, imagen_url = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            trim($nombre),
            trim($descripcion),
            (int)$categoria_id,
            (int)$precio,
            (float)$precio_pesos,
            (int)$stock,
            $imagen_url,
            (int)$id
        ]);
    } catch (PDOException $e) {
        error_log('Error actualizar_producto: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: eliminar_producto($id)
 * Elimina un producto de la base de datos
 * NOTA: Verificar que no tenga pedidos activos antes de eliminar
 *
 * @param int $id ID del producto a eliminar
 * @return bool true si se eliminó correctamente
 */
function eliminar_producto($id) {
    global $pdo;
    try {
        // Primero obtener la imagen para eliminarla del servidor
        $producto = obtener_producto($id);

        $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
        $resultado = $stmt->execute([(int)$id]);

        // Si se eliminó y tiene imagen personalizada, eliminar el archivo
        if ($resultado && $producto && $producto['imagen_url'] !== 'assets/img/productos/default.png') {
            $ruta_imagen = __DIR__ . '/../../' . $producto['imagen_url'];
            if (file_exists($ruta_imagen)) {
                unlink($ruta_imagen); // Eliminar archivo de imagen
            }
        }

        return $resultado;
    } catch (PDOException $e) {
        error_log('Error eliminar_producto: ' . $e->getMessage());
        return false;
    }
}

/**
 * Función: validar_imagen($archivo)
 * Valida que el archivo subido sea una imagen válida
 *
 * @param array $archivo Elemento de $_FILES
 * @return array ['valido' => bool, 'error' => string]
 */
function validar_imagen($archivo) {
    // Verificar que se subió un archivo sin errores
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['valido' => false, 'error' => 'Error al subir el archivo.'];
    }

    // Verificar tamaño máximo: 2MB = 2 * 1024 * 1024 bytes
    $max_size = 2 * 1024 * 1024;
    if ($archivo['size'] > $max_size) {
        return ['valido' => false, 'error' => 'La imagen no debe superar 2MB.'];
    }

    // Verificar extensión permitida
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensiones_permitidas)) {
        return ['valido' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WEBP.'];
    }

    // Verificar tipo MIME real del archivo (no confiar solo en la extensión)
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    $tipo_mime = mime_content_type($archivo['tmp_name']);
    if (!in_array($tipo_mime, $tipos_permitidos)) {
        return ['valido' => false, 'error' => 'El archivo no es una imagen válida.'];
    }

    return ['valido' => true, 'error' => ''];
}

/**
 * Función: guardar_imagen($archivo, $producto_id)
 * Guarda la imagen del producto en el servidor
 *
 * @param array $archivo     Elemento de $_FILES
 * @param int   $producto_id ID del producto (para nombrar el archivo)
 * @return string Ruta relativa de la imagen guardada
 */
function guardar_imagen($archivo, $producto_id) {
    // Directorio donde se guardan las imágenes
    $directorio = __DIR__ . '/../../assets/img/productos/';

    // Crear directorio si no existe
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }

    // Generar nombre único: producto_ID_timestamp.ext
    $extension  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $nombre_archivo = 'producto_' . $producto_id . '_' . time() . '.' . $extension;
    $ruta_completa  = $directorio . $nombre_archivo;

    // Mover el archivo temporal al directorio de destino
    if (move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
        return 'assets/img/productos/' . $nombre_archivo; // Retornar ruta relativa
    }

    return 'assets/img/productos/default.png'; // Imagen por defecto si falla
}

/**
 * Función: obtener_categorias()
 * Obtiene todas las categorías disponibles
 *
 * @return array Lista de categorías
 */
function obtener_categorias() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM categorias ORDER BY nombre");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('Error obtener_categorias: ' . $e->getMessage());
        return [];
    }
}

/**
 * Función: obtener_ultimo_id_insertado()
 * Retorna el ID del último registro insertado
 *
 * @return int ID del último registro
 */
function obtener_ultimo_id_insertado() {
    global $pdo;
    return (int)$pdo->lastInsertId();
}
