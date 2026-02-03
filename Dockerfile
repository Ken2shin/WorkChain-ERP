# ==========================================
# IMÁGENES DE ORIGEN (Prevención de errores 404)
# ==========================================
FROM swift:6.0-bookworm AS swift-source
FROM golang:1.22-bookworm AS go-source

# ==========================================
# ETAPA 1: COMPILACIÓN MULTI-LENGUAJE
# ==========================================
FROM debian:bookworm-slim AS multi-builder

RUN apt-get update && apt-get install -y \
    curl wget gnupg ca-certificates \
    build-essential clang lldb lld nasm \
    libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# Instalación de Swift
COPY --from=swift-source /usr/bin/swift* /usr/bin/
COPY --from=swift-source /usr/lib/swift /usr/lib/swift
COPY --from=swift-source /usr/lib/libswift* /usr/lib/

# Instalación de Go
COPY --from=go-source /usr/local/go /usr/local/go
ENV PATH="/usr/local/go/bin:${PATH}"

# Instalación de Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# Instalación de .NET SDK
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

WORKDIR /app/services
COPY ./services .
RUN mkdir -p bin_outputs

# ==========================================
# ETAPA 2: BUILD DEL FRONTEND (ASTRO 5 + TAILWIND 3)
# ==========================================
FROM node:22-slim AS frontend-builder
RUN npm install -g pnpm
WORKDIR /app/frontend
COPY ./frontend/package.json ./frontend/pnpm-lock.yaml* ./
RUN pnpm install
COPY ./frontend .
RUN pnpm run build

# ==========================================
# ETAPA 3: IMAGEN FINAL DE PRODUCCIÓN (PHP 8.3)
# ==========================================
FROM php:8.3-fpm

# 1. Instalar librerías del sistema (libicu76 + zip + runtimes)
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget gnupg ca-certificates nginx supervisor \
    git curl zip unzip libpq-dev libonig-dev libxml2-dev libzip-dev \
    libstdc++6 libgcc-s1 libicu76 \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar .NET Runtime
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --runtime dotnet --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

# 3. Extensiones PHP (Incluye zip vital para Composer)
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl bcmath zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 4. Backend Laravel (Con --no-scripts para evitar error de artisan)
COPY ./laravel .
RUN composer install --no-interaction --optimize-autoloader --no-dev --no-scripts

# 5. Frontend y Binarios
COPY --from=frontend-builder /app/frontend/dist ./public/app
COPY --from=multi-builder /app/services/bin_outputs/* ./bin/

# 6. Configuración de Servidor
COPY ./docker/nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/worker.conf

# 7. CORRECCIÓN FINAL: Crear carpetas faltantes antes de asignar permisos
#    Git ignora estas carpetas, por eso fallaba el chown. Las creamos a mano:
RUN mkdir -p /var/www/storage/framework/sessions \
    /var/www/storage/framework/views \
    /var/www/storage/framework/cache \
    /var/www/storage/logs \
    /var/www/bootstrap/cache

# 8. Asignar Permisos Correctos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/worker.conf"]