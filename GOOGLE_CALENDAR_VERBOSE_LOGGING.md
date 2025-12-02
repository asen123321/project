# ✅ Google Calendar Verbose Logging - Implementation Complete

## 🎯 What Was Changed

The `BookingConfirmationEmailHandler` has been enhanced with Google Calendar synchronization and comprehensive verbose logging to help debug API issues.

---

## 📝 Changes Made

### File Modified: `src/MessageHandler/BookingConfirmationEmailHandler.php`

**1. Added Dependencies:**
```php
use App\Entity\Booking;
use App\Service\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
```

**2. Injected Services:**
```php
public function __construct(
    // ... existing dependencies ...
    private GoogleCalendarService $googleCalendarService,
    private EntityManagerInterface $em,
    // ... existing dependencies ...
)
```

**3. Added Google Calendar Sync Logic (after email is sent):**
- 100+ lines of verbose logging
- Step-by-step progress tracking
- Full error details with stack traces
- Configuration checks
- Status validation

---

## 🔍 Verbose Logging Features

The handler now logs:

### ✅ Step 1: Sync Check Start
```
Starting Google Calendar sync check...
  - booking_id
  - booking_status (pending/confirmed/cancelled)
  - has_calendar_event_id (yes/no)
```

### ✅ Step 2: Status Validation
```
IF status = CONFIRMED:
  "Booking status is CONFIRMED - proceeding with Google Calendar sync"

ELSE:
  "Booking status is NOT CONFIRMED - skipping Google Calendar sync"
  - Shows current status
  - Explains why (only CONFIRMED bookings sync)
```

### ✅ Step 3: Configuration Check
```
"Checking if Google Calendar is configured..."

IF configured:
  "Google Calendar IS CONFIGURED - attempting to create event..."
  - Shows all booking details (name, email, service, date)

ELSE:
  "❌ Google Calendar is NOT CONFIGURED"
  - Shows reason
  - Tells you which .env variable to check
```

### ✅ Step 4: API Call Result
```
IF successful:
  "✅ SUCCESS: Google Calendar event created!"
  - Event ID
  - Calendar URL
  - Saved to database

IF API returned NULL:
  "⚠️ WARNING: Google Calendar createEvent returned NULL"
  - Lists possible reasons

IF exception occurred:
  "❌ EXCEPTION: Failed to sync booking to Google Calendar"
  - error_message
  - error_code
  - error_class
  - error_file
  - error_line
  - FULL stack_trace
```

### ✅ Step 5: Completion
```
"Google Calendar sync process completed"
```

---

## 🚀 How to Use

### Start the Worker with Verbose Logging

```bash
php bin/console messenger:consume async -vv
```

The `-vv` flag enables verbose output - you'll see ALL the detailed logs.

---

## 📊 What You'll See in Terminal

### Example Output (PENDING Booking - Current Default)

```
[2025-11-26 21:30:45] app.INFO: Booking confirmation email sent
  booking_id: 42
  user_email: client@example.com

[2025-11-26 21:30:45] app.INFO: Starting Google Calendar sync check...
  booking_id: 42
  booking_status: "pending"
  has_calendar_event_id: "no"

[2025-11-26 21:30:45] app.INFO: Booking status is NOT CONFIRMED - skipping Google Calendar sync
  booking_id: 42
  current_status: "pending"
  note: "Only CONFIRMED bookings are synced to Google Calendar. Admin must confirm this booking first."

[2025-11-26 21:30:45] app.INFO: Google Calendar sync process completed
  booking_id: 42
```

**This tells you:**
- ✅ Email was sent successfully
- ⚠️ Google Calendar sync was SKIPPED because status is PENDING
- ℹ️ Admin needs to confirm the booking first

---

### Example Output (CONFIRMED Booking - Google Calendar Working)

