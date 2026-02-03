# ==========================================
# ETAPA 1: COMPILACIÓN MULTI-LENGUAJE (Heavy Lifting)
# ==========================================
FROM debian:bookworm-slim AS multi-builder

# 1. Instalar dependencias base necesarias para agregar repositorios
RUN apt-get update && apt-get install -y \
    curl wget gnupg software-properties-common \
    build-essential clang lldb lld nasm \
    binutils-gold libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses6-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar e Instalar .NET SDK 8.0 (Repositorio Oficial Microsoft)
# Descargamos el registro de paquetes de Microsoft, lo instalamos y luego instalamos el SDK
RUN wget https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb -O packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update \
    && apt-get install -y dotnet-sdk-8.0

# 3. Instalar Swift (Binarios oficiales para Debian 12)
# Descargamos Swift directamente desde swift.org
RUN curl -fsSL https://download.swift.org/swift-5.9.2-release/debian12/swift-5.9.2-RELEASE/swift-5.9.2-RELEASE-debian12.tar.gz -o swift.tar.gz \
    && tar -xzf swift.tar.gz --strip-components=1 -C /usr \
    && rm swift.tar.gz

# 4. Instalar Go y Rust (Repositorios estándar y script oficial)
RUN apt-get update && apt-get install -y golang-go
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

WORKDIR /app/services
COPY ./services .

# --- Aquí irían tus comandos de compilación (Ejemplos) ---
# RUN cd rust_module && cargo build --release
# RUN dotnet publish csharp_service -c Release -o ./bin

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

# Instalación de Runtimes y Repositorio de Microsoft para .NET Runtime
# (Necesario para ejecutar lo que compilaste en la Etapa 1)
RUN apt-get update && apt-get install -y wget gnupg \
    && wget https://packages.microsoft.com/config/debian/12/packages-microsoft-prod.deb -O packages-microsoft-prod.deb \
    && dpkg -i packages-microsoft-prod.deb \
    && rm packages-microsoft-prod.deb \
    && apt-get update && apt-get install -y \
    git curl libpq-dev libonig-dev libxml2-dev zip unzip \
    supervisor nginx \
    libstdc++6 libgcc-s1 libicu-dev \
    dotnet-runtime-8.0 \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP para PostgreSQL
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. Backend Laravel
COPY ./laravel .
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 2. Frontend Astro (desde Etapa 2)
COPY --from=frontend-builder /app/frontend/dist ./public/app

# 3. Binarios Compilados (desde Etapa 1)
# IMPORTANTE: Asegúrate de que esta carpeta exista o comenta esta línea si aún no compilas nada
COPY --from=multi-builder /app/services/bin_outputs/* ./bin/ || true

# Configuración Nginx y Supervisor
COPY ./nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/worker.conf

# Permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/worker.conf"]