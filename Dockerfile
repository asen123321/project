# 1. Използваме PHP с Apache
FROM php:8.2-apache

# 2. Инсталираме системни библиотеки + ИНСТРУМЕНТИ ЗА КОМПИЛИРАНЕ (autoconf)
# Без autoconf и build-essential, инсталацията на Redis гърми!
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

# 3. Инсталираме REDIS (вече няма да дава грешка)
RUN pecl install redis \
    && docker-php-ext-enable redis

# 4. Инсталираме стандартните PHP разширения
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
# Казваме на Apache да слуша на порт 8000 вместо на 80
RUN sed -i 's/80/8000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 6. Настройка на основната папка (public)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 7. Инсталираме Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

# 8. Инсталираме пакетите на Symfony
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 9. Оправяме папките и правата (за да няма грешка 500/Permission Denied)
RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 10. Генерираме JWT ключове (ако имаш LexikJWT)
RUN php bin/console lexik:jwt:generate-keypair --skip-if-exists || true

# 11. Отваряме порт 8000
EXPOSE 8000