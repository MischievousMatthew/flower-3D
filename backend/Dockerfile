# Use official PHP + Composer image
FROM php:8.2-cli

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
    && docker-php-ext-install zip pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy backend files
COPY . .

# Install dependencies
RUN composer install --no-dev

# Cache Laravel config
RUN php artisan config:cache

# Expose Render port
EXPOSE 10000

# Run Laravel
CMD php artisan serve --host 0.0.0.0 --port 10000