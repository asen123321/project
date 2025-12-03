# Google Calendar Debug Guide - Worker Logs

## 🔍 Verbose Logging Added to BookingConfirmationEmailHandler

The `BookingConfirmationEmailHandler` has been enhanced with comprehensive logging to help debug Google Calendar synchronization issues.

---

## 📊 What to Look For in Worker Logs

When you run the messenger worker, you'll now see detailed logs showing exactly what's happening with Google Calendar:

```bash
php bin/console messenger:consume async -vv
```

---

## 🧪 Expected Log Output

### Scenario 1: Booking Created as PENDING (Current Setup)

When a user creates a booking, you'll see:

```
[2025-11-26 21:00:10] app.INFO: Booking confirmation email sent
  booking_id: 42
  user_email: client@example.com

[2025-11-26 21:00:10] app.INFO: Starting Google Calendar sync check...
  booking_id: 42
  booking_status: "pending"
  has_calendar_event_id: "no"

[2025-11-26 21:00:10] app.INFO: Booking status is NOT CONFIRMED - skipping Google Calendar sync
  booking_id: 42
  current_status: "pending"
  note: "Only CONFIRMED bookings are synced to Google Calendar. Admin must confirm this booking first."

[2025-11-26 21:00:10] app.INFO: Google Calendar sync process completed
  booking_id: 42
```

**This is EXPECTED** - Bookings start as PENDING and only sync when admin confirms them.

---

### Scenario 2: Google Calendar NOT Configured

If Google Calendar credentials are missing:

```
[2025-11-26 21:00:10] app.INFO: Starting Google Calendar sync check...
  booking_id: 42
  booking_status: "confirmed"
  has_calendar_event_id: "no"

[2025-11-26 21:00:10] app.INFO: Booking status is CONFIRMED - proceeding with Google Calendar sync
  booking_id: 42

[2025-11-26 21:00:10] app.INFO: Checking if Google Calendar is configured...
  booking_id: 42

[2025-11-26 21:00:10] app.WARNING: ❌ Google Calendar is NOT CONFIGURED
  booking_id: 42
  reason: "Service account credentials file not found or not configured"
  check_env_variable: "GOOGLE_CALENDAR_CREDENTIALS_PATH"
```

**Fix:** Configure Google Calendar service account credentials in `.env`

---

### Scenario 3: Google Calendar IS Configured and Working

Successful sync:

```
[2025-11-26 21:00:10] app.INFO: Starting Google Calendar sync check...
  booking_id: 42
  booking_status: "confirmed"
  has_calendar_event_id: "no"

[2025-11-26 21:00:10] app.INFO: Booking status is CONFIRMED - proceeding with Google Calendar sync
  booking_id: 42

[2025-11-26 21:00:10] app.INFO: Checking if Google Calendar is configured...
  booking_id: 42

[2025-11-26 21:00:10] app.INFO: Google Calendar IS CONFIGURED - attempting to create event...
  booking_id: 42
  client_name: "Sarah Johnson"
  client_email: "sarah@example.com"
  service: "Women's Haircut"
  stylist: "Asen"
  date: "2025-11-28 14:00:00"

[2025-11-26 21:00:11] app.INFO: ✅ SUCCESS: Google Calendar event created!
  booking_id: 42
  event_id: "evt_abc123xyz456"
  calendar_event_url: "https://calendar.google.com/calendar/event?eid=ZXZ0X2FiYzEyM3h5ejQ1Ng=="

[2025-11-26 21:00:11] app.INFO: Google Calendar event ID saved to booking
  booking_id: 42
  event_id: "evt_abc123xyz456"

[2025-11-26 21:00:11] app.INFO: Google Calendar sync process completed
  booking_id: 42
```

**Perfect!** Event created successfully.

---

### Scenario 4: API Error (With Full Stack Trace)

If there's an API error:

