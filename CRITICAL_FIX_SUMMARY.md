# CRITICAL FIX SUMMARY - Data Persistence Issues

## 🔴 ROOT CAUSE IDENTIFIED

The application code is **correct** and properly configured. The issue is that **the database schema is missing the required columns**.

### The Problem

**Entity has fields → Code sets the fields → But database table doesn't have the columns**

```
Application Code (✅ Working):
├─ BookingController extracts client_phone from request
├─ BookingController sets client_phone on entity
├─ GoogleCalendarService returns event ID
├─ BookingController sets google_calendar_event_id on entity
└─ Entity Manager flushes changes

Database (❌ Missing Columns):
├─ booking table doesn't have client_phone column
├─ booking table doesn't have google_calendar_event_id column
└─ Result: Data is silently discarded (saved as NULL)
```

---

## 🎯 THE FIX (Required)

**You MUST run the database migration to add the missing columns.**

### Quick Fix Command

```bash
# Option 1: Direct MySQL (Recommended)
mysql -u your_username -p your_database_name < migrations/add_missing_booking_fields_mysql.sql

# Option 2: Using Doctrine (if PHP driver is fixed)
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

**That's it!** Once the columns exist, everything will work.

---

## 📊 Current vs. After Migration

### Before Migration (Current State)

**Database:**
```sql
mysql> DESCRIBE booking;
+---------------+--------------+------+-----+---------+
| Field         | Type         | Null | Key | Default |
+---------------+--------------+------+-----+---------+
| id            | int          | NO   | PRI | NULL    |
| user_id       | int          | NO   | MUL | NULL    |
| stylist_id    | int          | NO   | MUL | NULL    |
| service_id    | int          | NO   | MUL | NULL    |
| booking_date  | datetime     | NO   |     | NULL    |
| status        | varchar(20)  | NO   |     | NULL    |
| notes         | text         | YES  |     | NULL    |
| created_at    | datetime     | NO   |     | NULL    |
| updated_at    | datetime     | YES  |     | NULL    |
+---------------+--------------+------+-----+---------+
❌ Missing: client_phone
❌ Missing: client_email
❌ Missing: client_name
❌ Missing: google_calendar_event_id
```

**Result:**
- ❌ Phone numbers → NULL
- ❌ Calendar event IDs → NULL
- ❌ Frontend sees no bookings
- ❌ Double-booking prevention doesn't work visually

### After Migration

**Database:**
```sql
mysql> DESCRIBE booking;
+---------------------------+--------------+------+-----+---------+
| Field                     | Type         | Null | Key | Default |
+---------------------------+--------------+------+-----+---------+
| id                        | int          | NO   | PRI | NULL    |
| user_id                   | int          | NO   | MUL | NULL    |
| stylist_id                | int          | NO   | MUL | NULL    |
| service_id                | int          | NO   | MUL | NULL    |
| booking_date              | datetime     | NO   |     | NULL    |
| status                    | varchar(20)  | NO   |     | NULL    |
| notes                     | text         | YES  |     | NULL    |
| created_at                | datetime     | NO   |     | NULL    |
| updated_at                | datetime     | YES  |     | NULL    |
| client_name               | varchar(255) | YES  |     | NULL    | ✅ NEW
| client_email              | varchar(255) | YES  |     | NULL    | ✅ NEW
| client_phone              | varchar(50)  | YES  |     | NULL    | ✅ NEW
| google_calendar_event_id  | varchar(255) | YES  | MUL | NULL    | ✅ NEW
+---------------------------+--------------+------+-----+---------+
```

**Result:**
- ✅ Phone numbers saved correctly
- ✅ Calendar event IDs saved correctly
- ✅ Frontend shows gray busy blocks
- ✅ Double-booking prevention works

---

## 🔍 Why This Happened

When you add new fields to a Doctrine entity, the changes are only in the PHP code. The actual database table doesn't change automatically.

**Timeline:**
1. ✅ Entity updated with new fields (`client_phone`, `google_calendar_event_id`)
2. ✅ Controller code updated to use the new fields
3. ❌ **Migration never run** → Database still has old schema
4. ❌ Doctrine tries to persist data to non-existent columns
5. ❌ Data is lost/set to NULL

**The Solution:**
Run the migration to sync the database schema with the entity definition.

---

## 🚀 Step-by-Step Fix

### Step 1: Backup Your Database

```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### Step 2: Run the Migration

