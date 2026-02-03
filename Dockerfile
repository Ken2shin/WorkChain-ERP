# ==========================================
# IMÁGENES DE ORIGEN (Para evitar errores 404 de descarga)
# ==========================================
FROM swift:6.0-bookworm AS swift-source
FROM golang:1.22-bookworm AS go-source

# ==========================================
# ETAPA 1: COMPILACIÓN MULTI-LENGUAJE
# ==========================================
FROM debian:bookworm-slim AS multi-builder

# 1. Instalar dependencias base y de compilación
RUN apt-get update && apt-get install -y \
    curl wget gnupg ca-certificates \
    build-essential clang lldb lld nasm \
    libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar Swift (Copiando desde la imagen oficial para evitar 404)
COPY --from=swift-source /usr/bin/swift* /usr/bin/
COPY --from=swift-source /usr/lib/swift /usr/lib/swift
COPY --from=swift-source /usr/lib/libswift* /usr/lib/

# 3. Instalar Go (Copiando desde imagen oficial)
COPY --from=go-source /usr/local/go /usr/local/go
ENV PATH="/usr/local/go/bin:${PATH}"

# 4. Instalar Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# 5. Instalar .NET SDK 8.0 usando el script oficial
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

WORKDIR /app/services
COPY ./services .
RUN mkdir -p bin_outputs

# Aquí irían tus comandos de compilación para Go/Rust/Swift/C# si los necesitas
# RUN go build -o bin_outputs/crypto-service ./go/...
# RUN cargo build --release && cp target/release/anomaly-detector bin_outputs/

# ==========================================
# ETAPA 2: BUILD DEL FRONTEND (ASTRO 5 + TAILWIND 3)
# ==========================================
FROM node:22-slim AS frontend-builder

# Instalamos pnpm de forma global
RUN npm install -g pnpm

WORKDIR /app/frontend

# Copiamos archivos de dependencias
COPY ./frontend/package.json ./frontend/pnpm-lock.yaml* ./

# Instalamos dependencias (esto fallará si no tienes el .dockerignore que te pasé)
RUN pnpm install

# Copiamos el resto del frontend (incluyendo tailwind.config.mjs y postcss.config.cjs corregidos)
COPY ./frontend .

# Ejecutamos el build de Astro
RUN pnpm run build

# ==========================================
# ETAPA 3: IMAGEN FINAL DE PRODUCCIÓN (PHP 8.3)
# ==========================================
FROM php:8.3-fpm

# Instalamos Runtimes necesarios y herramientas de servidor
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget gnupg ca-certificates nginx supervisor \
    git curl zip unzip libpq-dev libonig-dev libxml2-dev \
    libstdc++6 libgcc-s1 libicu72 \
    && rm -rf /var/lib/apt/lists/*

# Instalamos .NET Runtime (solo ejecución)
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --runtime dotnet --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

# Extensiones PHP necesarias para Laravel/ERP
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. Copiar y configurar Backend Laravel
COPY ./laravel .
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 2. Copiar Frontend Astro (Se guarda en la carpeta public/app para convivir con Laravel)
COPY --from=frontend-builder /app/frontend/dist ./public/app

# 3. Copiar Binarios Compilados (Go, Rust, Swift, .NET)
COPY --from=multi-builder /app/services/bin_outputs/* ./bin/

# Configuración de Servidor (Asegúrate de que estos archivos existan en tu repo)
COPY ./docker/nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/worker.conf

# Ajuste de permisos para Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

# Comando de inicio procesado por Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/worker.conf"]