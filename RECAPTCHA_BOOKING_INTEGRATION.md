# reCAPTCHA v3 Booking Integration Summary

This document details the Google reCAPTCHA v3 integration into the booking creation process to prevent automated booking abuse and bot attacks.

## Overview

Google reCAPTCHA v3 has been successfully integrated into the booking calendar system. The integration is completely invisible to legitimate users and provides robust protection against automated booking attacks, spam bookings, and bot abuse.

## Protected Endpoint

### Booking Creation (`/booking/create`)
- **Action**: `booking_create`
- **Minimum Score**: 0.5 (medium threshold - balances security with user experience)
- **Template**: `templates/booking/index.html.twig`
- **Controller**: `src/Controller/BookingController.php`
- **Verification**: Server-side via `ReCaptchaService`

## Implementation Details

### Backend Changes

**File**: `src/Controller/BookingController.php`

#### 1. Added ReCaptchaService Dependency

```php
use App\Service\ReCaptchaService;

public function __construct(
    // ... other dependencies
    private ReCaptchaService $recaptchaService
) {
}
```

#### 2. Updated index() Method

Passes reCAPTCHA configuration to the template:

```php
#[Route('/', name: 'booking_index')]
#[IsGranted('ROLE_USER')]
public function index(): Response
{
    $stylists = $this->stylistRepository->findAllActive();
    $services = $this->serviceRepository->findAllActive();
    $userBookings = $this->bookingRepository->findUpcomingByUser($this->getUser());

    return $this->render('booking/index.html.twig', [
        'stylists' => $stylists,
        'services' => $services,
        'userBookings' => $userBookings,
        'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
        'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
    ]);
}
```

#### 3. Added Verification to create() Endpoint

The verification happens **before any booking logic**, immediately after receiving the request:

```php
#[Route('/create', name: 'booking_create', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function create(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    if (!$data) {
        return $this->json(['error' => 'Invalid JSON'], 400);
    }

    // Verify reCAPTCHA if enabled
    if ($this->recaptchaService->isEnabled()) {
        $recaptchaToken = $data['recaptcha_token'] ?? null;

        // Require token
        if (!$recaptchaToken) {
            $this->logger->warning('Booking creation blocked: Missing reCAPTCHA token', [
                'user_id' => $this->getUser()->getId(),
                'ip_address' => $request->getClientIp()
            ]);

            return $this->json([
                'error' => 'Security verification is required. Please refresh the page and try again.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify token with Google
        $verification = $this->recaptchaService->verify(
            token: $recaptchaToken,
            action: 'booking_create',
            remoteIp: $request->getClientIp(),
            minScore: 0.5  // Medium threshold
        );

        // Block on failure
        if (!$verification['success']) {
            $this->logger->warning('Booking creation blocked: reCAPTCHA verification failed', [
                'user_id' => $this->getUser()->getId(),
                'ip_address' => $request->getClientIp(),
                'score' => $verification['score'] ?? 'N/A',
                'reason' => $verification['message'] ?? 'Unknown'
            ]);

            return $this->json([
                'error' => 'Suspicious activity detected. Please try again or contact support if the problem persists.',
                'code' => 'RECAPTCHA_FAILED'
            ], Response::HTTP_FORBIDDEN);
        }

        // Log successful verification
        $this->logger->info('reCAPTCHA verification successful for booking', [
            'user_id' => $this->getUser()->getId(),
            'score' => $verification['score']
        ]);
    }

    // Continue with booking creation...
}
```

### Frontend Changes

**File**: `templates/booking/index.html.twig`

#### 1. Load reCAPTCHA Script

Added conditional script loading in the `<head>` section:

```html
{% if recaptcha_enabled %}
{# Load reCAPTCHA v3 #}
<script src="https://www.google.com/recaptcha/api.js?render={{ recaptcha_site_key }}"></script>
{% endif %}
```

#### 2. Configure reCAPTCHA Constants

Added configuration at the beginning of the JavaScript:

```javascript
// reCAPTCHA configuration
const RECAPTCHA_ENABLED = {{ recaptcha_enabled ? 'true' : 'false' }};
const RECAPTCHA_SITE_KEY = '{{ recaptcha_site_key }}';
```

#### 3. Updated Booking Confirmation Handler

Modified the booking creation flow to include reCAPTCHA verification:

