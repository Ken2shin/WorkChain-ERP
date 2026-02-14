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
# CÓDIGO LARAVEL
# =========================
COPY . .

# =========================
# DEPENDENCIAS PHP (FIX REAL)
# =========================
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

RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# =========================
# NGINX + SUPERVISOR
# =========================
COPY ../docker/nginx.conf /etc/nginx/sites-available/default
COPY ../docker/supervisor.conf /etc/supervisor/conf.d/laravel.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/laravel.conf"]