```
[2025-11-26 21:00:10] app.INFO: Google Calendar IS CONFIGURED - attempting to create event...
  booking_id: 42
  client_name: "Sarah Johnson"
  client_email: "sarah@example.com"
  service: "Women's Haircut"
  stylist: "Asen"
  date: "2025-11-28 14:00:00"

[2025-11-26 21:00:11] app.ERROR: ❌ EXCEPTION: Failed to sync booking to Google Calendar
  booking_id: 42
  error_message: "Invalid credentials"
  error_code: 401
  error_class: "Google\Service\Exception"
  error_file: "/vendor/google/apiclient/src/Service/Calendar.php"
  error_line: 123
  stack_trace: """
    #0 /vendor/google/apiclient/src/Service/Calendar.php(123): Google\Client->authenticate()
    #1 /home/needy/project2/src/Service/GoogleCalendarService.php(104): Google\Service\Calendar->insert()
    #2 /home/needy/project2/src/MessageHandler/BookingConfirmationEmailHandler.php(126): App\Service\GoogleCalendarService->createEvent()
    #3 ...
  """
```

**This shows the EXACT error** - credentials are invalid.

---

### Scenario 5: API Returned NULL

If API call succeeds but returns null:

```
[2025-11-26 21:00:10] app.INFO: Google Calendar IS CONFIGURED - attempting to create event...
  booking_id: 42
  ...

[2025-11-26 21:00:11] app.WARNING: ⚠️ WARNING: Google Calendar createEvent returned NULL
  booking_id: 42
  possible_reasons: [
    "API returned null",
    "Exception was caught and returned null",
    "Calendar service not properly initialized"
  ]
```

**Check:** GoogleCalendarService.php - look for caught exceptions that return null

---

## 🔧 Understanding the Current System Flow

### Current Setup (After Our Changes)

```
User Creates Booking
  └─► Status: PENDING
      BookingConfirmationEmailHandler runs:
        ├─► Email sent ✅
        └─► Google Calendar: SKIPPED (status is PENDING)

      ↓

Admin Reviews Booking in Dashboard
  └─► Admin clicks "✓ Confirm"
      AdminController changes status to CONFIRMED:
        ├─► Status: PENDING → CONFIRMED
        ├─► Google Calendar: SYNCED ✅
        └─► Email: "Booking Confirmed" sent ✅
```

**Why Google Calendar shows "zero activity":**
- Bookings start as PENDING (not CONFIRMED)
- BookingConfirmationEmailHandler sees PENDING and skips sync
- You'll see this log: "Booking status is NOT CONFIRMED - skipping Google Calendar sync"

**This is the INTENDED behavior** based on the previous requirement to only sync CONFIRMED bookings.

---

## 🛠️ How to Test Different Scenarios

### Test 1: See the "PENDING" Skip Message

```bash
# Terminal 1: Start worker
php bin/console messenger:consume async -vv

# Terminal 2: Create a booking via web UI
# (As a regular user, create a booking)

# Expected in Terminal 1:
[app.INFO] Booking confirmation email sent
[app.INFO] Starting Google Calendar sync check...
[app.INFO] Booking status is NOT CONFIRMED - skipping Google Calendar sync
  current_status: "pending"
  note: "Only CONFIRMED bookings are synced to Google Calendar..."
```

---

### Test 2: Test Google Calendar Configuration Check

```bash
# Check if credentials file exists
ls -la /path/to/service-account-key.json

# Check .env configuration
grep GOOGLE_CALENDAR .env

# Expected output in .env:
GOOGLE_CALENDAR_CREDENTIALS_PATH=/path/to/service-account-key.json
GOOGLE_CALENDAR_ID=primary

# Start worker and create booking
# If credentials are missing, you'll see:
[app.WARNING] ❌ Google Calendar is NOT CONFIGURED
  reason: "Service account credentials file not found or not configured"
  check_env_variable: "GOOGLE_CALENDAR_CREDENTIALS_PATH"
```

---

### Test 3: Force a CONFIRMED Booking to Test Sync

**Option A: Change BookingController to create CONFIRMED bookings**

Edit `src/Controller/BookingController.php:265`:
```php
// TEMPORARY FOR TESTING - Change from:
$booking->setStatus(Booking::STATUS_PENDING);

// To:
$booking->setStatus(Booking::STATUS_CONFIRMED);
```

Then create a booking - you'll see full Google Calendar sync attempt in logs.

**Option B: Confirm via Admin Dashboard**