```javascript
document.getElementById('confirmBookingBtn').addEventListener('click', async function() {
    const alertDiv = document.getElementById('modal-alert');
    const btn = this;

    // ... form validation ...

    try {
        // Step 1: Check availability first
        // ... availability check code ...

        // Step 2: Generate reCAPTCHA token if enabled
        let recaptchaToken = null;

        if (RECAPTCHA_ENABLED) {
            try {
                btn.innerHTML = '<span class="loading-spinner"></span> Verifying security...';
                recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {
                    action: 'booking_create'
                });
            } catch (error) {
                console.error('reCAPTCHA error:', error);
                alertDiv.innerHTML = '<div class="alert alert-error">Security verification failed. Please refresh the page and try again.</div>';
                btn.disabled = false;
                btn.innerHTML = 'Confirm Booking';
                return;
            }
        }

        // Step 3: Create booking
        btn.innerHTML = '<span class="loading-spinner"></span> Creating booking...';

        const bookingData = {
            stylist_id: stylistId,
            service_id: serviceId,
            booking_date: bookingDate,
            booking_time: bookingTime,
            notes: notes
        };

        // Add reCAPTCHA token if enabled
        if (RECAPTCHA_ENABLED && recaptchaToken) {
            bookingData.recaptcha_token = recaptchaToken;
        }

        const response = await fetch('/booking/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(bookingData)
        });

        const result = await response.json();

        if (result.success) {
            alertDiv.innerHTML = '<div class="alert alert-success">✓ ' + result.message + '</div>';
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            // Check for reCAPTCHA failure
            if (response.status === 403 && result.code === 'RECAPTCHA_FAILED') {
                alertDiv.innerHTML = '<div class="alert alert-error">⚠️ ' + result.error + '</div>';
            } else {
                throw new Error(result.error || 'Booking failed');
            }
            btn.disabled = false;
            btn.innerHTML = 'Confirm Booking';
        }
    } catch (error) {
        alertDiv.innerHTML = `<div class="alert alert-error">✗ ${error.message}</div>`;
        btn.disabled = false;
        btn.innerHTML = 'Confirm Booking';
    }
});
```

## Security Features

### 1. Server-Side Verification Only
- All tokens verified server-side via Google's API
- Frontend verification never trusted
- Secret key never exposed to client

### 2. Action Validation
- Token generated with action `booking_create`
- Server validates action matches expected value
- Prevents token replay from other forms

### 3. IP Address Tracking
- User IP included in verification request
- Helps Google detect suspicious patterns
- Improves scoring accuracy

### 4. Comprehensive Logging
- Successful verifications logged with score
- Failed verifications logged with reason and IP
- Missing tokens logged with user information
- All logs include user_id for audit trails

### 5. Score-Based Protection
- Minimum score: 0.5 (medium threshold)
- Balances security with user experience
- Prevents false positives for legitimate users

### 6. No Sensitive Data Exposure
- Booking details only in request body (not logged to console)
- reCAPTCHA token not logged (security best practice)
- Error messages user-friendly without revealing internals
- Console.error used only for reCAPTCHA library errors

### 7. Graceful Degradation
- reCAPTCHA can be disabled via environment variables
- When disabled, bookings proceed without verification
- Allows testing and development without keys

## Score Threshold Reasoning

**Medium Threshold (0.5)** chosen for booking creation because:

- **Higher than login (0.4)**: Bookings are more valuable actions that could be abused
- **Lower than financial transactions (0.7-0.8)**: Not as critical as payment processing
- **Balances security and UX**: Prevents most bots while allowing legitimate users
- **Accounts for VPN users**: Some legitimate users may score lower when using VPNs
- **Based on best practices**: Google recommends 0.5 for form submissions

**Score Range**: 0.0 (very likely bot) to 1.0 (very likely human)

## User Experience Flow

### For Legitimate Users (Score ≥ 0.5)

1. **Select time slot** on calendar → Opens booking modal
2. **Fill in details** (stylist, service, notes)
3. **Click "Confirm Booking"** → Button changes to "Checking availability..."
4. **Availability verified** → Button changes to "Verifying security..."
5. **reCAPTCHA executes** (invisible - no interaction needed)
6. **Token generated** → Button changes to "Creating booking..."
7. **Booking created** → Success message appears
8. **Page refreshes** → New booking visible on calendar

**Total time added by reCAPTCHA**: ~0.5-1 second (imperceptible to users)

### For Bots/Suspicious Activity (Score < 0.5)

1. **Select time slot** → Opens modal
2. **Fill in details** (if bot can)
3. **Click button** → reCAPTCHA executes
4. **Low score detected** → Server returns HTTP 403
5. **Error displayed**: "Suspicious activity detected. Please try again or contact support."
6. **Booking blocked** → No database entry created

