# Database Migration Guide - Critical Fields

## Problem

The application code has been updated to save `client_phone` and `google_calendar_event_id`, but these columns don't exist in the database yet. This causes data to be lost/not persisted.

## Solution

Run the SQL migration to add the missing columns to the database.

---

## Quick Fix (5 minutes)

### Option 1: Using Doctrine Migrations (Recommended)

**If you have PHP/database driver working:**

```bash
# Generate migration from entity changes
php bin/console make:migration

# Review the generated migration file in migrations/
# Then apply it:
php bin/console doctrine:migrations:migrate

# Verify
php bin/console doctrine:schema:validate
```

### Option 2: Manual SQL Execution

**If database driver is broken or you prefer manual SQL:**

#### Step 1: Choose your SQL file

**For modern MySQL/MariaDB (5.7+):**
Use: `migrations/add_missing_booking_fields.sql`

**For older MySQL or compatibility:**
Use: `migrations/add_missing_booking_fields_mysql.sql`

#### Step 2: Connect to your database

```bash
# Using mysql command line
mysql -u your_username -p your_database_name < migrations/add_missing_booking_fields_mysql.sql

# Or using MySQL Workbench / phpMyAdmin
# Copy and paste the SQL from the file and execute
```

#### Step 3: Verify the migration

```sql
-- Check booking table has new columns
DESCRIBE booking;

-- Should show:
-- client_name          | varchar(255) | YES
-- client_email         | varchar(255) | YES
-- client_phone         | varchar(50)  | YES
-- google_calendar_event_id | varchar(255) | YES

-- Check stylist table has user relationship
DESCRIBE stylist;

-- Should show:
-- user_id | int | YES | MUL
```

---

## Detailed Migration Steps

### What the Migration Does

The migration adds the following columns:

**To `booking` table:**
1. `client_name` (VARCHAR 255) - Client's full name
2. `client_email` (VARCHAR 255) - Client's email address
3. `client_phone` (VARCHAR 50) - Client's phone number
4. `google_calendar_event_id` (VARCHAR 255) - Google Calendar event ID for tracking

**To `stylist` table:**
1. `user_id` (INT) - Foreign key linking stylist to user account

**Indexes created:**
1. `idx_booking_calendar_event` - Faster lookups by calendar event ID
2. `idx_stylist_user` - Faster stylist-user relationship queries

---

## Troubleshooting

### Error: "Duplicate column name 'client_phone'"

**Cause:** Column already exists in the database

**Fix:** The column is already there, no action needed. Skip that ALTER TABLE statement.

**Check which columns exist:**
```sql
DESCRIBE booking;
```

If `client_phone` is already listed, remove that line from the SQL script.

---

### Error: "Can't DROP 'idx_booking_calendar_event'; check that column/key exists"

**Cause:** Index doesn't exist yet (normal on first run)

**Fix:** Ignore this error, or use the version with `IF NOT EXISTS` (MySQL 5.7+)

---

### Error: "Cannot add foreign key constraint"

**Cause:**
- Referenced `user` table doesn't exist
- Or `user.id` doesn't match `stylist.user_id` type

**Fix:**
```sql
-- Check user table exists
SHOW TABLES LIKE 'user';

-- Check user.id column type
DESCRIBE user;

-- Make sure stylist.user_id matches user.id type
-- If user.id is BIGINT, change stylist.user_id to BIGINT too
```

---

### Error: "could not find driver"

**Cause:** PHP PDO MySQL driver not installed

**Fix:**

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install php8.3-mysql
sudo systemctl restart php8.3-fpm  # or apache2
```

**Alternative:** Run SQL directly using MySQL client (see Option 2 above)

---

## Verification After Migration

### Test 1: Create a booking with phone

```bash
# Using curl (replace with your session cookie)
curl -X POST http://localhost/booking/create \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session" \
  -d '{
    "stylist_id": 1,
    "service_id": 1,
    "booking_date": "2025-11-27",
    "booking_time": "14:00",
    "client_phone": "555-1234",
    "notes": "Test booking"
  }'
```

### Test 2: Check database

```sql
-- Check the latest booking
SELECT
    id,
    client_name,
    client_email,
    client_phone,
    google_calendar_event_id,
    booking_date,
    status
