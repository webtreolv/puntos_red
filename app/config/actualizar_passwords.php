<?php
/**
 * config/actualizar_passwords.php
 * Script de utilidad para actualizar las contraseñas en la BD con bcrypt real
 * EJECUTAR UNA SOLA VEZ después de importar el SQL
 * ELIMINAR este archivo después de usarlo por seguridad
 */

require_once 'conexion.php';
require_once 'seguridad.php';

// Contraseñas en texto plano para generar los hashes
$contrasenas = [
    'admin@puntos.com'    => 'Admin123!',
    'carlos@ejemplo.com'  => 'User123!',
    'maria@ejemplo.com'   => 'User123!',
    'roberto@ejemplo.com' => 'User123!',
];

$actualizados = 0;

foreach ($contrasenas as $email => $pass) {
    $hash = encriptar_contrasena($pass); // Generar hash bcrypt

    // Actualizar contraseña en BD usando prepared statement
    $stmt = $pdo->prepare("UPDATE usuarios SET contrasena_hash = ? WHERE email = ?");
    $resultado = $stmt->execute([$hash, $email]);

    if ($resultado) {
        echo "✅ Contraseña actualizada para: $email<br>";
        $actualizados++;
    } else {
        echo "❌ Error al actualizar: $email<br>";
    }
}

echo "<br><strong>Total actualizados: $actualizados</strong><br>";
echo "<br><strong style='color:red'>⚠️ ELIMINA ESTE ARCHIVO AHORA POR SEGURIDAD</strong>";
?>
