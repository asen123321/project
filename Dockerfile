# 1. Използваме PHP 8.3 (защото composer.lock го изисква)
FROM php:8.3-apache

# 2. Инсталираме системни библиотеки + autoconf
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    acl \
    autoconf \
    pkg-config \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# 3. Инсталираме REDIS
RUN pecl install redis \
    && docker-php-ext-enable redis

# 4. Инсталираме PHP разширения
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache \
    gd \
    bcmath \
    sockets

# 5. Активираме mod_rewrite
RUN a2enmod rewrite

# --- НАСТРОЙКА ЗА ПОРТ 8000 ---
RUN sed -i 's/80/8000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 6. Настройка на основната папка
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

# --- ВАЖНОТО НОВО: СЪЗДАВАМЕ .env ФАЙЛ ---
# Създаваме празен .env файл, за да не гърми Symfony, че липсва.
# Реалните променливи ще се вземат от настройките на Koyeb.
RUN touch .env

# 8. Инсталираме пакетите
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 9. Оправяме папките и правата
RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 10. Генерираме JWT ключове
RUN php bin/console lexik:jwt:generate-keypair --skip-if-exists || true

# 11. Отваряме порт 8000
EXPOSE 8000