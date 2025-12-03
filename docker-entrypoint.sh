#!/bin/bash
set -e

echo "========================================="
echo "Starting Symfony Application Deployment"
echo "========================================="
echo ""

echo "Checking .env file..."
if [ ! -f .env ]; then
    echo "Creating .env file..."
    echo 'APP_ENV=prod' > .env
    echo 'APP_SECRET=${APP_SECRET:-changeme_generate_a_real_secret_key}' >> .env
    echo 'DATABASE_URL=${DATABASE_URL:-postgresql://user:pass@localhost:5432/dbname}' >> .env
fi

echo "Generating JWT keys if missing..."
if [ ! -f config/jwt/private.pem ]; then
    echo "JWT keys not found, generating..."
    mkdir -p config/jwt
    chown -R www-data:www-data config/jwt
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction || {
        echo "WARNING: JWT key generation failed. Ensure JWT_PASSPHRASE is set in Koyeb."
        echo "Continuing anyway..."
    }
fi

echo "Clearing cache..."
php bin/console cache:clear --no-warmup --env=prod || echo "Cache clear failed, continuing..."

echo "Warming up cache..."
php bin/console cache:warmup --env=prod || echo "Cache warmup failed, continuing..."

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
echo "Starting Supervisor..."
echo "========================================="
echo ""
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
