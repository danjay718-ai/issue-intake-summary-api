#!/bin/sh
set -e

# Initialize SQLite database file if it does not exist
if [ ! -f /var/www/html/database/database.sqlite ]; then
    echo "Creating SQLite database..."
    mkdir -p /var/www/html/database
    touch /var/www/html/database/database.sqlite
fi

# Copy environment template if .env is missing
if [ ! -f /var/www/html/.env ]; then
    echo "Creating .env configuration file from example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Run key generation if not set in .env
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run migrations and seed database
echo "Running database migrations..."
php artisan migrate --force

echo "Seeding default database records..."
php artisan db:seed --force

# Ensure runtime directories are writable
chmod -R 775 /var/www/html/storage /var/www/html/database

# Execute the container command
echo "Starting service: $@"
exec "$@"
