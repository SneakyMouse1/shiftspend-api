#!/bin/bash

set -e

echo "Copying app files to volume..."
rsync -a --exclude='storage/' /app/. /var/www/

echo "Clearing old cache..."
cd /var/www
php artisan optimize:clear || true 

echo "Setting permissions and ensuring directories exist..."
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/cache
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

cd /var/www

echo "Running migrations..."
php artisan migrate --force || echo "Migrations failed, continuing..."

echo "Running post-install scripts..."
php artisan package:discover --ansi

echo "Linking storage..."
php artisan storage:link --force 2>/dev/null || true

echo "Caching config & routes..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting PHP-FPM..."
exec php-fpm
