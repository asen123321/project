# Google Calendar Test Script - Usage Instructions

## 📋 Quick Start

The `test_google_sync.php` script has been created and is ready to run.

### Run the Test:

```bash
php test_google_sync.php
```

---

## 🔍 What the Script Does

The test script will:

1. ✅ Check if Composer autoloader exists
2. ✅ Look for `google-calendar.json` in project root
3. ✅ Validate the JSON credentials
4. ✅ Check for required fields in credentials
5. ✅ Initialize Google Client
6. ✅ Initialize Calendar Service
7. ✅ Try to access `asem4o@gmail.com` calendar
8. ✅ Create a test event
9. ✅ Verify the event was created
10. ✅ List recent events
11. ✅ Show summary and next steps

---

## 📊 Expected Output

### Successful Test:

```
==============================================
Google Calendar API Test Script
==============================================

Step 1: Loading Composer autoloader...
   ✅ Composer autoloader loaded

Step 2: Checking for Google Calendar credentials...
   Looking for: /home/needy/project2/google-calendar.json
   ✅ Credentials file found

Step 3: Validating credentials JSON...
   ✅ JSON is valid

Step 4: Checking required fields in credentials...
   ✅ All required fields present
   📧 Service Account Email: salon-booking@project-123456.iam.gserviceaccount.com
   📁 Project ID: project-123456

Step 5: Initializing Google Client...
   ✅ Google Client initialized

Step 6: Initializing Calendar Service...
   ✅ Calendar Service initialized

Step 7: Determining target calendar...
   Target user email: asem4o@gmail.com
   Trying calendar ID: asem4o@gmail.com

Step 8: Testing calendar access...
   Attempting to retrieve calendar...
   ✅ Calendar found!
   📅 Calendar Name: asem4o@gmail.com
   🆔 Calendar ID: asem4o@gmail.com

Step 9: Creating test event...
   Event Details:
   - Summary: [TEST] Hair Salon Booking System Test Event
   - Start: 2025-11-26 22:30:00
   - End: 2025-11-26 23:30:00
   - Attendee: asem4o@gmail.com

   Sending event to Google Calendar API...

   ✅ SUCCESS! Test event created!

   ==========================================
   EVENT DETAILS
   ==========================================
   Event ID: abc123xyz456
   Summary: [TEST] Hair Salon Booking System Test Event
   Status: confirmed
   Start: 2025-11-26T22:30:00+00:00
   End: 2025-11-26T23:30:00+00:00
   Link: https://www.google.com/calendar/event?eid=...
   ==========================================

Step 10: Verifying event exists...
   ✅ Event successfully retrieved from calendar
   Event ID matches: YES

Step 11: Listing recent events on calendar...
   Found 1 upcoming event(s):
   - [TEST] Hair Salon Booking System Test Event (2025-11-26T22:30:00+00:00)

==============================================
TEST SUMMARY
==============================================

✅ Google Calendar API is working!

What this means:
- Google Calendar credentials are valid ✅
- API authentication is working ✅
- Events can be created successfully ✅
- Calendar: asem4o@gmail.com ✅

Next Steps:
1. Check your Google Calendar at:
   https://calendar.google.com

2. You should see the test event:
   '[TEST] Hair Salon Booking System Test Event'

3. If using asem4o@gmail.com calendar:
   - The calendar must be shared with the service account:
   - salon-booking@project-123456.iam.gserviceaccount.com
   - With 'Make changes to events' permission

4. Update your Symfony .env file:
   GOOGLE_CALENDAR_CREDENTIALS_PATH=/home/needy/project2/google-calendar.json
   GOOGLE_CALENDAR_ID=asem4o@gmail.com

5. If you want to delete the test event:
   Event ID: abc123xyz456
   Or manually delete it from Google Calendar

==============================================
Test completed successfully! 🎉
==============================================
```

---

## ⚠️ Common Errors and Fixes

### Error 1: Credentials File Not Found

```
❌ ERROR: Credentials file not found!

Please ensure google-calendar.json exists in:
/home/needy/project2/google-calendar.json
```

**Fix:**
1. Download your service account JSON key from Google Cloud Console
2. Save it as `google-calendar.json` in the project root
3. Run the test again

---

### Error 2: Invalid JSON

```
❌ ERROR: Invalid JSON in credentials file
Error: Syntax error
```

**Fix:**
1. Verify the JSON file is valid:
   ```bash
   cat google-calendar.json | python -m json.tool
   ```
2. Re-download the credentials file from Google Cloud Console

---

### Error 3: Calendar Not Found (404)

```
⚠️  Could not access user calendar (asem4o@gmail.com)
Error Code: 404
Error: Not Found

Falling back to service account's own calendar...
```

**This is expected if:**
- The calendar hasn't been shared with the service account
- The service account will use its own calendar instead

**To fix (to use asem4o@gmail.com calendar):**
1. Go to Google Calendar settings
2. Share the calendar with the service account email
3. Grant "Make changes to events" permission

---

### Error 4: Permission Denied (403)

```
⚠️  Could not access user calendar (asem4o@gmail.com)
Error Code: 403
Error: Forbidden
```

**Fix:**
1. Share the calendar with the service account
2. Ensure permission is "Make changes to events"
3. Wait a few minutes for permissions to propagate

---

### Error 5: Invalid Credentials (401)

```
❌ ERROR: Failed to create event
HTTP Code: 401
Error Message: Invalid Credentials
```

**Fix:**
1. Re-download service account credentials
2. Ensure the service account has Calendar API enabled
3. Check that the JSON file is complete

---

### Error 6: API Not Enabled (403 - Calendar API)

```
❌ ERROR: Failed to create event
HTTP Code: 403
Reason: accessNotConfigured
Message: Calendar API has not been used in project...
```

