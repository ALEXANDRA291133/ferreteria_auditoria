FROM php:8.2-apache

# Instalar extensiones de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean

# Copiar todos los archivos al contenedor
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html

# Exponer puerto
EXPOSE 80

# Comando para iniciar Apache
CMD ["apache2-foreground"]
