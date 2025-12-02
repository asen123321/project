# Quick Start Guide - Hair Salon Booking System

## Admin Credentials

**Email:** asem4o@gmail.com
**Username:** asen_admin
**Default Password:** Admin123!
**⚠️ CHANGE PASSWORD IMMEDIATELY AFTER FIRST LOGIN**

---

## Initial Setup (3 Steps)

### Step 1: Load Data (Once Database Works)

```bash
cd /home/needy/project2
php bin/console doctrine:fixtures:load
```

This creates:
- Your admin account
- Stylist profile "Asen"
- 19 hair salon services

### Step 2: Configure Google Calendar

**Quick Steps:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create project → Enable Google Calendar API
3. Create Service Account → Download JSON key
4. Share your Google Calendar with the service account email
5. Upload JSON to: `/home/needy/project2/config/google-calendar-credentials.json`
6. Set permissions: `chmod 600 google-calendar-credentials.json`

**Detailed Guide:** See `GOOGLE_CALENDAR_SETUP.md`

### Step 3: Test Everything

1. Log in at: `https://yourdomain.com/`
2. Go to booking page
3. Create a test booking
4. Check your Google Calendar for the event
5. Check your email for confirmation

---

## What Happens When a Client Books

1. ✅ Client selects service and time on `/booking`
2. ✅ Booking is created in database
3. ✅ Event automatically appears in your Google Calendar
4. ✅ Client receives confirmation email with details
5. ✅ Google Calendar sends reminders (24h & 1h before)

---

## Your Stylist Profile

**Name:** Asen
**Specialization:** Master Stylist - All Services
**Bio:** Master stylist and salon owner with over 15 years of experience

This appears in the booking system dropdown.

---

## Available Services (19 Total)

**Basic Services:**
- Women's Haircut - $65 (45 min)
- Men's Haircut - $45 (30 min)
- Children's Haircut - $30 (25 min)

**Color Services:**
- Single Process Color - $85 (90 min)
- Full Highlights - $185 (180 min)
- Partial Highlights - $125 (120 min)
- Balayage - $165 (150 min)
- Color Correction - $250 (240 min)

**Specialty Services:**
- Deep Conditioning Treatment - $55 (30 min)
- Keratin Smoothing Treatment - $295 (180 min)
- Perm - $135 (150 min)

**Styling Services:**
- Blowout & Style - $55 (45 min)
- Formal Updo - $95 (60 min)
- Bridal Hair Trial - $125 (90 min)
- Bridal Hair Styling - $185 (120 min)

**Extensions:**
- Tape-In Extensions - $350 (120 min)
- Extension Removal - $75 (60 min)

**Men's Services:**
- Hot Shave - $50 (45 min)
- Beard Trim & Styling - $25 (20 min)

---

## Google Calendar Event Format

Events appear in your calendar like this:

**Title:** Hair Appointment: Women's Haircut

**Details:**
- Client name, email, phone
- Service type and duration
- Price
- Any special notes from client

**Reminders:**
- Email 24 hours before
- Popup notification 1 hour before

---

## Important Files

```
config/
  └── google-calendar-credentials.json  ← Google API credentials (keep secure!)

GOOGLE_CALENDAR_SETUP.md               ← Full setup instructions
IMPLEMENTATION_SUMMARY.md              ← Technical details
QUICK_START.md                         ← This file
```

---

## Troubleshooting

### "Google Calendar not configured"
- Upload credentials file to correct location
- Set proper permissions: `chmod 600`
- Verify file path in config

### Events not appearing in calendar
- Check calendar is shared with service account
- Look for events in "All events" view
- Verify timezone settings match

### Bookings work but no calendar sync
- System continues to work without calendar
- Check logs: `tail -f var/log/dev.log | grep "Google Calendar"`
- Email confirmations still sent to clients

---

## View Logs

```bash
# See Google Calendar sync status
tail -f var/log/dev.log | grep "Google Calendar"

# See all application activity
tail -f var/log/dev.log
```

---

## Security Reminders

- ⚠️ Change default admin password immediately
- 🔒 Keep `google-calendar-credentials.json` secure (never commit to git)
- 🔑 Rotate Google service account keys every 90 days
- 👤 Use strong passwords (12+ characters)

---

## Getting Help

1. **Google Calendar Setup:** Read `GOOGLE_CALENDAR_SETUP.md`
2. **Technical Details:** Read `IMPLEMENTATION_SUMMARY.md`
3. **Application Logs:** Check `var/log/dev.log`
4. **Google API Status:** Check [Google Cloud Console](https://console.cloud.google.com/)

---

## System Architecture

```
Client Books Appointment
         ↓
    Database ← Booking Created
         ↓
    Google Calendar ← Event Synced
         ↓
    Email ← Confirmation Sent
         ↓
    Client Receives Confirmation
```

**Non-blocking:** If Google Calendar is down, bookings still succeed and emails are still sent.

---

## Contact Information for Clients

Clients can reach you at: asem4o@gmail.com

All booking confirmations and calendar invites will use this email.

---

## Next Steps

1. ✅ Read this guide
2. ⏳ Load fixtures (once database works)
3. ⏳ Configure Google Calendar
4. ⏳ Change admin password
5. ⏳ Test booking flow
6. ✅ Start accepting appointments!

---

**Ready to Go Live!**

Once database and Google Calendar are configured, the system is fully operational and ready for production use.
