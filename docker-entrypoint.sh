#!/bin/sh
set -e

echo "==> Starting ModerNutrition Laravel Backend on Render..."

# Cache configuration, routes, and views for production performance
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run database migrations and seeds automatically on release
echo "==> Running database migrations & seeders..."
php artisan migrate --force || echo "Migration warning: check database connection."
php artisan db:seed --force || echo "Seeder warning: check database seeds."

# Start PHP-FPM in the background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Start Nginx in the foreground
echo "==> Starting Nginx on port 80..."
nginx -g "daemon off;"