```
[2025-11-26 21:30:45] app.INFO: Booking confirmation email sent
  booking_id: 42
  user_email: client@example.com

[2025-11-26 21:30:45] app.INFO: Starting Google Calendar sync check...
  booking_id: 42
  booking_status: "confirmed"
  has_calendar_event_id: "no"

[2025-11-26 21:30:45] app.INFO: Booking status is CONFIRMED - proceeding with Google Calendar sync
  booking_id: 42

[2025-11-26 21:30:45] app.INFO: Checking if Google Calendar is configured...
  booking_id: 42

[2025-11-26 21:30:45] app.INFO: Google Calendar IS CONFIGURED - attempting to create event...
  booking_id: 42
  client_name: "Sarah Johnson"
  client_email: "sarah@example.com"
  service: "Women's Haircut"
  stylist: "Asen"
  date: "2025-11-28 14:00:00"

[2025-11-26 21:30:46] app.INFO: ✅ SUCCESS: Google Calendar event created!
  booking_id: 42
  event_id: "evt_abc123xyz456"
  calendar_event_url: "https://calendar.google.com/calendar/event?eid=..."

[2025-11-26 21:30:46] app.INFO: Google Calendar event ID saved to booking
  booking_id: 42
  event_id: "evt_abc123xyz456"

[2025-11-26 21:30:46] app.INFO: Google Calendar sync process completed
  booking_id: 42
```

**This tells you:**
- ✅ Email sent
- ✅ Status is CONFIRMED
- ✅ Google Calendar is configured
- ✅ API call succeeded
- ✅ Event created with ID
- ✅ Event ID saved to database

---

### Example Output (Google Calendar NOT Configured)

```
[2025-11-26 21:30:45] app.INFO: Booking confirmation email sent

[2025-11-26 21:30:45] app.INFO: Starting Google Calendar sync check...
  booking_status: "confirmed"

[2025-11-26 21:30:45] app.INFO: Booking status is CONFIRMED - proceeding with Google Calendar sync

[2025-11-26 21:30:45] app.INFO: Checking if Google Calendar is configured...

[2025-11-26 21:30:45] app.WARNING: ❌ Google Calendar is NOT CONFIGURED
  booking_id: 42
  reason: "Service account credentials file not found or not configured"
  check_env_variable: "GOOGLE_CALENDAR_CREDENTIALS_PATH"
```

**This tells you:**
- ⚠️ Credentials file is missing
- 🔧 Check `GOOGLE_CALENDAR_CREDENTIALS_PATH` in `.env`

---

### Example Output (API Error with Full Stack Trace)

```
[2025-11-26 21:30:45] app.INFO: Google Calendar IS CONFIGURED - attempting to create event...
  client_name: "Sarah Johnson"
  service: "Women's Haircut"
  date: "2025-11-28 14:00:00"

[2025-11-26 21:30:46] app.ERROR: ❌ EXCEPTION: Failed to sync booking to Google Calendar
  booking_id: 42
  error_message: "Invalid credentials"
  error_code: 401
  error_class: "Google\Service\Exception"
  error_file: "/vendor/google/apiclient/src/Service/Calendar.php"
  error_line: 123
  stack_trace: """
    #0 /vendor/google/apiclient/src/Service/Calendar.php(123): Google\Client->authenticate()
    #1 /home/needy/project2/src/Service/GoogleCalendarService.php(104): ...
    #2 /home/needy/project2/src/MessageHandler/BookingConfirmationEmailHandler.php(126): ...
    #3 [internal function]: App\MessageHandler\BookingConfirmationEmailHandler->__invoke()
    ...
  """
```

**This tells you:**
- ❌ API call failed
- 🔍 Exact error: "Invalid credentials"
- 📍 Error code: 401 (Unauthorized)
- 📂 Where it failed: GoogleCalendarService.php line 104
- 📝 Full call stack showing the execution path

---

## 🔧 Current System Behavior

### Important: Bookings Start as PENDING

After the recent system finalization, bookings are created with `status = 'pending'` (not confirmed).

**This means:**

1. User creates booking → Status: PENDING
2. BookingConfirmationEmailHandler runs:
   - ✅ Email sent
   - ⏭️ Google Calendar: SKIPPED (not confirmed yet)
