# =========================
# LARAVEL PRODUCCIÓN
# =========================

FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive

# =========================
# DEPENDENCIAS DEL SISTEMA
# =========================
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx supervisor \
    git curl zip unzip \
    libpq-dev libonig-dev libxml2-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# =========================
# EXTENSIONES PHP
# =========================
RUN docker-php-ext-install \
    pdo pdo_pgsql mbstring xml bcmath zip pcntl

# =========================
# COMPOSER
# =========================
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# =========================
# WORKDIR
# =========================
WORKDIR /var/www

# =========================
# COPIAR CÓDIGO LARAVEL
# =========================
# Copiamos la carpeta completa
COPY ./laravel/ /var/www/

# Aseguramos que artisan sea ejecutable (por si acaso)
RUN chmod +x /var/www/artisan

# =========================
# DEPENDENCIAS PHP (FIX: NO SCRIPTS)
# =========================
# --no-scripts evita que falle si faltan variables de entorno en el build
RUN composer install \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --no-scripts

# =========================
# CACHE Y PERMISOS (CLAVE)
# =========================
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

# Asignamos permisos a las carpetas de almacenamiento y al artisan
RUN chown -R www-data:www-data storage bootstrap/cache /var/www/artisan
RUN chmod -R 775 storage bootstrap/cache /var/www/artisan

# =========================
# NGINX + SUPERVISOR
# =========================
COPY ./docker/nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/laravel.conf

# Exponemos el puerto 80 por convención
EXPOSE 80

# =========================
# ARRANQUE (FIX PARA RENDER)
# =========================
# Usamos 'sh -c' para poder leer la variable de entorno $PORT.
# Si Render asigna puerto 10000, Laravel usará 10000.
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-80}"]