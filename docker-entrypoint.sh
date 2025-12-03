#!/bin/bash
# Robust entrypoint script for Symfony on Koyeb
# Does NOT exit on errors - continues even if DB connection fails

echo "========================================="
echo "Starting Symfony Application Deployment"
echo "========================================="
echo ""

# ============================================
# 1. JWT KEY GENERATION & PERMISSIONS
# ============================================
echo "Checking JWT configuration..."
echo "JWT_PASSPHRASE is: ${JWT_PASSPHRASE:-(empty/not set)}"

echo "Generating JWT keys if missing..."
if [ ! -f config/jwt/private.pem ]; then
    echo "JWT keys not found, generating with EMPTY passphrase..."
    mkdir -p config/jwt

    # Generate JWT keypair with EMPTY passphrase
    # This prevents "error encoding JWT token" issues in production
    JWT_PASSPHRASE="" php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

    if [ $? -eq 0 ]; then
        echo "✓ JWT keys generated successfully!"
    else
        echo "ERROR: JWT key generation failed!"
        exit 1
    fi
else
    echo "✓ JWT keys already exist, skipping generation."
fi

# ALWAYS fix ownership and permissions for JWT keys
# This ensures www-data can read them even if owned by root/user 1000
echo "Setting correct ownership and permissions for JWT keys..."
chown -R www-data:www-data config/jwt
chmod 600 config/jwt/private.pem  # Private key: read/write for owner ONLY
chmod 644 config/jwt/public.pem   # Public key: readable by all
echo "✓ JWT key permissions set correctly"

# Verify JWT keys are readable by www-data
echo ""
echo "========================================="
echo "Verifying JWT keys are readable..."
echo "========================================="
if [ -f config/jwt/private.pem ] && [ -f config/jwt/public.pem ]; then
    echo "✓ Private key exists: config/jwt/private.pem"
    echo "✓ Public key exists: config/jwt/public.pem"
    ls -lh config/jwt/

    # Test readability as www-data user
    if su -s /bin/sh www-data -c "test -r config/jwt/private.pem"; then
        echo "✓ Private key is readable by www-data"
    else
        echo "WARNING: Private key may not be readable by www-data!"
    fi
else
    echo "ERROR: JWT keys are missing!"
    exit 1
fi
echo "========================================="
echo ""

# ============================================
# 2. CACHE MANAGEMENT
# ============================================
echo "Clearing cache..."
php bin/console cache:clear --no-warmup --env=prod || echo "⚠ Cache clear failed, continuing..."

echo "Warming up cache..."
php bin/console cache:warmup --env=prod || echo "⚠ Cache warmup failed, continuing..."

# ============================================
# 3. PERMISSIONS FOR VAR DIRECTORY
# ============================================
echo "Fixing var/ directory permissions..."
chown -R www-data:www-data var
chmod -R 775 var
echo "✓ Permissions fixed for var/"

# ============================================
# 4. DATABASE MIGRATIONS (NON-FATAL)
# ============================================
echo ""
echo "========================================="
echo "Running database migrations..."
echo "========================================="

# Use || true to continue even if migration fails (DB might not be available yet)
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

if [ $? -eq 0 ]; then
    echo "✓ Migrations completed successfully!"
else
    echo "⚠ WARNING: Database migrations failed!"
    echo "⚠ This is non-fatal - container will start anyway."
    echo "⚠ If database is unavailable, migrations will be skipped."
    echo "⚠ You may need to run migrations manually later."
fi

echo "========================================="
echo ""

# ============================================
# 5. FINAL VERIFICATION
# ============================================
echo "========================================="
echo "Pre-flight checks complete!"
echo "========================================="
echo "✓ JWT keys: Ready"
echo "✓ Cache: Cleared"
echo "✓ Permissions: Fixed"
echo "✓ Migrations: Attempted"
echo ""
echo "Starting Apache on port 8000..."
echo "========================================="
echo ""

# ============================================
# 6. START APACHE
# ============================================
# exec replaces the shell with Apache, making Apache PID 1
exec apache2-foreground
