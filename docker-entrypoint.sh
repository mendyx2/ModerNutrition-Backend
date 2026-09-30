#!/bin/sh
set -e

echo "==> Starting ModerNutrition Laravel Backend..."

# Ensure write permissions on storage & bootstrap/cache
mkdir -p resources/views storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chmod -R 777 storage bootstrap/cache || true

PORT="${PORT:-8080}"
echo "==> Configuring Nginx listeners..."
if [ "$PORT" != "80" ]; then
    sed -i "s/listen 80;/listen 80;\n    listen ${PORT};/g" /etc/nginx/http.d/default.conf || true
fi

# Discover packages
php artisan package:discover --ansi || true

# Start PHP-FPM in the background
echo "==> Starting PHP-FPM..."
php-fpm -D

# Run database migrations and seeders in background so web service boots immediately
(
   echo "==> Running database migrations..."
   n=0
   until [ "$n" -ge 10 ]
   do
      if php artisan migrate --force; then
         echo "==> Running database seeders..."
         php artisan db:seed --force || echo "Seeder notice: completed or skipped."
         break
      fi
      n=$((n+1))
      sleep 2
   done
) &

# Start Nginx in the foreground
echo "==> Starting Nginx..."
nginx -g "daemon off;"