3. Admin reviews booking in dashboard
4. Admin clicks "✓ Confirm"
5. AdminController changes status to CONFIRMED:
   - ✅ Google Calendar synced
   - ✅ "Booking Confirmed" email sent

**Why you see "zero activity" in Google Calendar:**
- The worker logs will show: "Booking status is NOT CONFIRMED - skipping Google Calendar sync"
- This is **EXPECTED BEHAVIOR**
- Google Calendar only syncs when admin confirms the booking

---

## 🧪 How to Test Google Calendar Sync

### Option 1: Confirm Bookings via Admin Dashboard (Recommended)

1. Create a booking as a regular user
2. Log in as admin (asem4o@gmail.com)
3. Go to `/admin/dashboard`
4. Find the pending booking
5. Click "✓ Confirm"
6. Watch the AdminController logs for Google Calendar sync

### Option 2: Temporarily Make Bookings CONFIRMED by Default

Edit `src/Controller/BookingController.php` line 265:

```php
// TEMPORARY FOR TESTING - Change from:
$booking->setStatus(Booking::STATUS_PENDING);

// To:
$booking->setStatus(Booking::STATUS_CONFIRMED);
```

Then:
1. Start worker: `php bin/console messenger:consume async -vv`
2. Create a booking via web UI
3. Watch the verbose logs in terminal
4. You'll see the full Google Calendar sync attempt

**Remember to change it back to PENDING after testing!**

---

## 📋 Troubleshooting Guide

### Issue: "Booking status is NOT CONFIRMED - skipping Google Calendar sync"

**This is NORMAL** - Bookings start as PENDING.

**To fix (if you want immediate sync):**
- Change `BookingController.php:265` to `STATUS_CONFIRMED`
- Or confirm bookings via admin dashboard

---

### Issue: "Google Calendar is NOT CONFIGURED"

**Fix:**

1. Check if credentials file exists:
   ```bash
   ls -la /path/to/service-account-key.json
   ```

2. Update `.env`:
   ```env
   GOOGLE_CALENDAR_CREDENTIALS_PATH=/full/absolute/path/to/service-account-key.json
   GOOGLE_CALENDAR_ID=primary
   ```

3. Restart worker

---

### Issue: "EXCEPTION: Invalid credentials" (Error 401)

**Fix:**

1. Verify service account JSON is valid
2. Share calendar with service account email:
   ```bash
   # Get service account email
   cat service-account-key.json | grep client_email
   
   # Share calendar with this email in Google Calendar settings
   ```
3. Ensure Calendar API is enabled for the project

---

### Issue: "createEvent returned NULL"

Check `GoogleCalendarService.php` for exceptions that return null.

**The verbose logging will show the exception BEFORE the NULL return** - look earlier in the logs for the actual error.

---

## 📖 Documentation Files

1. **GOOGLE_CALENDAR_DEBUG_GUIDE.md** (16 KB)
   - Comprehensive troubleshooting guide
   - Log interpretation examples
   - Common errors and fixes
   - SQL queries for debugging

2. **GOOGLE_CALENDAR_VERBOSE_LOGGING.md** (this file)
   - Quick reference
   - Example outputs
   - Testing instructions

---

## ✅ Summary

**What you now have:**

1. ✅ **Verbose logging** in BookingConfirmationEmailHandler
2. ✅ **Google Calendar sync** happens in background worker
3. ✅ **Full error details** with stack traces
4. ✅ **Step-by-step progress** visible in terminal
5. ✅ **Configuration checks** to diagnose issues

**How to use it:**

```bash
# Start worker with verbose output
php bin/console messenger:consume async -vv

# Create a booking via web UI

# Watch terminal logs - you'll see EXACTLY what happens:
# - Email sent ✅
# - Google Calendar sync check ✅
# - Status validation ✅
# - Configuration check ✅
# - API call result ✅
# - Success or error with full details ✅
```

**What the logs will tell you:**

- Why Google Calendar isn't syncing (status not CONFIRMED)
- If credentials are missing
- If API calls fail (with exact error)
- If events are created successfully

**The verbose logging will show you EVERYTHING!** 🎉