**Fix:**
1. Go to Google Cloud Console
2. Enable the Google Calendar API
3. Wait a few minutes
4. Run the test again

---

## 🎯 Prerequisites

Before running the test, ensure:

1. **Composer dependencies installed:**
   ```bash
   composer install
   ```

2. **Service Account created:**
   - Go to Google Cloud Console
   - Create a Service Account
   - Download JSON key

3. **Google Calendar API enabled:**
   - In Google Cloud Console
   - APIs & Services → Library
   - Search "Google Calendar API"
   - Click "Enable"

4. **Calendar shared with service account (optional):**
   - Open Google Calendar
   - Settings → Share with specific people
   - Add service account email
   - Permission: "Make changes to events"

---

## 📖 Understanding the Output

### Success Indicators:

✅ All steps show green checkmarks
✅ "Test event created!" message appears
✅ Event ID is shown
✅ Event link is provided
✅ "Test completed successfully! 🎉"

### What to Check:

1. **Service Account Email:**
   - Shown in Step 4
   - This is the email you need to share your calendar with

2. **Calendar ID:**
   - Shown in Step 8
   - This is what you'll use in `.env` as `GOOGLE_CALENDAR_ID`

3. **Event ID:**
   - Shown in Step 9
   - Proves event was created successfully

4. **Event Link:**
   - Click this to view the event in Google Calendar
   - Verifies the event is visible

---

## 🔧 What to Do After Successful Test

### 1. Update Symfony Configuration

Edit `.env` file:

```env
# Google Calendar Service Account
GOOGLE_CALENDAR_CREDENTIALS_PATH=/home/needy/project2/google-calendar.json
GOOGLE_CALENDAR_ID=asem4o@gmail.com
```

### 2. Update services.yaml (if needed)

Edit `config/services.yaml`:

```yaml
parameters:
    google_calendar_credentials: '%env(resolve:GOOGLE_CALENDAR_CREDENTIALS_PATH)%'
    google_calendar_id: '%env(GOOGLE_CALENDAR_ID)%'
```

### 3. Clear Symfony Cache

```bash
php bin/console cache:clear
```

### 4. Test with Worker

```bash
# Start worker
php bin/console messenger:consume async -vv

# Create a booking via web UI

# Watch for Google Calendar logs
```

### 5. Verify in Google Calendar

1. Open https://calendar.google.com
2. Look for the test event
3. Verify it appears on the correct calendar
4. Delete the test event (optional)

---

## 🧪 Troubleshooting Tips

### If test fails at Step 8 (Calendar Access):

**The script will automatically fall back to the service account's own calendar.**

This means:
- Events will be created on the service account's calendar
- Not on asem4o@gmail.com's calendar
- Service account won't see events in their personal Google Calendar

**To use asem4o@gmail.com calendar:**
1. Share calendar with service account
2. Run test again

### If test succeeds but Symfony app still doesn't sync:

1. Check `.env` has correct paths
2. Verify `services.yaml` is configured
3. Ensure `GoogleCalendarService.php` uses the correct calendar ID
4. Check worker logs for errors
5. Verify booking status is CONFIRMED (not PENDING)

### If you see "createEvent returned NULL":

This means the API call didn't throw an exception but returned null.

**Check:**
- `GoogleCalendarService.php` for caught exceptions
- Worker logs for the actual error before NULL return
- API quotas in Google Cloud Console

---

## 📊 Interpreting Results

### Result: SUCCESS with asem4o@gmail.com calendar

```
✅ Calendar found!
📅 Calendar Name: asem4o@gmail.com
```

**This means:**
- Calendar is properly shared
- Service account has access
- Events will appear on asem4o@gmail.com's calendar
- ✅ Use `GOOGLE_CALENDAR_ID=asem4o@gmail.com` in `.env`

---

### Result: Fallback to service account calendar

```
⚠️  Could not access user calendar (asem4o@gmail.com)
Falling back to service account's own calendar...
✅ Using service account's primary calendar
```

**This means:**
- Calendar is not shared with service account
- Using service account's own calendar
- Events won't appear on asem4o@gmail.com's calendar
- ✅ Use `GOOGLE_CALENDAR_ID=primary` in `.env`
- ⚠️ Share calendar to fix

---

### Result: Complete failure

```
❌ ERROR: Cannot access any calendar
```

**This means:**
- Credentials are invalid
- API is not enabled
- Service account is misconfigured

**Fix:** Check credentials and API settings in Google Cloud Console

---

## 🚀 Quick Commands

```bash
# Run the test
php test_google_sync.php

# Check if credentials file exists
ls -la google-calendar.json

# View service account email
cat google-calendar.json | grep client_email

# Validate JSON
cat google-calendar.json | python -m json.tool

# Check Composer autoloader
ls -la vendor/autoload.php

# Clear Symfony cache after configuration
php bin/console cache:clear
```

---

## ✅ Success Checklist

After running the test, verify:

- [ ] Script completed without errors
- [ ] Event was created successfully
- [ ] Event ID was displayed
- [ ] Event appears in Google Calendar
- [ ] Service account email noted
- [ ] Calendar ID noted
- [ ] `.env` updated with correct paths
- [ ] Symfony cache cleared
- [ ] Test event deleted (optional)

---

## 📖 Summary

**The test script will:**

1. ✅ Validate your Google Calendar setup
2. ✅ Show exactly which calendar will be used
3. ✅ Create a real test event
4. ✅ Verify the event exists
5. ✅ Provide the exact configuration for Symfony

**After successful test:**

- Update `.env` with paths shown in test output
- Clear Symfony cache
- Create bookings to test integration
- Watch worker logs for sync activity

**The test eliminates guesswork - you'll know immediately if Google Calendar is working!** 🎉
