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

# Aseguramos que artisan sea ejecutable
RUN chmod +x /var/www/artisan

# =========================
# DEPENDENCIAS PHP
# =========================
RUN composer install \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --no-scripts

# =========================
# CACHE Y PERMISOS
# =========================
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

# Asignamos permisos
RUN chown -R www-data:www-data storage bootstrap/cache /var/www/artisan
RUN chmod -R 775 storage bootstrap/cache /var/www/artisan

# =========================
# ARRANQUE (FIX PARA RENDER)
# =========================
# Exponemos 80 por convención, pero usamos $PORT
EXPOSE 80

# Usamos 'sh -c' para que Laravel tome el puerto que Render le asigne dinámicamente.
# Esto EVITA el error "Port scan timeout".
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-80}"]