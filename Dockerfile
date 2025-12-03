# 1. Използваме PHP 8.3
FROM php:8.3-apache

# 2. Инсталираме библиотеки + инструменти за компилиране
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libsodium-dev \
    acl \
    autoconf \
    pkg-config \
    build-essential \
    dos2unix \
    && rm -rf /var/lib/apt/lists/*

# 3. Инсталираме REDIS
RUN pecl install redis \
    && docker-php-ext-enable redis

# 4. Инсталираме PHP разширения (INCLUDING pdo_mysql for MySQL support!)
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    gd \
    bcmath \
    sockets \
    sodium

# 5. Активираме mod_rewrite (за красиви URL-и)
RUN a2enmod rewrite

# --- НАСТРОЙКА ЗА ПОРТ 8000 (KOYEB REQUIREMENT) ---
# Completely OVERWRITE Apache config files to avoid sed syntax errors
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

# Overwrite ports.conf to listen on port 8000
RUN echo "Listen 8000" > /etc/apache2/ports.conf

# Overwrite 000-default.conf with clean VirtualHost configuration
RUN echo '<VirtualHost *:8000>' > /etc/apache2/sites-available/000-default.conf && \
    echo '    ServerAdmin webmaster@localhost' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    DocumentRoot /var/www/html/public' >> /etc/apache2/sites-available/000-default.conf && \
    echo '' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    <Directory /var/www/html/public>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Options -Indexes +FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf && \
    echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf && \
    echo '' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log' >> /etc/apache2/sites-available/000-default.conf && \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined' >> /etc/apache2/sites-available/000-default.conf && \
    echo '</VirtualHost>' >> /etc/apache2/sites-available/000-default.conf

# --- ВАЖНАТА ПОПРАВКА ЗА 404 ГРЕШКАТА ---
# Това позволява на .htaccess файла да работи и да пренасочва /login към Symfony
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# 7. Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

# 7.5 Създаваме празен .env
RUN touch .env

# 8. Инсталираме пакетите
RUN composer install --optimize-autoloader --no-scripts --no-interaction --prefer-dist

# 9. Оправяме папките и правата
RUN mkdir -p config/jwt var/cache var/log \
    && chown -R www-data:www-data var config/jwt \
    && chmod -R 777 var config/jwt

# 10. Копираме и настройваме entrypoint скрипт
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN dos2unix /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# 11. Отваряме порт 8000
EXPOSE 8000

# 12. Стартираме чрез entrypoint скрипт
CMD ["/usr/local/bin/docker-entrypoint.sh"]

