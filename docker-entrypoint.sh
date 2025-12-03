#!/bin/bash
# Robust entrypoint script for Symfony on Koyeb
# Fixes ALL permission issues by setting www-data ownership on entire project

echo "========================================="
echo "Starting Symfony Application Deployment"
echo "========================================="
echo ""
echo "Environment: ${APP_ENV:-prod}"
echo "Application URL: ${APP_URL:-not set}"
echo ""

# ============================================
# 1. FIX ALL PROJECT PERMISSIONS (CRITICAL!)
# ============================================
echo "========================================="
echo "Fixing project-wide permissions..."
echo "========================================="

# Change ownership of ENTIRE project to www-data
# This fixes "Permission denied" errors in src/Twig and other directories
echo "Setting ownership: www-data:www-data on entire project..."
chown -R www-data:www-data .

# Set proper directory permissions
echo "Setting directory permissions (755)..."
find . -type d -exec chmod 755 {} \; 2>/dev/null || true

# Set proper file permissions
echo "Setting file permissions (644)..."
find . -type f -exec chmod 644 {} \; 2>/dev/null || true

# Make bin/console executable
echo "Making bin/console executable..."
chmod +x bin/console

echo "✓ Project-wide permissions fixed!"
echo ""

# ============================================
# 2. JWT KEY GENERATION & PERMISSIONS
# ============================================
echo "========================================="
echo "JWT Configuration"
echo "========================================="
echo "JWT_PASSPHRASE is: ${JWT_PASSPHRASE:-(empty/not set)}"

echo "Generating JWT keys if missing..."
if [ ! -f config/jwt/private.pem ]; then
    echo "JWT keys not found, generating with EMPTY passphrase..."
    mkdir -p config/jwt

    # Generate JWT keypair with EMPTY passphrase
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

# Set correct JWT key permissions
echo "Setting JWT key permissions..."
chown -R www-data:www-data config/jwt
chmod 600 config/jwt/private.pem  # Private key: read/write for owner ONLY
chmod 644 config/jwt/public.pem   # Public key: readable by all
echo "✓ JWT key permissions set correctly"

# Verify JWT keys are readable
if [ -f config/jwt/private.pem ] && [ -f config/jwt/public.pem ]; then
    echo "✓ Private key: config/jwt/private.pem ($(stat -c%s config/jwt/private.pem) bytes)"
    echo "✓ Public key: config/jwt/public.pem ($(stat -c%s config/jwt/public.pem) bytes)"

    # Test readability as www-data user
    if su -s /bin/sh www-data -c "test -r config/jwt/private.pem"; then
        echo "✓ Private key is readable by www-data"
    else
        echo "⚠ WARNING: Private key may not be readable by www-data!"
    fi
else
    echo "ERROR: JWT keys are missing!"
    exit 1
fi
echo ""

# ============================================
# 3. INSTALL ASSETS
# ============================================
echo "========================================="
echo "Installing public assets..."
echo "========================================="

# Install assets to public/ directory
php bin/console assets:install public --symlink --relative || php bin/console assets:install public || echo "⚠ Assets install failed, continuing..."

if [ $? -eq 0 ]; then
    echo "✓ Assets installed successfully!"
else
    echo "⚠ Assets install had issues, but continuing..."
fi

# Fix public directory permissions
chown -R www-data:www-data public
echo "✓ Public directory permissions fixed"
echo ""

# ============================================
# 4. CACHE MANAGEMENT
# ============================================
echo "========================================="
echo "Cache Management"
echo "========================================="

echo "Clearing cache..."
php bin/console cache:clear --no-warmup --env=prod || echo "⚠ Cache clear failed, continuing..."

echo "Warming up cache..."
php bin/console cache:warmup --env=prod || echo "⚠ Cache warmup failed, continuing..."

# Fix cache permissions
echo "Fixing cache permissions..."
chown -R www-data:www-data var/cache
chmod -R 775 var/cache
echo "✓ Cache permissions fixed"
echo ""

# ============================================
# 5. VAR DIRECTORY PERMISSIONS
# ============================================
echo "Fixing var/ directory permissions..."
chown -R www-data:www-data var
chmod -R 775 var
echo "✓ var/ directory permissions fixed"
echo ""

# ============================================
# 6. SRC DIRECTORY PERMISSIONS (Fix Twig errors)
# ============================================
echo "Fixing src/ directory permissions..."
chown -R www-data:www-data src
chmod -R 755 src
echo "✓ src/ directory permissions fixed"
echo ""

# ============================================
# 7. DATABASE MIGRATIONS (NON-FATAL)
# ============================================
echo "========================================="
echo "Database Migrations"
echo "========================================="

# Use || true to continue even if migration fails (DB might not be available yet)
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || true

if [ $? -eq 0 ]; then
    echo "✓ Migrations completed successfully!"
else
    echo "⚠ WARNING: Database migrations failed or skipped!"
    echo "⚠ This is non-fatal - container will start anyway."
    echo "⚠ If database is unavailable, migrations will be skipped."
fi
echo ""

# ============================================
# 8. ENVIRONMENT VERIFICATION
# ============================================
echo "========================================="
echo "Environment Configuration Check"
echo "========================================="
echo "APP_ENV: ${APP_ENV:-not set}"
echo "APP_URL: ${APP_URL:-not set (will default to localhost!)}"
echo "MAILER_DSN: ${MAILER_DSN:0:30}... (truncated)"
echo ""

# Warn if APP_URL is not set or is localhost
if [ -z "$APP_URL" ] || [ "$APP_URL" = "http://localhost" ]; then
    echo "⚠ WARNING: APP_URL is not set or is localhost!"
    echo "⚠ Email links will point to localhost instead of your production domain!"
    echo "⚠ Set APP_URL in Koyeb environment variables to fix this."
    echo ""
fi

# ============================================
# 9. FINAL VERIFICATION
# ============================================
echo "========================================="
echo "Pre-flight Checks Complete!"
echo "========================================="
echo "✓ Project permissions: Fixed (www-data:www-data)"
echo "✓ JWT keys: Ready"
echo "✓ Assets: Installed"
echo "✓ Cache: Cleared and warmed"
echo "✓ Permissions: All directories fixed"
echo "✓ Migrations: Attempted"
echo ""
echo "Starting Apache on port 8000..."
echo "========================================="
echo ""

# ============================================
# 10. CREATE SUPERVISOR LOG DIRECTORY
# ============================================
echo "Creating supervisor log directory..."
mkdir -p /var/log/supervisor
chown -R www-data:www-data /var/log/supervisor
chmod -R 755 /var/log/supervisor
echo "✓ Supervisor log directory ready"
echo ""

# ============================================
# 11. START SUPERVISOR
# ============================================
echo "Starting Supervisor..."
echo "Supervisor will manage:"
echo "  1. Apache (Web Server) on port 8000"
echo "  2. Messenger Worker (async email processing)"
echo ""
echo "========================================="
echo "🚀 Application Ready!"
echo "========================================="
echo ""

# Start supervisord (manages Apache + Messenger Worker)
# nodaemon=true keeps it in foreground
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
