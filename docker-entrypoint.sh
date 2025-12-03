#!/bin/bash
set -e

echo "========================================="
echo "Starting Symfony Application Deployment"
echo "========================================="
echo ""

echo "Checking environment variables..."
if [ -z "$JWT_PASSPHRASE" ]; then
    echo "WARNING: JWT_PASSPHRASE environment variable is not set!"
    echo "Please set JWT_PASSPHRASE in Koyeb environment variables."
fi

echo "Generating JWT keys if missing..."
if [ ! -f config/jwt/private.pem ]; then
    echo "JWT keys not found, generating..."
    mkdir -p config/jwt
    chown -R www-data:www-data config/jwt
    chmod -R 777 config/jwt

    # Generate JWT keypair with passphrase from environment
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

    if [ $? -eq 0 ]; then
        echo "JWT keys generated successfully!"
        chmod 644 config/jwt/public.pem
        chmod 600 config/jwt/private.pem
        chown -R www-data:www-data config/jwt
    else
        echo "ERROR: JWT key generation failed!"
        echo "Please check JWT_PASSPHRASE environment variable."
        exit 1
    fi
else
    echo "JWT keys already exist, skipping generation."
fi

echo "Clearing cache..."
php bin/console cache:clear --no-warmup --env=prod || echo "Cache clear failed, continuing..."

echo "Warming up cache..."
php bin/console cache:warmup --env=prod || echo "Cache warmup failed, continuing..."

echo "Fixing permissions..."
chown -R www-data:www-data var config/jwt
chmod -R 777 var config/jwt

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
if [ $? -ne 0 ]; then
    echo "ERROR: Database migrations failed!"
    echo "Container will not start. Please check your database connection and migrations."
    exit 1
fi
echo "Migrations completed successfully!"

echo ""
echo "========================================="
echo "Application initialization complete!"
echo "Starting Apache..."
echo "========================================="
echo ""

# Start Apache in foreground
exec apache2-foreground
