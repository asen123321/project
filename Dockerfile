# 1. Използваме PHP с вграден Apache (най-лесното решение)
FROM php:8.2-apache

# 2. Инсталиране на системни библиотеки и зависимости
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

# 3. Инсталиране на PHP разширения, нужни за Symfony
RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    opcache \
    gd \
    bcmath \
    sockets

# 4. Включване на mod_rewrite за Apache (задължително за Symfony маршрутите)
RUN a2enmod rewrite

# 5. Настройка на Apache да сочи към папка /public (а не към главната)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 6. Инсталиране на Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 7. Задаваме работната папка
WORKDIR /var/www/html

# 8. Копираме файловете на проекта
COPY . .

# 9. Инсталираме PHP пакетите (без скриптове, за да не гърми при билдване)
# Използваме --no-scripts, защото базата данни още не е налична в този момент
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 10. Създаване на кеш папките и оправяне на правата
# Това е критично, за да не дава грешка 500
RUN mkdir -p var/cache var/log \
    && chown -R www-data:www-data var \
    && chmod -R 777 var

# 11. Отваряме порт 80 (стандартния за Apache/Koyeb)
EXPOSE 80

# Apache стартира автоматично, няма нужда от CMD команда