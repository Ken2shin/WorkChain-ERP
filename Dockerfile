# ==========================================
# ETAPA 1: COMPILACIÓN MULTI-LENGUAJE (Heavy Lifting)
# ==========================================
FROM debian:bookworm-slim AS multi-builder

# Instalamos compiladores para C, C++, Go, Swift y .NET
RUN apt-get update && apt-get install -y \
    curl build-essential clang lldb lld \
    golang-go swift-all dotnet-sdk-8.0 nasm \
    && rm -rf /var/lib/apt/lists/*

# Instalamos Rust manualmente (más flexible)
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

WORKDIR /app/services
COPY ./services .

# --- Ejemplos de Compilación (Descomenta según necesites) ---
# Rust: RUN cd rust_module && cargo build --release
# Go:   RUN cd go_service && go build -o main .
# C++:  RUN g++ -O3 cpp_core.cpp -o cpp_core
# C#:   RUN dotnet publish -c Release -o ./publish
# ASM:  RUN nasm -f elf64 logic.asm -o logic.o && ld logic.o -o logic

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
# ETAPA 3: IMAGEN FINAL DE PRODUCCIÓN (Optimizada)
# ==========================================
FROM php:8.4-fpm

# Runtimes necesarios para ejecutar binarios de C++, Swift, .NET y Go
RUN apt-get update && apt-get install -y \
    git curl libpq-dev libonig-dev libxml2-dev zip unzip \
    supervisor nginx \
    libstdc++6 libgcc-s1 \
    libicu-dev dotnet-runtime-8.0 \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP para PostgreSQL y rendimiento
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl bcmath

# Instalación de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 1. Backend Laravel
COPY ./laravel .
RUN composer install --no-interaction --optimize-autoloader --no-dev

# 2. Frontend Astro (desde Etapa 2)
COPY --from=frontend-builder /app/frontend/dist ./public/app

# 3. Binarios Compilados (desde Etapa 1)
# Copiamos solo los ejecutables finales a /var/www/bin
COPY --from=multi-builder /app/services/bin_outputs/* ./bin/

# Configuración de Nginx y Supervisor
COPY ./nginx.conf /etc/nginx/sites-available/default
COPY ./docker/supervisor.conf /etc/supervisor/conf.d/worker.conf

# Permisos de seguridad para Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80

# Orquestación de servicios
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/worker.conf"]