## Error Handling

### HTTP 403 Responses

All reCAPTCHA failures return HTTP 403 with JSON:

```json
{
    "error": "User-friendly error message",
    "code": "RECAPTCHA_FAILED"
}
```

### User-Friendly Error Messages

| Scenario | Message | Status Code |
|----------|---------|-------------|
| Missing token | "Security verification is required. Please refresh the page and try again." | 403 |
| Low score | "Suspicious activity detected. Please try again or contact support if the problem persists." | 403 |
| reCAPTCHA library error | "Security verification failed. Please refresh the page and try again." | N/A (handled in frontend) |
| Network error | "✗ [error message]" | N/A (catch block) |

### Error Display

- Errors shown in modal alert div
- Red background with clear error icon
- Button re-enabled for retry
- No page refresh on error (user can try again)

## Security Benefits

### 1. Prevents Automated Booking Attacks
- Bots cannot easily create spam bookings
- Protects against calendar flooding
- Prevents denial-of-service via bookings

### 2. Protects Stylist Availability
- Ensures real customers can book appointments
- Prevents fake bookings blocking legitimate users
- Maintains booking system integrity

### 3. Reduces Admin Workload
- Fewer spam bookings to review
- Less time spent cleaning up fake appointments
- Improves overall system efficiency

### 4. Audit Trail
- All verification attempts logged
- Can track suspicious IP addresses
- Helps identify attack patterns

## Configuration

### Environment Variables

Located in `.env` file:

```env
RECAPTCHA_SITE_KEY=6Ld5ARosAAAAAP3Xo-vm2XcpUkCb-_j7_m5aR-k7
RECAPTCHA_SECRET_KEY=6Ld5ARosAAAAAFAFjBXrr1E6m6qKMGlKNvZadPBb
```

To disable reCAPTCHA (development/testing):
```env
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

### Service Configuration

Located in `config/services.yaml`:

```yaml
parameters:
  recaptcha.site_key: '%env(RECAPTCHA_SITE_KEY)%'
  recaptcha.secret_key: '%env(RECAPTCHA_SECRET_KEY)%'

services:
  _defaults:
    bind:
      $recaptchaSiteKey: '%recaptcha.site_key%'
      $recaptchaSecretKey: '%recaptcha.secret_key%'
```

## Testing

### Development Testing

1. **With Test Keys** (Google's public test keys):
```env
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```
These always return success with score 1.0.

2. **Disabled** (empty keys):
```env
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```
reCAPTCHA checks are skipped entirely.

### Testing Checklist

- [ ] Booking modal loads correctly
- [ ] reCAPTCHA badge appears in bottom-right corner (if enabled)
- [ ] Booking submission generates token
- [ ] Token sent to backend in request body
- [ ] Backend verifies token successfully
- [ ] Successful booking creates calendar event
- [ ] Low scores are rejected with appropriate error
- [ ] Error messages are user-friendly
- [ ] No sensitive data in browser console
- [ ] Disabled reCAPTCHA allows bookings to proceed

## Monitoring

### Logs

reCAPTCHA verification results are logged:

```php
// Missing token
$this->logger->warning('Booking creation blocked: Missing reCAPTCHA token', [
    'user_id' => $this->getUser()->getId(),
    'ip_address' => $request->getClientIp()
]);

// Failed verification
$this->logger->warning('Booking creation blocked: reCAPTCHA verification failed', [
    'user_id' => $this->getUser()->getId(),
    'ip_address' => $request->getClientIp(),
    'score' => $verification['score'] ?? 'N/A',
    'reason' => $verification['message'] ?? 'Unknown'
]);

