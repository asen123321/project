#!/bin/bash
set -e

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
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction || echo "JWT generation skipped or failed"
fi

echo "Clearing cache..."
php bin/console cache:clear --no-warmup --env=prod || echo "Cache clear failed, continuing..."

echo "Warming up cache..."
php bin/console cache:warmup --env=prod || echo "Cache warmup failed, continuing..."

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || echo "Migration failed or no migrations to run"

echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
