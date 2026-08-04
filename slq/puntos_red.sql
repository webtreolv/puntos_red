-- ============================================================
-- BASE DE DATOS: puntos_red
-- Sistema de puntos de fidelidad
-- Motor: InnoDB | Charset: utf8mb4
-- ============================================================

-- Eliminar base de datos si existe y crearla de nuevo
DROP DATABASE IF EXISTS puntos_red;
CREATE DATABASE puntos_red
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE puntos_red;

-- ============================================================
-- TABLA 1: categorias
-- Almacena las categorías de productos disponibles
-- ============================================================
CREATE TABLE categorias (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    descripcion TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 2: usuarios
-- Almacena los usuarios del sistema (admin y usuario normal)
-- ============================================================
CREATE TABLE usuarios (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre              VARCHAR(150) NOT NULL,
    email               VARCHAR(200) NOT NULL UNIQUE,
    contrasena_hash     VARCHAR(255) NOT NULL,           -- Contraseña encriptada con bcrypt
    puntos_disponibles  INT UNSIGNED DEFAULT 0,          -- Puntos actuales del usuario
    rol                 ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    estado              ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion      DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 3: productos
-- Catálogo de productos disponibles para canjear con puntos
-- ============================================================
CREATE TABLE productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(200) NOT NULL,
    descripcion     TEXT,
    categoria_id    INT UNSIGNED NOT NULL,
    precio_puntos   INT UNSIGNED NOT NULL,               -- Costo en puntos del producto
    precio_pesos   DECIMAL(10,2) DEFAULT 0,           -- Costo en pesos mexicanos
    stock           INT UNSIGNED NOT NULL DEFAULT 0,
    imagen_url      VARCHAR(500) DEFAULT 'assets/img/productos/default.png',
    fecha_creacion  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_producto_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 4: carrito
-- Productos que el usuario ha agregado pero no confirmado
-- ============================================================
CREATE TABLE carrito (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    producto_id     INT UNSIGNED NOT NULL,
    cantidad        INT UNSIGNED NOT NULL DEFAULT 1,
    fecha_agregado  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_carrito_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_carrito_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY uq_usuario_producto (usuario_id, producto_id)  -- Un producto por usuario en carrito
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 5: pedidos
-- Pedidos confirmados por los usuarios
-- ============================================================
CREATE TABLE pedidos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    fecha_pedido        DATETIME DEFAULT CURRENT_TIMESTAMP,
    estado              ENUM('Solicitud','En camino','Listo para recoger') NOT NULL DEFAULT 'Solicitud',
    total_puntos_usados INT UNSIGNED NOT NULL DEFAULT 0,
    observaciones       TEXT,
    CONSTRAINT fk_pedido_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA: motivos
-- Catálogo de motivos para asignación de puntos
-- ============================================================
CREATE TABLE motivos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL,
    estado          ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    fecha_creacion  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar motivos iniciales
INSERT INTO motivos (nombre) VALUES ('Campaña');
INSERT INTO motivos (nombre) VALUES ('Promoción');
INSERT INTO motivos (nombre) VALUES ('Ajuste');
INSERT INTO motivos (nombre) VALUES ('Bienvenida');
INSERT INTO motivos (nombre) VALUES ('Otro');

-- ============================================================
-- TABLA 6: detalle_pedidos
-- Productos incluidos en cada pedido
-- ============================================================
CREATE TABLE detalle_pedidos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pedido_id       INT UNSIGNED NOT NULL,
    producto_id     INT UNSIGNED NOT NULL,
    cantidad        INT UNSIGNED NOT NULL DEFAULT 1,
    puntos_unitarios INT UNSIGNED NOT NULL,              -- Precio en puntos al momento de la compra
    CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id)
        REFERENCES productos(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLA 7: historial_puntos
-- Registro de todos los movimientos de puntos (entrada/salida)
-- ============================================================
CREATE TABLE historial_puntos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    admin_id        INT UNSIGNED,                        -- NULL si es movimiento automático del sistema
    cantidad_puntos INT NOT NULL,                        -- Positivo = suma, Negativo = resta
    tipo_movimiento ENUM('asignacion','gasto','devolucion','ajuste') NOT NULL,
    motivo          VARCHAR(200),
    descripcion     TEXT,
    fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_historial_admin FOREIGN KEY (admin_id)
        REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

-- Insertar categorías
INSERT INTO categorias (nombre, descripcion) VALUES
('Tecnología',    'Gadgets, accesorios electrónicos y dispositivos tecnológicos'),
('Textiles',      'Ropa, uniformes, gorras y artículos de tela'),
('Promocionales', 'Artículos con logo: plumas, libretas, tazas, etc.'),
('Hogar',         'Artículos para el hogar y decoración'),
('Gift Cards',    'Tarjetas de regalo para diversas tiendas y servicios');

-- Insertar usuarios (contraseñas encriptadas con bcrypt)
-- Admin: Admin123!
-- Usuario1: User123!
-- Usuario2: User123!
-- Usuario3: User123!
INSERT INTO usuarios (nombre, email, contrasena_hash, puntos_disponibles, rol, estado) VALUES
('Administrador Principal', 'admin@puntos.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10000, 'admin',   'activo'),
('Carlos Mendoza',          'carlos@ejemplo.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1500,  'usuario', 'activo'),
('María González',          'maria@ejemplo.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 800,   'usuario', 'activo'),
('Roberto Sánchez',         'roberto@ejemplo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2000,  'usuario', 'activo');

-- NOTA: El hash anterior es de la contraseña "password" de Laravel/PHP
-- Para producción, regenerar con: password_hash('Admin123!', PASSWORD_BCRYPT)
-- Se incluye un script de actualización al final

-- Insertar productos - Tecnología (categoria_id = 1)
INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, stock, imagen_url) VALUES
('Audífonos Bluetooth',     'Audífonos inalámbricos con cancelación de ruido, 20h batería',    1, 500,  15, 'assets/img/productos/audifonos.png'),
('Mouse Inalámbrico',       'Mouse ergonómico inalámbrico, 1600 DPI, receptor USB',             1, 300,  20, 'assets/img/productos/mouse.png'),
('Teclado Mecánico',        'Teclado mecánico RGB, switches azules, layout español',            1, 600,  10, 'assets/img/productos/teclado.png'),
('Hub USB-C 7 en 1',        'Hub con HDMI 4K, USB 3.0 x3, SD, MicroSD, PD 100W',              1, 400,  25, 'assets/img/productos/hub.png'),
('Cargador Inalámbrico',    'Cargador Qi 15W compatible con iPhone y Android',                  1, 250,  30, 'assets/img/productos/cargador.png'),
('Soporte para Laptop',     'Soporte ajustable aluminio, compatible 11-17 pulgadas',            1, 350,  18, 'assets/img/productos/soporte.png'),
('Webcam HD 1080p',         'Cámara web Full HD con micrófono integrado y tapa privacidad',    1, 450,  12, 'assets/img/productos/webcam.png'),
('Lámpara LED Escritorio',  'Lámpara LED regulable, 3 temperaturas de color, USB',             1, 200,  22, 'assets/img/productos/lampara.png'),
('Disco Duro Externo 1TB',  'Disco duro portátil USB 3.0, 1TB, compatible Win/Mac',            1, 700,  8,  'assets/img/productos/disco.png'),
('Smartwatch Básico',       'Reloj inteligente con monitor cardíaco, pasos y notificaciones',  1, 800,  6,  'assets/img/productos/smartwatch.png');

-- Insertar productos - Textiles (categoria_id = 2)
INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, stock, imagen_url) VALUES
('Playera Polo Corporativa', 'Polo bordado con logo, tela piqué, tallas S-XXL',               2, 300,  50, 'assets/img/productos/polo.png'),
('Gorra Snapback',           'Gorra ajustable con logo bordado, 6 paneles',                   2, 150,  40, 'assets/img/productos/gorra.png'),
('Chamarra Softshell',       'Chamarra resistente al viento, forro polar, logo bordado',       2, 600,  20, 'assets/img/productos/chamarra.png'),
('Mochila Ejecutiva',        'Mochila 25L con compartimento laptop 15", logo impreso',         2, 500,  15, 'assets/img/productos/mochila.png'),
('Calcetines Deportivos x3', 'Pack 3 pares calcetines deportivos con logo, tallas 25-29',     2, 100,  60, 'assets/img/productos/calcetines.png'),
('Camiseta Dry-Fit',         'Camiseta deportiva transpirable, logo sublimado, tallas S-XXL', 2, 200,  45, 'assets/img/productos/camiseta.png'),
('Bufanda Corporativa',      'Bufanda tejida con colores corporativos y logo',                 2, 180,  30, 'assets/img/productos/bufanda.png'),
('Delantal de Cocina',       'Delantal ajustable con logo bordado, tela resistente',          2, 220,  25, 'assets/img/productos/delantal.png'),
('Bolsa Tote Ecológica',     'Bolsa de tela reutilizable, serigrafía logo, 10kg capacidad',   2, 120,  55, 'assets/img/productos/tote.png'),
('Pañuelo Bandana',          'Bandana multifuncional con diseño corporativo',                  2, 80,   70, 'assets/img/productos/bandana.png');

-- Insertar productos - Promocionales (categoria_id = 3)
INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, stock, imagen_url) VALUES
('Pluma Metálica Ejecutiva', 'Pluma de metal con mecanismo giratorio, logo grabado láser',    3, 80,   100, 'assets/img/productos/pluma.png'),
('Libreta Moleskine A5',     'Libreta 120 hojas, tapa dura, logo impreso, incluye pluma',     3, 200,  40,  'assets/img/productos/libreta.png'),
('Taza Cerámica 350ml',      'Taza de cerámica con logo a color, apta para microondas',       3, 150,  60,  'assets/img/productos/taza.png'),
('Termo Acero Inox 500ml',   'Termo doble pared, mantiene temperatura 12h, logo grabado',     3, 300,  35,  'assets/img/productos/termo.png'),
('Llavero Metálico',         'Llavero de metal con logo grabado y mosquetón',                 3, 50,   150, 'assets/img/productos/llavero.png'),
('Agenda 2025',              'Agenda anual tapa dura, semana vista, logo impreso',             3, 250,  30,  'assets/img/productos/agenda.png'),
('Set Escritorio 5 piezas',  'Set: portaplumas, clips, notas, regla, abrecartas con logo',    3, 350,  20,  'assets/img/productos/set_escritorio.png'),
('Paraguas Plegable',        'Paraguas automático 8 varillas, funda con logo',                3, 280,  25,  'assets/img/productos/paraguas.png'),
('Botella Deportiva 750ml',  'Botella plástico libre BPA, tapa flip, logo impreso',           3, 180,  45,  'assets/img/productos/botella.png'),
('Mousepad XL',              'Mousepad 80x40cm con logo impreso, base antideslizante',        3, 220,  30,  'assets/img/productos/mousepad.png');

-- Insertar productos - Hogar (categoria_id = 4)
INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, stock, imagen_url) VALUES
('Juego de Toallas x2',      'Set 2 toallas de baño 100% algodón, bordado con logo',          4, 350,  20, 'assets/img/productos/toallas.png'),
('Portarretratos Digital',   'Marco digital 7" WiFi, 16GB, compatible Google Photos',         4, 600,  10, 'assets/img/productos/marco.png'),
('Vela Aromática Set x3',    'Set 3 velas de soya con aromas: lavanda, vainilla, cítrico',    4, 200,  35, 'assets/img/productos/velas.png'),
('Organizador de Escritorio','Organizador bambú 5 compartimentos, diseño minimalista',         4, 280,  25, 'assets/img/productos/organizador.png'),
('Cafetera de Goteo',        'Cafetera 12 tazas, jarra de vidrio, filtro permanente',         4, 500,  12, 'assets/img/productos/cafetera.png'),
('Juego de Cuchillos x5',    'Set cuchillos acero inoxidable con bloque de madera',           4, 450,  15, 'assets/img/productos/cuchillos.png'),
('Almohada Viscoelástica',   'Almohada memory foam, funda lavable, ergonómica',               4, 400,  18, 'assets/img/productos/almohada.png'),
('Difusor de Aromas',        'Difusor ultrasónico 300ml, 7 colores LED, temporizador',        4, 320,  22, 'assets/img/productos/difusor.png'),
('Tapete Antifatiga',        'Tapete ergonómico cocina 45x75cm, antideslizante',              4, 250,  20, 'assets/img/productos/tapete.png'),
('Báscula Digital Cocina',   'Báscula 5kg precisión 1g, pantalla LCD, tara',                  4, 180,  30, 'assets/img/productos/bascula.png');

-- Insertar productos - Gift Cards (categoria_id = 5)
INSERT INTO productos (nombre, descripcion, categoria_id, precio_puntos, stock, imagen_url) VALUES
('Gift Card Amazon $200',    'Tarjeta de regalo Amazon $200 MXN, código digital',             5, 400,  50, 'assets/img/productos/gc_amazon.png'),
('Gift Card Netflix 1 mes',  'Suscripción Netflix 1 mes plan estándar',                       5, 350,  30, 'assets/img/productos/gc_netflix.png'),
('Gift Card Spotify 3 meses','Suscripción Spotify Premium 3 meses',                           5, 300,  40, 'assets/img/productos/gc_spotify.png'),
('Gift Card Uber $150',      'Crédito Uber $150 MXN para viajes o Uber Eats',                5, 300,  35, 'assets/img/productos/gc_uber.png'),
('Gift Card Cinépolis',      'Boleto doble Cinépolis cualquier función',                       5, 250,  45, 'assets/img/productos/gc_cinepolis.png'),
('Gift Card Liverpool $500', 'Tarjeta de regalo Liverpool $500 MXN',                          5, 1000, 20, 'assets/img/productos/gc_liverpool.png'),
('Gift Card Steam $200',     'Tarjeta Steam $200 MXN para videojuegos',                       5, 400,  25, 'assets/img/productos/gc_steam.png'),
('Gift Card Rappi $200',     'Crédito Rappi $200 MXN para pedidos a domicilio',               5, 400,  30, 'assets/img/productos/gc_rappi.png'),
('Gift Card Starbucks $150', 'Tarjeta Starbucks $150 MXN para bebidas y alimentos',           5, 300,  40, 'assets/img/productos/gc_starbucks.png'),
('Gift Card Google Play $200','Crédito Google Play $200 MXN para apps y contenido',           5, 400,  35, 'assets/img/productos/gc_google.png');

-- Insertar historial de puntos inicial para usuarios
INSERT INTO historial_puntos (usuario_id, admin_id, cantidad_puntos, tipo_movimiento, motivo, descripcion) VALUES
(2, 1, 1500, 'asignacion', 'Bienvenida', 'Puntos de bienvenida al programa Puntos Red'),
(3, 1, 800,  'asignacion', 'Bienvenida', 'Puntos de bienvenida al programa Puntos Red'),
(4, 1, 2000, 'asignacion', 'Bienvenida', 'Puntos de bienvenida al programa Puntos Red');

-- ============================================================
-- SCRIPT PARA ACTUALIZAR CONTRASEÑAS CORRECTAS
-- Ejecutar este procedimiento después de importar el SQL
-- ============================================================
-- Las contraseñas en la tabla son temporales.
-- Para actualizar con bcrypt real, ejecutar en PHP:
--
-- $hash_admin = password_hash('Admin123!', PASSWORD_BCRYPT);
-- $hash_user  = password_hash('User123!',  PASSWORD_BCRYPT);
-- UPDATE usuarios SET contrasena_hash = '$hash_admin' WHERE email = 'admin@puntos.com';
-- UPDATE usuarios SET contrasena_hash = '$hash_user'  WHERE rol = 'usuario';
--
-- O usar el archivo: config/actualizar_passwords.php (incluido en el proyecto)
-- ============================================================

-- Verificar tablas creadas
SELECT 'Base de datos puntos_red creada exitosamente' AS mensaje;
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'puntos_red';
