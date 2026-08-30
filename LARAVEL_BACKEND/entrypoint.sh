#!/bin/bash
set -e

cd /var/www/html

# Create .env if not exists
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example..."
    cp .env.example .env
fi

# Ensure database directory and storage subdirectories exist
mkdir -p database storage/framework/views storage/framework/cache storage/framework/sessions storage/framework/deploy storage/logs
touch database/database.sqlite
chmod -R 777 storage database

# Install Composer dependencies if vendor folder missing
if [ ! -d vendor ]; then
    echo "Running composer install..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "Vendor folder found, skipping composer install."
fi

# Install Node dependencies if node_modules missing
if [ ! -d node_modules ]; then
    echo "Running npm install..."
    npm install
else
    echo "node_modules found, skipping npm install."
fi

# Generate app key if needed
echo "Generating application key..."
php artisan key:generate --force --no-interaction

# Run database migrations and seeders
echo "Running database migrations and seeders..."
php artisan migrate --force
php artisan db:seed --force

# Run Vite dev server in background for HMR hot reload
echo "Starting Vite dev server with Hot Reload..."
npm run dev -- --host 0.0.0.0 &

echo "Starting RelayIQ web server on 0.0.0.0:8080..."
exec php artisan serve --host=0.0.0.0 --port=8080