FROM booking
ORDER BY id DESC
LIMIT 1;
```

**Expected Results:**
- ✅ `client_phone` should have value `555-1234`
- ✅ `client_name` should have user's full name
- ✅ `client_email` should have user's email
- ✅ `google_calendar_event_id` should have a value (if Google Calendar is configured)

### Test 3: Check logs

```bash
tail -f var/log/dev.log | grep -E "(client_phone|Google Calendar)"
```

**Expected Log Messages:**
```
[INFO] Booking created successfully | booking_id: 123
[INFO] Google Calendar event created | event_id: abc123xyz | booking_id: 123
[INFO] Booking synced to Google Calendar | booking_id: 123 | event_id: abc123xyz
```

---

## After Migration: Test the /api/bookings Endpoint

Once the columns exist and data is being saved:

### Test 1: Check endpoint returns data

```bash
# Replace with your session cookie
curl -X GET "http://localhost/booking/api/bookings?start=2025-11-01&end=2025-12-31" \
  -H "Cookie: PHPSESSID=your_session" \
  -H "Accept: application/json"
```

**Expected Response:**
```json
[
  {
    "id": "123",
    "title": "Busy",
    "start": "2025-11-27T14:00:00",
    "end": "2025-11-27T14:45:00",
    "backgroundColor": "#6c757d",
    "borderColor": "#5a6268",
    "display": "background",
    "editable": false,
    "extendedProps": {
      "type": "busy",
      "stylistId": 1
    }
  }
]
```

### Test 2: Check logs

```bash
tail -f var/log/dev.log | grep "busy slots"
```

**Expected:**
```
[INFO] Fetching busy slots for calendar | start: 2025-11-01 | end: 2025-12-31 | bookings_found: 5
[INFO] Returning busy slots | event_count: 5
```

### Test 3: Visual verification

1. Open the booking page in browser
2. Look for gray "Busy" blocks on the calendar
3. Try clicking a busy time slot (should be blocked)
4. Open browser DevTools → Network tab
5. Look for request to `/booking/api/bookings`
6. Check response contains your bookings

---

## Complete Migration Workflow

```bash
# 1. Backup database (important!)
mysqldump -u username -p database_name > backup_before_migration.sql

# 2. Run the migration
mysql -u username -p database_name < migrations/add_missing_booking_fields_mysql.sql

# 3. Clear Symfony cache
php bin/console cache:clear

# 4. Verify schema
php bin/console doctrine:schema:validate

# 5. Create test booking
# (Use curl command above or via web interface)

# 6. Check database
mysql -u username -p database_name

# In MySQL:
SELECT * FROM booking ORDER BY id DESC LIMIT 1;

# 7. Check logs
tail -f var/log/dev.log

# 8. Test API endpoint
curl -H "Cookie: PHPSESSID=xxx" "http://localhost/booking/api/bookings?start=2025-11-01&end=2025-12-31"
```

---

## Summary

**Before Migration:**
- ❌ `client_phone` → NULL (not saved)
- ❌ `google_calendar_event_id` → NULL (not saved)
- ❌ Frontend calendar shows no busy slots
- ❌ `/api/bookings` returns empty or fails

**After Migration:**
- ✅ `client_phone` → Saved correctly
- ✅ `google_calendar_event_id` → Saved when calendar sync succeeds
- ✅ Frontend calendar shows gray busy blocks
- ✅ `/api/bookings` returns valid JSON array

---

## Quick Reference Commands

```bash
# Check if columns exist
mysql -u user -p -e "DESCRIBE database.booking" | grep client_phone

# Run migration
mysql -u user -p database < migrations/add_missing_booking_fields_mysql.sql

# Verify
mysql -u user -p -e "SELECT client_phone, google_calendar_event_id FROM database.booking LIMIT 1"

# Check logs
tail -100 var/log/dev.log | grep -E "(client_phone|calendar_event_id|busy slots)"

# Clear cache
php bin/console cache:clear

# Test API
curl -H "Cookie: PHPSESSID=xxx" "http://localhost/booking/api/bookings?start=2025-01-01&end=2025-12-31"
```

---

## Need Help?

If you encounter issues:

1. **Check the logs:**
   ```bash
   tail -100 var/log/dev.log
   ```

2. **Verify database connection:**
   ```bash
   mysql -u username -p -e "SELECT 1"
   ```

3. **Check table structure:**
   ```sql
   SHOW CREATE TABLE booking;
   ```

4. **Test manually:**
   - Create a booking via web interface
   - Check database immediately after
   - Look for NULL values

5. **See detailed fix documentation:**
   - `BOOKING_FIXES.md` - Complete fix documentation
   - `test_bookings_api.sh` - Automated test script

---

**Once migration is complete, all data persistence issues should be resolved!**
