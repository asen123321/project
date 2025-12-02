FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libpq-dev \
    libsodium-dev \
    autoconf \
    build-essential \
    dos2unix \
    zip \
    unzip \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    pdo_pgsql \
    intl \
    gd \
    zip \
    sockets \
    sodium \
    opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies with platform req bypass
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs \
    --no-interaction \
    --prefer-dist

# Copy application
COPY . /var/www/html

# Optimize autoloader
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# Create production .env file (since .env is gitignored)
RUN echo 'APP_ENV=prod' > .env \
    && echo 'APP_SECRET=${APP_SECRET:-changeme_generate_a_real_secret_key}' >> .env \
    && echo 'APP_URL=${APP_URL:-https://low-gianina-usersymfony-955f83af.koyeb.app}' >> .env \
    && echo 'DEFAULT_URI=${DEFAULT_URI:-https://low-gianina-usersymfony-955f83af.koyeb.app}' >> .env \
    && echo 'DATABASE_URL=${DATABASE_URL:-postgresql://user:pass@localhost:5432/dbname}' >> .env \
    && echo 'MESSENGER_TRANSPORT_DSN=${MESSENGER_TRANSPORT_DSN:-doctrine://default?auto_setup=0}' >> .env \
    && echo 'MAILER_DSN=${MAILER_DSN:-null://null}' >> .env \
    && echo 'JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem' >> .env \
    && echo 'JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem' >> .env \
    && echo 'JWT_PASSPHRASE=${JWT_PASSPHRASE:-}' >> .env \
    && echo 'GOOGLE_CLIENT_ID=${GOOGLE_CLIENT_ID:-}' >> .env \
    && echo 'GOOGLE_CLIENT_SECRET=${GOOGLE_CLIENT_SECRET:-}' >> .env \
    && echo 'GOOGLE_API_KEY=${GOOGLE_API_KEY:-}' >> .env \
    && echo 'MAILER_FROM_EMAIL=${MAILER_FROM_EMAIL:-noreply@example.com}' >> .env \
    && echo 'MAILER_FROM_NAME=${MAILER_FROM_NAME:-Symfony App}' >> .env \
    && echo 'RECAPTCHA_SITE_KEY=${RECAPTCHA_SITE_KEY:-}' >> .env \
    && echo 'RECAPTCHA_SECRET_KEY=${RECAPTCHA_SECRET_KEY:-}' >> .env \
    && echo 'LOCK_DSN=${LOCK_DSN:-flock}' >> .env

# Remove any dev cache and create fresh directories
RUN rm -rf var/cache/* var/log/* \
    && mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 var

# Copy configurations
COPY nginx.conf /etc/nginx/nginx.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy entrypoint and fix CRLF line endings
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port
EXPOSE 8000

# Start via entrypoint (which starts Supervisor)
CMD ["/usr/local/bin/docker-entrypoint.sh"]
