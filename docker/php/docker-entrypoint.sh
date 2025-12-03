#!/bin/bash
set -e

echo "========================================="
echo "Fixing essential permissions..."
echo "========================================="

# Only fix ownership for directories that need write access
# This is much faster than chown -R on the entire project
if [ -d /var/www/symfony/var ]; then
    chown -R www-data:www-data /var/www/symfony/var
    chmod -R 775 /var/www/symfony/var
fi

# Fix config/jwt permissions if exists
if [ -d /var/www/symfony/config/jwt ]; then
    chown -R www-data:www-data /var/www/symfony/config/jwt
    if [ -f /var/www/symfony/config/jwt/private.pem ]; then
        chmod 600 /var/www/symfony/config/jwt/private.pem
    fi
    if [ -f /var/www/symfony/config/jwt/public.pem ]; then
        chmod 644 /var/www/symfony/config/jwt/public.pem
    fi
fi

# Make bin/console executable
if [ -f /var/www/symfony/bin/console ]; then
    chmod +x /var/www/symfony/bin/console
fi

echo "✓ Permissions fixed!"
echo ""

# Start PHP-FPM or execute custom command
# PHP-FPM will automatically run as www-data based on its pool configuration
if [ "$#" -gt 0 ]; then
    echo "Executing command as www-data: $@"
    exec gosu www-data "$@"
else
    echo "Starting PHP-FPM..."
    exec php-fpm
fi
