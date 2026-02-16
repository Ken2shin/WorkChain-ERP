# ==========================================
# ETAPA 1: BUILDER MULTI-LENGUAJE (Servicios Internos)
# ==========================================
FROM swift:6.0-bookworm AS swift-source
FROM golang:1.22-bookworm AS go-source

FROM debian:bookworm-slim AS multi-builder

ENV DEBIAN_FRONTEND=noninteractive

# Instalamos herramientas de compilación
RUN apt-get update && apt-get install -y \
    curl wget gnupg ca-certificates \
    build-essential clang lldb lld nasm \
    libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# ---------- Swift ----------
COPY --from=swift-source /usr/bin/swift* /usr/bin/
COPY --from=swift-source /usr/lib/swift /usr/lib/swift
COPY --from=swift-source /usr/lib/libswift* /usr/lib/

# ---------- Go ----------
COPY --from=go-source /usr/local/go /usr/local/go
ENV PATH="/usr/local/go/bin:${PATH}"

# ---------- Rust ----------
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# ---------- .NET ----------
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

# ---------- Compilación de Servicios ----------
WORKDIR /app/services
COPY ./services/ /app/services/
# Creamos carpeta de salida para evitar errores si no hay binarios
RUN mkdir -p /app/services/bin_outputs


# ==========================================
# ETAPA 2: BUILDER FRONTEND (Astro 5)
# ==========================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app/frontend

COPY ./frontend/package.json ./frontend/package-lock.json* ./frontend/pnpm-lock.yaml* ./
# Instalamos dependencias (pnpm o npm)
RUN npm install -g pnpm && pnpm install

COPY ./frontend .
# Compilamos el proyecto Astro
RUN pnpm run build


# ==========================================
# ETAPA 3: PRODUCCIÓN FINAL (Laravel + Nginx + Astro Runtime)
# ==========================================
FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER=1

# 1. Instalamos NODE.JS (Versión 20)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# 2. Instalamos dependencias del sistema y Nginx/Supervisor
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    zip \
    unzip \
    git \
    supervisor \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. Instalamos extensiones de PHP necesarias para Laravel
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    zip \
    bcmath \
    pcntl

# 4. Copiamos Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Configuración de Laravel
WORKDIR /var/www
COPY ./laravel/ /var/www/

# Verificamos que exista artisan
RUN if [ ! -f /var/www/artisan ]; then \
        echo "ERROR: artisan no encontrado en /var/www"; \
        ls -la /var/www; \
        exit 1; \
    fi

RUN chmod +x /var/www/artisan

# Instalamos dependencias de Laravel
RUN composer install \
    --no-interaction \
    --no-dev \
    --optimize-autoloader \
    --no-scripts

# 6. Copiamos Binarios de servicios compilados
RUN mkdir -p /var/www/bin
COPY --from=multi-builder /app/services/bin_outputs/ /var/www/bin/

# 7. Copiamos el Build de Astro
COPY --from=frontend-builder /app/frontend/dist /var/www/astro-server

# =========================================================
# 🔥 CORRECCIÓN CRÍTICA (AQUÍ ESTÁ LA MAGIA)
# =========================================================
# Astro en modo servidor necesita estas librerías en el contenedor final
# para poder conectarse a la base de datos sin fallar.
WORKDIR /var/www/astro-server
# Creamos un package.json mínimo para instalar dependencias
RUN echo '{"type": "module"}' > package.json
# Instalamos pg, bcryptjs y uuid explícitamente
RUN npm install pg bcryptjs uuid
# =========================================================

# Volvemos al directorio raíz
WORKDIR /var/www

# 8. Permisos y Caché de Laravel
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    storage/logs \
    bootstrap/cache

RUN chown -R www-data:www-data storage bootstrap/cache /var/www/artisan
RUN chmod -R 775 storage bootstrap/cache /var/www/artisan

# 9. Configuraciones de Nginx y Supervisor
COPY ./docker/nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/laravel.conf

# Render asigna puerto dinámico, pero Nginx escuchará en el 80
EXPOSE 80

# 10. ARRANQUE MAESTRO
# Supervisor controlará Nginx, PHP y Astro simultáneamente
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/laravel.conf"]