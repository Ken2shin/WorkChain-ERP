# ==========================================
# ETAPA 1: COMPILACIÓN MULTI-LENGUAJE (Heavy Lifting)
# ==========================================
FROM debian:bookworm-slim AS multi-builder

# 1. Instalar dependencias base
# CORRECCIÓN: Cambiado 'libncurses6-dev' por 'libncurses-dev'
RUN apt-get update && apt-get install -y \
    curl wget gnupg ca-certificates software-properties-common \
    build-essential clang lldb lld nasm \
    binutils-gold libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar e Instalar .NET SDK 8.0
# NOTA: Usamos el script oficial dotnet-install.sh para evitar depender del
# repositorio APT de Microsoft (problemas de firma SHA1 con sequoia/apt).
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

# 3. Instalar Swift
RUN curl -fsSL https://download.swift.org/swift-5.9.2-release/debian12/swift-5.9.2-RELEASE/swift-5.9.2-RELEASE-debian12.tar.gz -o swift.tar.gz \
    && tar -xzf swift.tar.gz --strip-components=1 -C /usr \
    && rm swift.tar.gz

# 4. Instalar Go y Rust
RUN apt-get update && apt-get install -y golang-go \
    && rm -rf /var/lib/apt/lists/*
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

WORKDIR /app/services
COPY ./services .

# --- PREPARACIÓN DE SALIDA ---
RUN mkdir -p bin_outputs && touch bin_outputs/.gitkeep

# ==========================================
# ETAPA 2: BUILD DEL FRONTEND (ASTRO)
# ==========================================
FROM node:22-slim AS frontend-builder
WORKDIR /app/frontend
COPY ./frontend/package.json ./frontend/pnpm-lock.yaml* ./
RUN npm install -g pnpm && pnpm install
COPY ./frontend .
RUN pnpm run build

# ==========================================
# ETAPA 3: IMAGEN FINAL DE PRODUCCIÓN
# ==========================================
FROM php:8.3-fpm

# Runtimes necesarios (sin usar el repo APT de Microsoft)
RUN apt-get update && apt-get install -y --no-install-recommends \
    wget gnupg ca-certificates \
    git curl libpq-dev libonig-dev libxml2-dev zip unzip \
    supervisor nginx \
    libstdc++6 libgcc-s1 libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar dotnet runtime usando el instalador oficial (evita el error de firma SHA1)
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --runtime dotnet --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

# Extensiones PHP
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. Backend Laravel
COPY ./laravel .
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 2. Frontend Astro
COPY --from=frontend-builder /app/frontend/dist ./public/app

# 3. Binarios Compilados
COPY --from=multi-builder /app/services/bin_outputs/* ./bin/

# Configuración Nginx y Supervisor
COPY ./nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/worker.conf

# Permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/worker.conf"]