1. Create booking as regular user (status = PENDING)
2. Log in as admin
3. Confirm the booking via dashboard
4. Watch AdminController logs for Google Calendar sync

---

### Test 4: Check API Response

If Google Calendar IS configured but events aren't created:

```bash
# Watch worker logs for API errors
php bin/console messenger:consume async -vv

# You'll see detailed error info:
[app.ERROR] ❌ EXCEPTION: Failed to sync booking to Google Calendar
  error_message: "The exact error from Google API"
  error_code: 401 (or 403, 404, 500, etc.)
  error_class: "Google\Service\Exception"
  stack_trace: "Full stack trace with all function calls"
```

Common API errors:
- **401 Unauthorized** - Invalid credentials
- **403 Forbidden** - Service account doesn't have calendar access
- **404 Not Found** - Calendar ID doesn't exist
- **500 Internal Server Error** - Google API issue

---

## 📋 Troubleshooting Checklist

### Issue: "Booking status is NOT CONFIRMED - skipping Google Calendar sync"

✅ **This is EXPECTED** - Current system design:
1. New bookings start as PENDING
2. Admin must confirm booking
3. Google Calendar sync happens when admin confirms
4. This prevents unconfirmed bookings from cluttering the calendar

**To test Google Calendar anyway:**
- Temporarily change `BookingController.php:265` to `STATUS_CONFIRMED`
- Or confirm bookings via admin dashboard
- Watch logs for sync attempt

---

### Issue: "Google Calendar is NOT CONFIGURED"

Check these:

```bash
# 1. Does credentials file exist?
ls -la $(grep GOOGLE_CALENDAR_CREDENTIALS_PATH .env | cut -d'=' -f2)

# 2. Is path in .env correct?
cat .env | grep GOOGLE_CALENDAR

# 3. Is file readable by PHP?
sudo -u www-data cat /path/to/service-account-key.json

# 4. Is it valid JSON?
cat /path/to/service-account-key.json | python -m json.tool
```

**Fix:**
```bash
# Update .env with correct path
GOOGLE_CALENDAR_CREDENTIALS_PATH=/full/absolute/path/to/service-account-key.json
GOOGLE_CALENDAR_ID=primary
```

---

### Issue: "Google Calendar createEvent returned NULL"

Check `GoogleCalendarService.php` for caught exceptions:

```php
// Look for this pattern in GoogleCalendarService.php:
catch (\Exception $e) {
    $this->logger->error('...', [...]);
    return null; // ← This causes NULL return
}
```

**The verbose logging will show the exception details before the NULL return**

---

### Issue: API Authentication Errors

```
error_message: "Invalid credentials"
error_code: 401
```

**Fix:**
1. Verify service account JSON is correct
2. Ensure calendar is shared with service account email
3. Check service account has Calendar API enabled

```bash
# Service account email is in the JSON:
cat service-account-key.json | grep client_email

# Share calendar with this email:
# Go to Google Calendar → Settings → Share with specific people
# Add: service-account@project-id.iam.gserviceaccount.com
# Permission: Make changes to events
```

---

### Issue: Calendar Not Found (404)

```
error_message: "Not Found"
error_code: 404
```

**Fix:**
- If using `GOOGLE_CALENDAR_ID=primary`, ensure service account has access to a calendar
- Or use specific calendar ID: `GOOGLE_CALENDAR_ID=abc123@group.calendar.google.com`

---

## 🎯 Quick Diagnosis Commands

### Check Current Booking Status Distribution

```sql
SELECT status, COUNT(*) as count
FROM booking
GROUP BY status;

-- Expected output:
| status    | count |
|-----------|-------|
| pending   | 15    | ← These won't sync to Google Calendar
| confirmed | 5     | ← These should sync to Google Calendar
| cancelled | 3     |
```

### Find Bookings That Should Have Synced But Didn't

```sql
SELECT id, status, google_calendar_event_id, created_at
FROM booking
WHERE status = 'confirmed'
  AND google_calendar_event_id IS NULL
ORDER BY created_at DESC
LIMIT 10;

-- These are CONFIRMED but not synced - check logs for errors
```

### Check If Google Calendar Credentials Exist

