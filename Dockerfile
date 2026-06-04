# ==============================================================================
# Stage 1: Build Dependencies
# ==============================================================================
FROM php:8.3-cli-alpine AS builder

WORKDIR /var/www/html

# Install build-time dependencies
RUN apk add --no-cache git unzip zip

# Copy Composer binary from official image
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Copy composer manifest files first to cache dependencies layer
COPY composer.json composer.lock ./

# Install packages without running autoloader scripts
RUN composer install --no-interaction --no-scripts --no-progress --prefer-dist

# Copy the rest of the application files
COPY . .

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# ==============================================================================
# Stage 2: Runtime Environment
# ==============================================================================
FROM php:8.3-cli-alpine

WORKDIR /var/www/html

# Install runtime package dependencies
RUN apk add --no-cache sqlite-dev

# Install necessary PHP extensions for SQLite and queue signal handling (pcntl)
RUN docker-php-ext-install pdo_sqlite pcntl

# Copy built vendor and source files from builder stage
COPY --from=builder /var/www/html /var/www/html

# Ensure cache and logging folders are ready
RUN mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/testing \
             storage/framework/views \
             storage/logs \
    && chmod -R 775 storage bootstrap/cache

# Expose HTTP port for Artisan development server
EXPOSE 8000

# Copy and set execution permissions for the entrypoint helper
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

# Default execution command
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