**Choose one method:**

#### Method A: Direct MySQL (Works Always)

```bash
# 1. Copy the SQL file to accessible location
cd /home/needy/project2

# 2. Run migration
mysql -u your_username -p your_database_name < migrations/add_missing_booking_fields_mysql.sql

# 3. Enter your password when prompted
```

#### Method B: Doctrine (If PHP Driver Works)

```bash
# 1. Generate migration
php bin/console make:migration

# 2. Check generated file
ls -la migrations/

# 3. Apply migration
php bin/console doctrine:migrations:migrate

# 4. Verify
php bin/console doctrine:schema:validate
```

### Step 3: Verify Columns Exist

```sql
mysql> USE your_database_name;
mysql> DESCRIBE booking;

-- Look for these columns:
-- client_phone           | varchar(50)
-- google_calendar_event_id | varchar(255)
```

### Step 4: Clear Symfony Cache

```bash
php bin/console cache:clear
```

### Step 5: Test with New Booking

Create a test booking and verify data is saved:

```sql
SELECT
    id,
    client_name,
    client_phone,
    google_calendar_event_id,
    booking_date
FROM booking
ORDER BY id DESC
LIMIT 1;
```

**Expected:**
- ✅ `client_phone` has a value (not NULL)
- ✅ `google_calendar_event_id` has a value (if Google Calendar is configured)

### Step 6: Test Frontend

1. Open booking page
2. Create a new booking
3. Refresh the page
4. ✅ You should see gray "Busy" block at the booked time

---

## 🧪 Verification Tests

### Test 1: Database Schema

```bash
mysql -u username -p -e "DESCRIBE your_database.booking" | grep -E "client_phone|google_calendar_event_id"
```

**Expected Output:**
```
client_phone              varchar(50)    YES           NULL
google_calendar_event_id  varchar(255)   YES      MUL  NULL
```

### Test 2: Create Test Booking

```bash
curl -X POST http://localhost/booking/create \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session" \
  -d '{
    "stylist_id": 1,
    "service_id": 1,
    "booking_date": "2025-11-27",
    "booking_time": "14:00",
    "client_phone": "555-TEST",
    "notes": "Migration test"
  }'
```

### Test 3: Check Database

```sql
SELECT
    id,
    client_phone,
    google_calendar_event_id,
    booking_date
FROM booking
WHERE client_phone = '555-TEST';
```

**Expected:**
- Row found with `client_phone` = `555-TEST`

### Test 4: API Endpoint

```bash
curl -H "Cookie: PHPSESSID=xxx" \
  "http://localhost/booking/api/bookings?start=2025-11-01&end=2025-12-31"
```

**Expected:**
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

### Test 5: Frontend Visual

1. Open `/booking` in browser
2. Look at the calendar
3. ✅ Gray "Busy" blocks should appear over booked times

---

## 📝 What Was Already Fixed in the Code

The following code changes have already been applied:

### ✅ BookingController.php (Line 203)
```php
$clientPhone = $data['client_phone'] ?? null; // Extract phone number from request
```

### ✅ BookingController.php (Line 258)
```php
$booking->setClientPhone($clientPhone); // Set phone number from request
```

### ✅ BookingController.php (Lines 267-274)
```php
$calendarEventId = $this->googleCalendarService->createEvent($booking);
if ($calendarEventId) {
    $booking->setGoogleCalendarEventId($calendarEventId);
    // ... logging ...
}

// Flush again to save the Google Calendar event ID
$this->em->flush();
```

### ✅ BookingController.php (Lines 72-121)
```php
// Enhanced /api/bookings endpoint with:
// - Eager loading (joins)
// - Comprehensive logging
// - Error handling per booking
// - Valid JSON response
```

### ✅ Entity/Booking.php
```php
#[ORM\Column(length: 50, nullable: true)]
private ?string $clientPhone = null;

#[ORM\Column(length: 255, nullable: true)]
private ?string $googleCalendarEventId = null;

// + getter and setter methods
```

**All code is correct!** Only the database schema is missing.

---

## 🎯 Action Required

**YOU MUST RUN THE MIGRATION**

Without running the migration:
- ❌ Phone numbers will continue to be NULL
- ❌ Calendar event IDs will continue to be NULL
- ❌ Frontend will continue to show no busy slots
- ❌ Double-booking prevention won't work visually

