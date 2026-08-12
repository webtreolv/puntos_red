FROM php:8.3-apache

# Extensiones PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Configuración de Apache
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Configuración de PHP
COPY php.ini /usr/local/etc/php/

# Copiar TODO el proyecto al contenedor
COPY . /var/www/html/

# Permisos
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

WORKDIR /var/www/html