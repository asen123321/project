#!/bin/bash
set -e

echo "========================================="
echo "Starting Symfony Application Deployment"
echo "========================================="
echo ""

echo "Checking JWT configuration..."
echo "JWT_PASSPHRASE is: ${JWT_PASSPHRASE:-(empty/not set)}"

echo "Generating JWT keys if missing..."
if [ ! -f config/jwt/private.pem ]; then
    echo "JWT keys not found, generating with EMPTY passphrase..."
    mkdir -p config/jwt
    chown -R www-data:www-data config/jwt
    chmod -R 777 config/jwt

    # Generate JWT keypair with EMPTY passphrase
    # This prevents "error encoding JWT token" issues in production
    JWT_PASSPHRASE="" php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

    if [ $? -eq 0 ]; then
        echo "✓ JWT keys generated successfully!"
        chmod 644 config/jwt/public.pem
        chmod 644 config/jwt/private.pem
        chown -R www-data:www-data config/jwt
    else
        echo "ERROR: JWT key generation failed!"
        exit 1
    fi
else
    echo "✓ JWT keys already exist, skipping generation."
fi

# Verify JWT keys exist and have correct permissions
echo ""
echo "========================================="
echo "Verifying JWT keys before starting..."
echo "========================================="
if [ -f config/jwt/private.pem ] && [ -f config/jwt/public.pem ]; then
    echo "✓ Private key exists: config/jwt/private.pem"
    echo "✓ Public key exists: config/jwt/public.pem"
    ls -lh config/jwt/
    echo "✓ JWT keys are ready!"
else
    echo "ERROR: JWT keys are missing!"
    echo "Private key exists: $([ -f config/jwt/private.pem ] && echo 'YES' || echo 'NO')"
    echo "Public key exists: $([ -f config/jwt/public.pem ] && echo 'YES' || echo 'NO')"
    exit 1
fi
echo "========================================="
echo ""

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
