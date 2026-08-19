#!/bin/sh
set -e

echo "==> Starting ModerNutrition Laravel Backend..."

PORT="${PORT:-80}"
echo "==> Configuring Nginx on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/http.d/default.conf || true
sed -i "s/listen \[::\]:80;/listen \[::\]:${PORT};/g" /etc/nginx/http.d/default.conf || true

# Discover packages once vendor is ready
php artisan package:discover --ansi || true

# Cache configuration, routes, and views for production performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run database migrations and seeds automatically on release
echo "==> Running database migrations & seeders..."
php artisan migrate --force || echo "Migration notice: check database connection."
php artisan db:seed --force || echo "Seeder notice: check database seeds."

# Start PHP-FPM in the background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Start Nginx in the foreground
echo "==> Starting Nginx on port ${PORT}..."
nginx -g "daemon off;"
