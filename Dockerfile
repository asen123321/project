# 1. PHP 8.3
FROM php:8.3-apache

# 2. Системни библиотеки
RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libpng-dev libonig-dev libxml2-dev acl \
    autoconf pkg-config build-essential \
    && rm -rf /var/lib/apt/lists/*

# 3. Redis
RUN pecl install redis && docker-php-ext-enable redis

# 4. PHP разширения
RUN docker-php-ext-install pdo_mysql intl zip opcache gd bcmath sockets

# 5. Mod Rewrite & Apache Configs
RUN a2enmod rewrite
RUN sed -i 's/80/8000/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# --- ТОВА ЛИПСВАШЕ В ТВОЯ КОД (ОПРАВЯ 404 ГРЕШКАТА) ---
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 6. Composer & Files
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN touch .env

# 7. Install
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# --- ТОВА СЪЩО ЛИПСВАШЕ (ОПРАВЯ JWT ГРЕШКАТА) ---
# Изтриваме старите ключове, които са се копирали от компютъра ти,
# за да не пречат на новите, които ще създадем при старта.
RUN rm -rf config/jwt/*.pem \
    && mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 8. Port
EXPOSE 8000

# 9. START COMMAND (Генерира ключове с паролата от Koyeb при старт)
CMD php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction && apache2-foreground