// Successful verification
$this->logger->info('reCAPTCHA verification successful for booking', [
    'user_id' => $this->getUser()->getId(),
    'score' => $verification['score']
]);
```

View logs:
```bash
tail -f var/log/dev.log | grep reCAPTCHA
tail -f var/log/dev.log | grep "Booking creation blocked"
```

### Metrics to Track

1. **Verification Success Rate**
   - Should be >95% for legitimate users
   - Lower rates may indicate overly strict threshold

2. **Average Scores**
   - Legitimate users typically score 0.7-1.0
   - Consistent low scores may indicate bot attacks

3. **Blocked Attempts**
   - Track IP addresses with multiple failures
   - May indicate coordinated attack attempts

4. **Score Distribution**
   - Monitor how many users fall into each score range
   - Helps tune the minimum score threshold

## Troubleshooting

### Issue: "Security verification is required"

**Cause**: Frontend not sending reCAPTCHA token

**Solution**:
1. Check browser console for JavaScript errors
2. Verify reCAPTCHA script loaded correctly
3. Check RECAPTCHA_SITE_KEY is set correctly
4. Ensure recaptcha_enabled is true in template

### Issue: "Suspicious activity detected"

**Cause**: User scored below 0.5 threshold

**Solution**:
1. Ask user to try again (scores can vary)
2. Check if user is using VPN (can lower scores)
3. Clear browser cookies/cache
4. Contact support if problem persists
5. Consider temporarily lowering threshold for testing

### Issue: reCAPTCHA badge not showing

**Cause**: Script not loaded or blocked

**Solution**:
1. Check browser console for errors
2. Verify script tag exists in page source
3. Check ad-blocker isn't blocking reCAPTCHA
4. Confirm RECAPTCHA_SITE_KEY is valid

### Issue: All bookings being blocked

**Cause**: Production site key used on wrong domain

**Solution**:
1. Verify domain registered in Google reCAPTCHA console
2. For localhost, make sure "localhost" is in domains list
3. Check RECAPTCHA_SITE_KEY matches your domain's key

### Issue: Console errors about grecaptcha

**Cause**: Script loaded but not initialized

**Solution**:
1. Ensure script loaded before form submission
2. Add error handling for grecaptcha.execute
3. Check network tab for failed script loads

## Privacy Considerations

### Data Sent to Google

When reCAPTCHA is enabled, the following data is sent to Google:

- **User IP address** (for scoring)
- **Browser user agent** (for bot detection)
- **Interaction patterns** (mouse movements, typing patterns)
- **Cookie information** (if user has visited Google sites)

### User Notification

Consider adding a privacy notice to your booking page:

```html
<div class="recaptcha-notice" style="font-size: 12px; color: #666; margin-top: 10px;">
    This site is protected by reCAPTCHA and the Google
    <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and
    <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.
</div>
```

## Performance Impact

### Load Time
- **Script size**: ~60KB (loaded asynchronously)
- **First load**: ~200-300ms to download and initialize
- **Cached**: <50ms on subsequent page loads

### Booking Time
- **Token generation**: 300-800ms
- **Server verification**: 100-300ms
- **Total added time**: ~0.5-1.5 seconds

**Impact**: Minimal - users typically don't notice the delay

## Best Practices

### ✅ DO

1. **Keep score threshold balanced**
   - Too high: blocks legitimate users
   - Too low: allows bots through
   - 0.5 is recommended for bookings

2. **Monitor logs regularly**
   - Track blocked attempts
   - Identify attack patterns
   - Adjust threshold if needed

3. **Test with real users**
   - Get feedback on false positives
   - Adjust threshold based on data
   - Monitor success rates

4. **Provide clear error messages**
   - Don't reveal security details
   - Offer actionable solutions
   - Include support contact

5. **Log all verification attempts**
   - Helps identify issues
   - Provides audit trail
   - Aids in debugging

### ❌ DON'T

1. **Don't expose secret key**
   - Never in frontend code
   - Never in version control
   - Never in logs

2. **Don't trust frontend verification**
   - Always verify server-side
   - Frontend can be manipulated
   - Security must be server-side

3. **Don't log sensitive data**
   - No booking details in logs
   - No full reCAPTCHA tokens
   - No user passwords (obviously)

4. **Don't reuse tokens**
   - Generate new token per action
   - Tokens are single-use
   - Old tokens will fail

5. **Don't skip error handling**
   - Handle network failures
   - Handle reCAPTCHA errors
   - Provide fallback behavior

## Related Documentation

- **reCAPTCHA Setup Guide**: `RECAPTCHA_SETUP.md`
- **Login Integration**: `RECAPTCHA_LOGIN_INTEGRATION.md`
- **Service Implementation**: `src/Service/ReCaptchaService.php`
- **Booking Controller**: `src/Controller/BookingController.php`
- **Booking Template**: `templates/booking/index.html.twig`

## Support

- **Google reCAPTCHA Console**: https://www.google.com/recaptcha/admin
- **Official Documentation**: https://developers.google.com/recaptcha/docs/v3
- **API Reference**: https://developers.google.com/recaptcha/docs/verify

---

**Integration Completed**: 2024
**Status**: Active and protecting booking creation
**Score Threshold**: 0.5 (medium)
**Action Name**: `booking_create`
