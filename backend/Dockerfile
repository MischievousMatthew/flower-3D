# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip pdo_pgsql gd opcache pcntl

# Point Apache to Laravel's public/ folder
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' \
        /etc/apache2/sites-enabled/000-default.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' \
        /etc/apache2/apache2.conf \
    && a2enmod rewrite

# Make Apache listen on port 10000 (required by Render)
RUN sed -i 's|Listen 80|Listen 10000|g' /etc/apache2/ports.conf \
    && sed -i 's|<VirtualHost \*:80>|<VirtualHost *:10000>|g' \
        /etc/apache2/sites-enabled/000-default.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy all project files
COPY . .

# Install PHP dependencies (includes Cloudinary package)
RUN composer install --no-dev --optimize-autoloader --prefer-dist --ignore-platform-reqs

# Set correct permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose Render's required port
EXPOSE 10000

# Start: clear cache, migrate, seed, then start Apache
CMD php artisan config:clear \
    && php artisan cache:clear \
    && php artisan migrate --force \
    && php artisan db:seed --class=AdminSeeder --force \
    && apache2-foreground