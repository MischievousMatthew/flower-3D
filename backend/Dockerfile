# -------------------------------
# 1️⃣ Use official PHP 8.2 Apache image
# -------------------------------
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# -------------------------------
# 2️⃣ Install system dependencies & PHP extensions
# -------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libpq-dev \
    curl \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install zip pdo_pgsql gd

# Enable Apache mod_rewrite for Laravel routes
RUN a2enmod rewrite

# -------------------------------
# 3️⃣ Install Composer
# -------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------
# 4️⃣ Copy all project files
# -------------------------------
COPY . .

# -------------------------------
# 5️⃣ Install backend dependencies
# -------------------------------
RUN composer install --no-dev --optimize-autoloader --prefer-dist --ignore-platform-reqs

# -------------------------------
# 6️⃣ Install frontend dependencies & build assets
# -------------------------------
RUN npm install
RUN npm run build

# -------------------------------
# 7️⃣ Set permissions
# -------------------------------
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# -------------------------------
# 8️⃣ Expose Apache port
# -------------------------------
EXPOSE 80

# -------------------------------
# 9️⃣ Production entrypoint
# -------------------------------
CMD ["apache2-foreground"]