# Usa la imagen oficial de PHP 7.4 con el servidor web Apache
# Esta es la base de nuestro entorno
FROM php:7.4-apache

# Habilitamos el módulo 'rewrite' de Apache para que pueda leer tu archivo .htaccess
# y usar 'reporte.php' como página de inicio
RUN a2enmod rewrite

# Copiamos todos los archivos de tu proyecto (que están en el mismo nivel que el Dockerfile)
# al directorio raíz del servidor web en el contenedor (/var/www/html/)
COPY . /var/www/html/

# Aseguramos que los archivos tengan los permisos correctos para que Apache los pueda leer
RUN chown -R www-data:www-data /var/www/html