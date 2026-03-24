# Use official PHP 8.2 CLI image
FROM php:8.2-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev default-mysql-client libonig-dev \
    && docker-php-ext-install zip pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy backend contents into container
COPY . .

# Install PHP dependencies
RUN composer install --no-dev

# Cache Laravel config
RUN php artisan config:cache

# Expose port for Render
EXPOSE 10000

# Start Laravel server
CMD php artisan serve --host 0.0.0.0 --port 10000