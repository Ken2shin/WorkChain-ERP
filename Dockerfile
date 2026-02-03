FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    nginx \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql mbstring xml pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy Laravel files
COPY . .

# Install composer dependencies
RUN composer install --no-interaction --prefer-dist

# Install npm dependencies
RUN npm install

# Copy nginx configuration
COPY ./nginx.conf /etc/nginx/sites-available/default

# Set permissions
RUN chown -R www-data:www-data /app

EXPOSE 8000

CMD ["php-fpm"]