```bash
# Via PHP
php -r "
  require 'vendor/autoload.php';
  \$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  \$dotenv->load();
  \$path = \$_ENV['GOOGLE_CALENDAR_CREDENTIALS_PATH'] ?? 'NOT SET';
  echo 'Path: ' . \$path . PHP_EOL;
  echo 'Exists: ' . (file_exists(\$path) ? 'YES' : 'NO') . PHP_EOL;
"
```

---

## 📊 Log Interpretation Examples

### Example 1: Everything Working (CONFIRMED booking)

```
✅ Booking confirmation email sent
✅ Starting Google Calendar sync check...
   ├─ booking_status: "confirmed" ✅
   └─ has_calendar_event_id: "no" ✅

✅ Booking status is CONFIRMED - proceeding with Google Calendar sync

✅ Checking if Google Calendar is configured...

✅ Google Calendar IS CONFIGURED - attempting to create event...
   ├─ client_name: "Sarah Johnson"
   ├─ service: "Women's Haircut"
   └─ date: "2025-11-28 14:00:00"

✅ SUCCESS: Google Calendar event created!
   ├─ event_id: "evt_abc123xyz456"
   └─ calendar_event_url: "https://..."

✅ Google Calendar event ID saved to booking

✅ Google Calendar sync process completed
```

**Result:** Event created successfully ✅

---

### Example 2: PENDING Booking (Expected)

```
✅ Booking confirmation email sent

✅ Starting Google Calendar sync check...
   ├─ booking_status: "pending" ← Not CONFIRMED yet
   └─ has_calendar_event_id: "no"

⚠️  Booking status is NOT CONFIRMED - skipping Google Calendar sync
   ├─ current_status: "pending"
   └─ note: "Only CONFIRMED bookings are synced..."

✅ Google Calendar sync process completed
```

**Result:** Skipped (as designed) - Admin must confirm booking first ✅

---

### Example 3: Missing Credentials

```
✅ Booking confirmation email sent

✅ Starting Google Calendar sync check...
   ├─ booking_status: "confirmed"
   └─ has_calendar_event_id: "no"

✅ Booking status is CONFIRMED - proceeding with Google Calendar sync

✅ Checking if Google Calendar is configured...

❌ Google Calendar is NOT CONFIGURED
   ├─ reason: "Service account credentials file not found..."
   └─ check_env_variable: "GOOGLE_CALENDAR_CREDENTIALS_PATH"
```

**Result:** Configuration missing - Fix .env file ❌

---

### Example 4: API Error

```
✅ Booking confirmation email sent

✅ Google Calendar IS CONFIGURED - attempting to create event...
   └─ [booking details]

❌ EXCEPTION: Failed to sync booking to Google Calendar
   ├─ error_message: "Invalid credentials"
   ├─ error_code: 401
   ├─ error_class: "Google\Service\Exception"
   └─ stack_trace: [full trace]
```

**Result:** API authentication failed - Check service account permissions ❌

---

## 🚀 Next Steps

1. **Start the worker with verbose logging:**
   ```bash
   php bin/console messenger:consume async -vv
   ```

2. **Create a test booking** via the web interface

3. **Watch the terminal logs** - you'll see exactly:
   - ✅ Email sent confirmation
   - 🔍 Google Calendar sync check
   - 📊 Booking status (PENDING or CONFIRMED)
   - ⚙️ Google Calendar configuration status
   - 🌐 API call details
   - ✅ Success or ❌ Error with full details

4. **Read the logs** using this guide to understand what's happening

5. **Fix any issues** based on the error messages

---

## 📖 Summary

**Verbose logging now shows:**

- ✅ Booking status (PENDING/CONFIRMED/etc.)
- ✅ Google Calendar configuration status
- ✅ Exact API call details
- ✅ Success/failure with event IDs
- ✅ Full exception details with stack traces
- ✅ All intermediate steps

**Common findings:**

1. **"Booking status is NOT CONFIRMED"** = Working as designed (admin must confirm first)
2. **"Google Calendar is NOT CONFIGURED"** = Missing credentials file
3. **"EXCEPTION: Invalid credentials"** = Service account authentication issue
4. **"SUCCESS: Google Calendar event created"** = Everything working! 🎉

**The logs will tell you EXACTLY what's happening with Google Calendar!**
