#!/bin/sh
set -e

echo "==> Starting ModerNutrition Laravel Backend..."

# Ensure write permissions on storage & bootstrap/cache
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache || true

PORT="${PORT:-80}"
echo "==> Configuring Nginx on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/http.d/default.conf || true
sed -i "s/listen \[::\]:80;/listen \[::\]:${PORT};/g" /etc/nginx/http.d/default.conf || true

# Discover packages once vendor is ready
php artisan package:discover --ansi || true

# Run database migrations with retry loop to ensure tables exist
echo "==> Running database migrations..."
n=0
until [ "$n" -ge 5 ]
do
   php artisan migrate --force && break
   n=$((n+1))
   echo "Database migration attempt $n failed. Retrying in 3 seconds..."
   sleep 3
done

echo "==> Running database seeders..."
php artisan db:seed --force || echo "Seeder notice: seeders completed or already run."

# Cache configuration, routes, and views for production performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Start PHP-FPM in the background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Start Nginx in the foreground
echo "==> Starting Nginx on port ${PORT}..."
nginx -g "daemon off;"