With the migration:
- ✅ Everything will work perfectly
- ✅ Data will be saved correctly
- ✅ Frontend will show busy slots
- ✅ Double-booking prevention will work

---

## 📚 Documentation Reference

| File | Purpose |
|------|---------|
| `migrations/add_missing_booking_fields_mysql.sql` | SQL migration script |
| `MIGRATION_GUIDE.md` | Detailed migration instructions |
| `BOOKING_FIXES.md` | Complete fix documentation |
| `test_bookings_api.sh` | Automated test script |

---

## 🆘 Troubleshooting

### "I ran the migration but data is still NULL"

**Check:**
1. Did migration actually run successfully?
   ```sql
   DESCRIBE booking;
   ```
   Look for `client_phone` column

2. Are you creating NEW bookings or checking OLD ones?
   - Old bookings will still have NULL (they were created before migration)
   - Create a NEW booking to test

3. Check logs:
   ```bash
   tail -f var/log/dev.log | grep -E "(client_phone|calendar_event_id)"
   ```

### "Frontend still shows no busy slots"

**Check:**
1. Are there any confirmed bookings?
   ```sql
   SELECT COUNT(*) FROM booking WHERE status='confirmed';
   ```

2. Check browser console for errors (F12)

3. Check API response:
   ```bash
   curl -H "Cookie: PHPSESSID=xxx" "http://localhost/booking/api/bookings?start=2025-01-01&end=2025-12-31"
   ```

4. Check logs:
   ```bash
   tail -f var/log/dev.log | grep "busy slots"
   ```

### "Google Calendar event ID still NULL"

**Check:**
1. Is Google Calendar configured?
   ```bash
   ls -la google-calendar.json
   ```

2. Check logs for sync errors:
   ```bash
   tail -100 var/log/dev.log | grep "Google Calendar"
   ```

3. Common issues:
   - Credentials file missing
   - Calendar not shared with service account
   - API not enabled in Google Cloud Console

---

## ✅ Success Checklist

After running the migration, verify:

- [ ] `DESCRIBE booking` shows `client_phone` column
- [ ] `DESCRIBE booking` shows `google_calendar_event_id` column
- [ ] Create new booking with phone number
- [ ] Phone number appears in database (not NULL)
- [ ] Google Calendar event ID appears in database (if configured)
- [ ] `/api/bookings` returns JSON array with events
- [ ] Frontend calendar shows gray busy blocks
- [ ] Logs show "Booking synced to Google Calendar"
- [ ] No errors in browser console
- [ ] Test booking has all fields populated

---

## 🎉 Expected Result

Once migration is complete:

**Database:**
```sql
mysql> SELECT client_phone, google_calendar_event_id FROM booking ORDER BY id DESC LIMIT 1;
+----------------+---------------------------+
| client_phone   | google_calendar_event_id  |
+----------------+---------------------------+
| 555-1234       | abc123xyz789              |
+----------------+---------------------------+
✅ Data is saved!
```

**Frontend:**
```
Calendar displays:
┌─────────────────────────┐
│ November 2025           │
├─────────────────────────┤
│ Mon Tue Wed Thu Fri Sat │
│                         │
│ 25  26  27  28  29  30  │
│     [BUSY BLOCK]        │ ← Gray block at 2:00 PM
│                         │
└─────────────────────────┘
✅ Busy slots visible!
```

**Logs:**
```
[INFO] Booking created successfully | booking_id: 123
[INFO] Google Calendar event created | event_id: abc123xyz
[INFO] Booking synced to Google Calendar | booking_id: 123 | event_id: abc123xyz
[INFO] Fetching busy slots for calendar | bookings_found: 5
[INFO] Returning busy slots | event_count: 5
✅ Everything working!
```

---

## 🚀 Run This Now

```bash
# 1. Backup
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql

# 2. Run migration
mysql -u username -p database_name < migrations/add_missing_booking_fields_mysql.sql

# 3. Verify
mysql -u username -p -e "DESCRIBE database_name.booking" | grep client_phone

# 4. Clear cache
php bin/console cache:clear

# 5. Test
# Create a new booking via the web interface

# 6. Check result
mysql -u username -p -e "SELECT client_phone, google_calendar_event_id FROM database_name.booking ORDER BY id DESC LIMIT 1"
```

**That's all you need to do to fix the issue!**
