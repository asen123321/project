# 1. СМЯНА НА ВЕРСИЯТА: Ползваме PHP 8.3 (заради composer.lock)
FROM php:8.3-apache

# 2. Инсталиране на библиотеки + инструменти за Redis
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libpng-dev libonig-dev libxml2-dev acl \
    autoconf pkg-config build-essential \
    && rm -rf /var/lib/apt/lists/*

# 3. Инсталиране на REDIS
RUN pecl install redis && docker-php-ext-enable redis

# 4. Инсталиране на PHP разширения
RUN docker-php-ext-install pdo_mysql intl zip opcache gd bcmath sockets

# 5. Активиране на mod_rewrite и смяна на Порт 8000
RUN a2enmod rewrite
RUN sed -i 's/80/8000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 6. Настройка на Apache (ВАЖНО: Оправя 404 грешката на Login)
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
# Този ред липсваше в твоя код:
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 7. Копиране на файловете
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
# Създаваме празен .env, за да не гърми
RUN touch .env

# 8. Инсталиране на пакетите
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 9. ПОПРАВКА ЗА JWT (Изтриваме старите/грешни ключове)
# Това е критично, за да се махнат онези "Zone.Identifier" файлове
RUN rm -rf config/jwt/*.pem \
    && mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 10. Порт
EXPOSE 8000

# 11. СТАРТ КОМАНДА (Генерира нови ключове с паролата от Koyeb)
CMD php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction && apache2-foreground