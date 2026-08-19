# ==============================================================================
# ModerNutrition Laravel Backend — Production Dockerfile for Render
# PHP 8.2 FPM + Nginx on Alpine Linux
# ==============================================================================

FROM php:8.2-fpm-alpine

# Install system dependencies & Nginx
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    mysql-client \
    postgresql-dev

# Install PHP extensions (including pcntl, intl, bcmath, gd, pdo_pgsql, pdo_mysql)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        zip \
        bcmath \
        pcntl \
        intl \
        opcache \
        gd

# Install Redis extension via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Ensure storage directories exist
RUN mkdir -p storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# Install PHP dependencies without script execution at build time
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs --no-scripts

# Configure Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Set permissions
RUN chmod +x artisan docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose HTTP port
EXPOSE 80

# Run entrypoint
CMD ["/var/www/html/docker-entrypoint.sh"]
