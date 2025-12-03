FROM php:8.4-fpm

# Install system dependencies for PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    zlib1g-dev \
    autoconf \
    automake \
    libtool \
    g++ \
    make \
    gosu \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets

# Install gRPC and Protobuf via PECL
RUN pecl install grpc protobuf && docker-php-ext-enable grpc protobuf

# Install Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Copy composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/symfony

# Copy PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Copy composer files first (for better layer caching)
COPY composer.json composer.lock symfony.lock ./

# Install PHP dependencies (production mode)
# Skip post-install scripts to avoid needing APP_SECRET during build
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY . .

# Optimize autoloader for production (classmap-authoritative)
# This creates an optimized autoloader that only uses the classmap
RUN composer dump-autoload --no-dev --classmap-authoritative

# Fix permissions for directories that need write access
RUN mkdir -p var/cache var/log var/sessions && \
    chown -R www-data:www-data var/ && \
    chmod -R 775 var/

# Fix config/jwt permissions if exists
RUN if [ -d config/jwt ]; then \
        chown -R www-data:www-data config/jwt && \
        chmod 600 config/jwt/private.pem 2>/dev/null || true && \
        chmod 644 config/jwt/public.pem 2>/dev/null || true; \
    fi

# Make bin/console executable
RUN chmod +x bin/console

# Copy and setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

# Use entrypoint to start PHP-FPM
# Cache will be warmed up at container startup when APP_SECRET is available
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
