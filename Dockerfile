# 1. Използваме PHP с вграден Apache
FROM php:8.2-apache

# 2. Инсталиране на системни библиотеки
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    acl \
    && rm -rf /var/lib/apt/lists/*

# --- НОВОТО: Инсталиране на REDIS (това липсваше!) ---
RUN pecl install redis \
    && docker-php-ext-enable redis

# 3. Инсталиране на PHP разширения
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache \
    gd \
    bcmath \
    sockets

# 4. Включване на mod_rewrite
RUN a2enmod rewrite

# 5. Настройка на папките
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

# 7. Инсталиране на пакетите
# Тук вече няма да гърми, защото инсталирахме Redis по-горе
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 8. Създаване на JWT папката и ключовете
RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 9. Генериране на JWT ключове (ако имаш LexikJWT)
RUN php bin/console lexik:jwt:generate-keypair --skip-if-exists || true

# 10. Финални настройки
EXPOSE 80