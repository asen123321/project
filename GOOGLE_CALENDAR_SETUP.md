# Google Calendar API Integration Setup Guide

This guide provides step-by-step instructions for setting up Google Calendar API integration for the Hair Salon Booking System. When properly configured, all confirmed bookings will automatically sync to the admin's Google Calendar.

## Table of Contents
1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Method 1: Service Account (Recommended)](#method-1-service-account-recommended)
4. [Method 2: OAuth 2.0 Client](#method-2-oauth-20-client)
5. [Configuration](#configuration)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The booking system integrates with Google Calendar to:
- **Automatically create** calendar events when bookings are confirmed
- **Include appointment details** (client name, email, phone, service, duration, price)
- **Set reminders** (24 hours before via email, 1 hour before via popup)
- **Delete events** when bookings are cancelled
- **Sync to admin's calendar** (asem4o@gmail.com)

The integration is **non-blocking** - if Google Calendar sync fails, the booking will still be created and the user will receive their confirmation email.

---

## Prerequisites

Before you begin, you need:
- A Google account (asem4o@gmail.com)
- Access to [Google Cloud Console](https://console.cloud.google.com/)
- Administrator access to this server to upload credentials

---

## Method 1: Service Account (Recommended)

A service account is the recommended approach for server-to-server API access. It doesn't require user interaction and works automatically.

### Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click **"Select a project"** dropdown at the top
3. Click **"New Project"**
4. Enter project name: `Hair-Salon-Booking`
5. Click **"Create"**
6. Wait for project creation, then select the new project

### Step 2: Enable Google Calendar API

1. In the left sidebar, go to **"APIs & Services" > "Library"**
2. Search for `Google Calendar API`
3. Click on **"Google Calendar API"**
4. Click **"Enable"** button
5. Wait for API to be enabled

### Step 3: Create Service Account

1. In the left sidebar, go to **"APIs & Services" > "Credentials"**
2. Click **"+ CREATE CREDENTIALS"** at the top
3. Select **"Service account"**
4. Fill in the details:
   - **Service account name**: `booking-calendar-sync`
   - **Service account ID**: (auto-generated, e.g., `booking-calendar-sync@hair-salon-booking.iam.gserviceaccount.com`)
   - **Description**: `Service account for syncing bookings to Google Calendar`
5. Click **"CREATE AND CONTINUE"**
6. **Grant this service account access to project** (optional, skip this)
7. Click **"CONTINUE"**
8. **Grant users access to this service account** (optional, skip this)
9. Click **"DONE"**

### Step 4: Create and Download Service Account Key

1. You'll see your service account in the list
2. Click on the service account email (e.g., `booking-calendar-sync@hair-salon-booking.iam.gserviceaccount.com`)
3. Go to the **"KEYS"** tab
4. Click **"ADD KEY" > "Create new key"**
5. Select **"JSON"** format
6. Click **"CREATE"**
7. The JSON file will automatically download to your computer
8. **IMPORTANT**: Keep this file secure! It contains private credentials.

### Step 5: Share Calendar with Service Account

This is the critical step that allows the service account to write to the admin's calendar.

1. Open [Google Calendar](https://calendar.google.com/)
2. Log in as **asem4o@gmail.com**
3. On the left sidebar, find **"My calendars"**
4. Hover over the calendar you want to use (usually your primary calendar)
5. Click the **three dots** (⋮) menu
6. Select **"Settings and sharing"**
7. Scroll down to **"Share with specific people or groups"**
8. Click **"+ Add people and groups"**
9. Enter the **service account email** from Step 3 (e.g., `booking-calendar-sync@hair-salon-booking.iam.gserviceaccount.com`)
10. Set permissions to **"Make changes to events"**
11. Click **"Send"**
12. You'll see a warning that this is a service account - click **"Ok"**

### Step 6: Upload Credentials to Server

1. Rename the downloaded JSON file to: `google-calendar-credentials.json`
2. Upload the file to the server at: `/home/needy/project2/config/google-calendar-credentials.json`

**Using SCP (from your local machine):**
```bash
scp /path/to/downloaded/file.json user@server:/home/needy/project2/config/google-calendar-credentials.json
```

**Or create the file manually:**
```bash
nano /home/needy/project2/config/google-calendar-credentials.json
# Paste the JSON content
# Press Ctrl+X, then Y, then Enter to save
```

3. Set proper permissions:
```bash
chmod 600 /home/needy/project2/config/google-calendar-credentials.json
chown www-data:www-data /home/needy/project2/config/google-calendar-credentials.json
```

### Step 7: Find Your Calendar ID (if not using primary)

If you want to use a specific calendar instead of the primary one:

1. In [Google Calendar](https://calendar.google.com/), go to **Settings**
2. Click on the calendar in the left sidebar under **"Settings for my calendars"**
3. Scroll down to **"Integrate calendar"**
4. Copy the **"Calendar ID"** (e.g., `asem4o@gmail.com` for primary, or something like `abc123@group.calendar.google.com` for a shared calendar)
5. Update `config/services.yaml`:
```yaml
parameters:
  google.calendar.calendar_id: 'your-calendar-id-here@gmail.com'
```

---

## Method 2: OAuth 2.0 Client

OAuth 2.0 requires user consent but gives more flexibility. This is more complex and requires a web-based authentication flow.

### Step 1-2: Same as Method 1

Follow Steps 1-2 from Method 1 (Create Project and Enable API)

### Step 3: Create OAuth 2.0 Credentials

1. In the left sidebar, go to **"APIs & Services" > "Credentials"**
2. Click **"+ CREATE CREDENTIALS"** at the top
3. Select **"OAuth client ID"**
4. If prompted, configure the OAuth consent screen:
   - **User Type**: Internal (if using Google Workspace) or External
   - **App name**: `Hair Salon Booking System`
   - **User support email**: `asem4o@gmail.com`
   - **Developer contact**: `asem4o@gmail.com`
   - Click **"Save and Continue"**
   - **Scopes**: Click "Add or Remove Scopes", search for `Google Calendar API`, select:
     - `https://www.googleapis.com/auth/calendar` (See, edit, share, and permanently delete all calendars)
   - Click **"Update"**, then **"Save and Continue"**
   - **Test users**: Add `asem4o@gmail.com`
   - Click **"Save and Continue"**

5. Now create the OAuth client:
   - **Application type**: Select **"Web application"**
   - **Name**: `Booking System Calendar Sync`
   - **Authorized redirect URIs**: Add `https://yourdomain.com/google/callback` (adjust for your domain)
   - Click **"CREATE"**

6. Download the credentials JSON file
7. Rename to `google-calendar-credentials.json`
8. Upload to `/home/needy/project2/config/google-calendar-credentials.json`

### Step 4: Implement Token Storage

OAuth 2.0 requires storing and refreshing access tokens. You'll need to:

1. Create a one-time authentication script to get the initial token
2. Store the refresh token securely in the database
3. Modify `GoogleCalendarService` to use OAuth flow instead of service account

**Note:** This is significantly more complex. Service Account (Method 1) is recommended for server-to-server integration.

---

## Configuration

### services.yaml Configuration

The service is already configured in `config/services.yaml`:

```yaml
parameters:
  google.calendar.credentials_path: '%kernel.project_dir%/config/google-calendar-credentials.json'
  google.calendar.calendar_id: 'primary'

services:
  App\Service\GoogleCalendarService:
    arguments:
      $credentialsPath: '%google.calendar.credentials_path%'
      $calendarId: '%google.calendar.calendar_id%'
```

**To use a specific calendar instead of primary:**
```yaml
parameters:
  google.calendar.calendar_id: 'asem4o@gmail.com'  # or your specific calendar ID
```

### Environment Variables (Optional)

For added security, you can use environment variables:

1. Edit `.env` or `.env.local`:
```env
GOOGLE_CALENDAR_CREDENTIALS_PATH=/path/to/google-calendar-credentials.json
GOOGLE_CALENDAR_ID=primary
```

2. Update `config/services.yaml`:
```yaml
parameters:
  google.calendar.credentials_path: '%env(GOOGLE_CALENDAR_CREDENTIALS_PATH)%'
  google.calendar.calendar_id: '%env(GOOGLE_CALENDAR_ID)%'
```

---

## Testing

### Check if Configuration is Valid

1. Create a test booking through the booking system
2. Check the application logs:
```bash
tail -f var/log/dev.log | grep "Google Calendar"
```

3. Look for these log messages:
   - ✅ `Google Calendar client initialized successfully`
   - ✅ `Booking synced to Google Calendar` with event_id
   - ⚠️ `Google Calendar not configured - skipping sync` (credentials file missing)
   - ❌ `Failed to sync booking to Google Calendar` (check error details)

### Verify in Google Calendar

1. Log in to [Google Calendar](https://calendar.google.com/) as **asem4o@gmail.com**
2. Look for the created event with title: `Hair Appointment: [Service Name]`
3. Click on the event to verify details:
   - Start/end time should match booking
   - Description should contain client info, service details
   - Attendee should be the client's email
   - Reminders should be set (24 hours, 1 hour)

### Test Booking Cancellation

1. Cancel a booking through the system
2. Verify the event is deleted from Google Calendar
3. Check logs for: `Google Calendar event deleted`

---

## Troubleshooting

### Error: "Credentials file not found"

**Symptom:** Log shows `Google Calendar not configured - skipping sync`

**Solution:**
- Verify file exists: `ls -la /home/needy/project2/config/google-calendar-credentials.json`
- Check file permissions: `chmod 600` and owned by web server user
- Verify path in `config/services.yaml` matches actual file location

### Error: "Invalid credentials"

**Symptom:** `Failed to initialize Google Calendar client` with authentication error

**Solution:**
- Verify the JSON file is valid (check for syntax errors)
- Ensure you downloaded the correct key type (JSON for service account)
- Try regenerating and downloading a new key from Google Cloud Console

### Error: "Calendar not found" or "Forbidden"

**Symptom:** Events aren't created, log shows 403 or 404 errors

**Solution:**
- **Service Account Method:** Ensure you shared the calendar with the service account email (Step 5 of Method 1)
- Verify the calendar ID is correct in `services.yaml`
- Check that service account has "Make changes to events" permission
- Try using `'primary'` as calendar_id to test

### Error: "Insufficient permissions"

**Symptom:** API returns 403 with "Insufficient Permission" message

**Solution:**
- Verify Google Calendar API is enabled in Google Cloud Console
- Check service account has correct scopes in credentials file
- Ensure calendar is shared with service account with edit permissions

### Events Not Appearing

**Symptom:** No errors in logs, but events don't show in calendar

**Solution:**
- Check you're viewing the correct calendar in Google Calendar
- Ensure calendar is not hidden in Google Calendar settings
- Verify timezone settings match between server and Google Calendar
- Look for events in the "All events" view

### Performance Issues

**Symptom:** Booking creation is slow

**Solution:**
- The Google Calendar sync is already non-blocking
- If still slow, consider moving sync to a background job:
  - Create a Symfony Messenger handler
  - Dispatch `CalendarSyncMessage` after booking creation
  - Process asynchronously with queue worker

---

## Security Best Practices

1. **Never commit credentials to version control**
   - Add to `.gitignore`: `config/google-calendar-credentials.json`

2. **Restrict file permissions**
   ```bash
   chmod 600 config/google-calendar-credentials.json
   chown www-data:www-data config/google-calendar-credentials.json
   ```

3. **Rotate service account keys regularly**
   - Generate new key every 90 days
   - Delete old keys from Google Cloud Console

4. **Use specific calendar**
   - Create a dedicated calendar for bookings instead of using primary
   - Share only that calendar with the service account

5. **Monitor API usage**
   - Check Google Cloud Console > APIs & Services > Dashboard
   - Set up quota alerts to prevent abuse

6. **Enable audit logging**
   - Review Google Calendar activity logs regularly
   - Set up alerts for suspicious activity

---

## Additional Features

### Custom Event Colors

To color-code events by service type, update `GoogleCalendarService.php`:

```php
$event->setColorId('5'); // 5 = Yellow, see Google Calendar API docs for color IDs
```

### Video Conference Links

To add Google Meet links to appointments:

```php
$event->setConferenceData([
    'createRequest' => [
        'requestId' => uniqid(),
        'conferenceSolutionKey' => ['type' => 'hangoutsMeet']
    ]
]);
```

### Multiple Stylist Support

If you expand to multiple stylists in the future:

1. Create a separate calendar for each stylist
2. Share each calendar with the service account
3. Store calendar_id in the Stylist entity
4. Pass `$stylist->getCalendarId()` to `GoogleCalendarService`

---

## Support & Resources

- [Google Calendar API Documentation](https://developers.google.com/calendar/api/guides/overview)
- [Service Accounts Overview](https://cloud.google.com/iam/docs/service-accounts)
- [PHP Client Library](https://github.com/googleapis/google-api-php-client)
- [Calendar API Quotas](https://developers.google.com/calendar/api/guides/quota)

For application-specific issues, check:
- Application logs: `var/log/dev.log` or `var/log/prod.log`
- Symfony debug toolbar when in dev environment
- Google Cloud Console > Logging for API-level errors

---

## Summary

You've now set up Google Calendar integration! Here's what happens:

1. ✅ User books appointment through the booking system
2. ✅ Booking is created in the database
3. ✅ System creates Google Calendar event with all details
4. ✅ Event appears in admin's calendar (asem4o@gmail.com)
5. ✅ Reminders are automatically set
6. ✅ Email confirmation is sent to client
7. ✅ If booking is cancelled, event is deleted from calendar

The system will continue to work even if Google Calendar is temporarily unavailable - bookings are never lost!
