# Quick Command Reference

## Installation Commands

### Install Google API Client (if needed)
```bash
composer require google/apiclient --ignore-platform-req=ext-redis
```

**Status:** ✅ Already installed (v2.18.4)

---

## Database Setup

### Create Migration
```bash
php bin/console make:migration
```

### Run Migrations
```bash
php bin/console doctrine:migrations:migrate
```

### Load Fixtures (Admin + Services)
```bash
php bin/console doctrine:fixtures:load
```

**Note:** This will prompt for confirmation and clear existing data.

---

## Cache Management

### Clear Cache
```bash
php bin/console cache:clear
```

### Warm Up Cache
```bash
php bin/console cache:warmup
```

---

## Testing & Debugging

### Check Database Schema
```bash
php bin/console doctrine:schema:validate
```

### View Routes
```bash
php bin/console debug:router | grep booking
```

### Test Google Calendar Configuration
```bash
# Check if credentials file exists and is readable
ls -la google-calendar.json

# View service account email
cat google-calendar.json | grep client_email
```

### View Logs in Real-Time
```bash
# All logs
tail -f var/log/dev.log

# Google Calendar only
tail -f var/log/dev.log | grep "Google Calendar"

# Booking-related only
tail -f var/log/dev.log | grep -i booking
```

---

## File Permissions

### Secure Google Calendar Credentials
```bash
chmod 600 google-calendar.json
chown www-data:www-data google-calendar.json
```

### Fix General Permissions (if needed)
```bash
chmod -R 755 src config templates
chmod -R 775 var
chown -R www-data:www-data var
```

---

## Quick Tests

### Test API Endpoints

**Get Busy Slots:**
```bash
# Replace with actual session cookie
curl -H "Cookie: PHPSESSID=abc123..." \
  "http://localhost/booking/api/bookings?start=2025-11-01&end=2025-11-30"
```

**Get Available Slots:**
```bash
curl -H "Cookie: PHPSESSID=abc123..." \
  "http://localhost/booking/api/available-slots?stylist_id=1&service_id=1&date=2025-11-26"
```

---

## Verification Checklist

### 1. Check Google Calendar File
```bash
✓ File exists: ls -la google-calendar.json
✓ Has content: wc -l google-calendar.json
✓ Valid JSON: cat google-calendar.json | jq .
```

### 2. Check Service Configuration
```bash
✓ Config correct: grep google.calendar config/services.yaml
✓ Path resolves: php -r "echo realpath('google-calendar.json');"
```

### 3. Check Database
```bash
✓ Can connect: php bin/console dbal:run-sql "SELECT 1"
✓ Admin exists: php bin/console dbal:run-sql "SELECT * FROM user WHERE email='asem4o@gmail.com'"
✓ Stylist exists: php bin/console dbal:run-sql "SELECT * FROM stylist WHERE name='Asen'"
✓ Services exist: php bin/console dbal:run-sql "SELECT COUNT(*) FROM service"
```

---

## Common Issues & Fixes

### Issue: Database Driver Error
```bash
# Install PDO MySQL driver
sudo apt-get install php8.3-mysql
sudo systemctl restart php8.3-fpm  # or apache2
```

### Issue: Cache Errors
```bash
rm -rf var/cache/*
php bin/console cache:clear
chmod -R 775 var/cache
```

### Issue: Autoload Errors
```bash
composer dump-autoload
composer install --optimize-autoloader
```

---

## Production Deployment

### 1. Set Environment to Production
```bash
# Edit .env.local
APP_ENV=prod
APP_DEBUG=0
```

### 2. Clear & Warm Cache
```bash
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
```

### 3. Run Migrations
```bash
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
```

### 4. Load Fixtures (first time only)
```bash
php bin/console doctrine:fixtures:load --env=prod
```

---

## Monitoring

### Real-time Booking Activity
```bash
watch -n 2 'php bin/console dbal:run-sql "SELECT COUNT(*) as bookings FROM booking WHERE DATE(created_at) = CURDATE()"'
```

### Check Google Calendar Sync Success Rate
```bash
grep "Google Calendar" var/log/prod.log | grep -c "synced"
grep "Google Calendar" var/log/prod.log | grep -c "Failed"
```

### Monitor System Health
```bash
# Check PHP processes
ps aux | grep php-fpm

# Check error logs
tail -20 var/log/prod.log
```

---

## Maintenance

### Rotate Logs
```bash
# Compress old logs
gzip var/log/dev.log.1
gzip var/log/prod.log.1

# Clear old logs (older than 30 days)
find var/log -name "*.log.*" -mtime +30 -delete
```

### Backup Database
```bash
php bin/console doctrine:database:dump > backup_$(date +%Y%m%d).sql
```

### Update Dependencies
```bash
composer update
php bin/console cache:clear
```

---

## Development

### Create New Entity
```bash
php bin/console make:entity
```

### Create New Controller
```bash
php bin/console make:controller
```

### Create New Service
```bash
php bin/console make:service
```

---

## Quick Start (First Time Setup)

```bash
# 1. Install dependencies (if needed)
composer require google/apiclient --ignore-platform-req=ext-redis

# 2. Run database migrations
php bin/console doctrine:migrations:migrate

# 3. Load fixtures
php bin/console doctrine:fixtures:load

# 4. Clear cache
php bin/console cache:clear

# 5. Secure credentials
chmod 600 google-calendar.json
chown www-data:www-data google-calendar.json

# 6. Test
tail -f var/log/dev.log | grep "Google Calendar"
```

---

## Emergency Recovery

### Reset Admin Password
```bash
php bin/console security:hash-password
# Then update in database:
# UPDATE user SET password='<hashed>' WHERE email='asem4o@gmail.com'
```

### Clear All Bookings (CAUTION)
```bash
php bin/console dbal:run-sql "DELETE FROM booking"
php bin/console dbal:run-sql "ALTER TABLE booking AUTO_INCREMENT = 1"
```

### Reload All Data
```bash
php bin/console doctrine:schema:drop --force
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load
```

---

**For detailed instructions, see:**
- `GOOGLE_CALENDAR_SETUP.md` - Google Calendar configuration
- `FINAL_IMPLEMENTATION.md` - Complete implementation details
- `QUICK_START.md` - Getting started guide
