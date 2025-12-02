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
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
# - pdo_mysql: MySQL database connectivity
# - mbstring: Multibyte string handling for emails
# - exif: Image metadata
# - pcntl: Process control for workers
# - bcmath: Arbitrary precision mathematics
# - gd: Image processing
# - zip: Archive handling
# - sockets: Network socket support for SMTP
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets

# Install gRPC and Protobuf via PECL
RUN pecl install grpc protobuf && docker-php-ext-enable grpc protobuf

# Install Redis extension via PECL
# This enables PHP to communicate with Redis for queue management
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/symfony

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

RUN chown -R www-data:www-data /var/www/symfony

USER www-data

EXPOSE 9000