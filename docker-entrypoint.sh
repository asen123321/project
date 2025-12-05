#!/bin/bash
set -e

echo "========================================="
echo "Starting Symfony Application"
echo "Environment: ${APP_ENV:-prod}"
echo "========================================="

# Fix ONLY essential directories (not entire project)
echo "Fixing essential permissions..."

# Fix var/ directory (cache, logs, sessions)
if [ -d var ]; then
    chown -R www-data:www-data var 2>/dev/null || true
    chmod -R 777 var 2>/dev/null || true
fi

# Fix config/jwt if exists
if [ -d config/jwt ]; then
    chown -R www-data:www-data config/jwt 2>/dev/null || true
    chmod 600 config/jwt/private.pem 2>/dev/null || true
    chmod 644 config/jwt/public.pem 2>/dev/null || true
fi

# Make bin/console executable
if [ -f bin/console ]; then
    chmod +x bin/console 2>/dev/null || true
fi

echo "✓ Permissions fixed!"

# Generate JWT keys if missing (with empty passphrase)
if [ ! -f config/jwt/private.pem ]; then
    echo "Generating JWT keys..."
    mkdir -p config/jwt
    openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096 -pass pass: 2>/dev/null || true
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass: 2>/dev/null || true
    chown -R www-data:www-data config/jwt 2>/dev/null || true
    chmod 600 config/jwt/private.pem 2>/dev/null || true
    chmod 644 config/jwt/public.pem 2>/dev/null || true
    echo "✓ JWT keys generated"
fi

# Create supervisor log directory
mkdir -p /var/log/supervisor

echo ""
echo "Starting Apache and Messenger Worker via Supervisor..."
echo "========================================="

# Start supervisor which manages Apache and Messenger worker
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
