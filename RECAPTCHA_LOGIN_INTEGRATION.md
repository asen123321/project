# reCAPTCHA v3 Login Integration Summary

This document summarizes the Google reCAPTCHA v3 integration into the Symfony authentication system.

## Overview

Google reCAPTCHA v3 has been successfully integrated into all authentication endpoints to protect against automated bot attacks and abuse. The integration is invisible to legitimate users and provides score-based bot detection.

## Integrated Endpoints

### 1. Standard Login (`/api/login`)
- **Action**: `login`
- **Minimum Score**: 0.4 (lower threshold for better user experience)
- **Template**: `templates/login/index.html.twig`
- **Verification**: Server-side via `ReCaptchaService`

### 2. User Registration (`/api/register`)
- **Action**: `register`
- **Minimum Score**: 0.5 (medium threshold for new accounts)
- **Template**: `templates/registration/register.html.twig`
- **Verification**: Server-side via `ReCaptchaService`

### 3. Google OAuth Login (`/api/google-login`)
- **Action**: `google_login`
- **Minimum Score**: 0.4 (lower threshold for social login)
- **Template**: `templates/login/index.html.twig`
- **Verification**: Server-side via `ReCaptchaService`

## Implementation Details

### Backend Changes

**File**: `src/Controller/LoginController.php`

All three authentication endpoints now follow this pattern:

```php
// 1. Check if reCAPTCHA is enabled
if ($this->recaptchaService->isEnabled()) {

    // 2. Extract token from request
    $recaptchaToken = $data['recaptcha_token'] ?? null;

    // 3. Require token if reCAPTCHA is enabled
    if (!$recaptchaToken) {
        return $this->json([
            'error' => 'Security verification is required...'
        ], Response::HTTP_FORBIDDEN);
    }

    // 4. Verify token with Google
    $verification = $this->recaptchaService->verify(
        token: $recaptchaToken,
        action: 'login',  // or 'register', 'google_login'
        remoteIp: $request->getClientIp(),
        minScore: 0.4
    );

    // 5. Reject on failure
    if (!$verification['success']) {
        return $this->json([
            'error' => 'Suspicious activity detected...',
            'details' => $verification['message']
        ], Response::HTTP_FORBIDDEN);
    }
}

// 6. Continue with existing authentication logic
```

### Frontend Changes

**Files**:
- `templates/login/index.html.twig`
- `templates/registration/register.html.twig`

Both templates now:

1. **Load reCAPTCHA Script** (conditionally):
```html
{% if recaptcha_enabled %}
<script src="https://www.google.com/recaptcha/api.js?render={{ recaptcha_site_key }}"></script>
{% endif %}
```

2. **Configure Constants**:
```javascript
const RECAPTCHA_ENABLED = {{ recaptcha_enabled ? 'true' : 'false' }};
const RECAPTCHA_SITE_KEY = '{{ recaptcha_site_key }}';
```

3. **Generate Token on Submit**:
```javascript
if (RECAPTCHA_ENABLED) {
    try {
        recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {
            action: 'login'  // matches backend expectation
        });
    } catch (error) {
        console.error('reCAPTCHA error:', error);
        showError('Security verification failed...');
        return;
    }
}
```

4. **Include Token in Request**:
```javascript
const response = await fetch('/api/login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        email: email,
        password: password,
        recaptcha_token: recaptchaToken
    })
});
```

5. **Handle Verification Errors**:
```javascript
if (!response.ok) {
    const data = await response.json();
    showError(data.error || 'Login failed...');
}
```

## Score Thresholds Explained

The minimum scores are set based on the security requirements of each action:

| Action | Score | Reasoning |
|--------|-------|-----------|
| Login | 0.4 | Lower threshold to avoid blocking legitimate users who may have shared IPs or unusual browsing patterns |
| Registration | 0.5 | Medium threshold to prevent bot account creation while allowing real users |
| Google Login | 0.4 | Lower threshold since Google already provides OAuth verification |

**Score Range**: 0.0 (very likely bot) to 1.0 (very likely human)

## Security Features

### 1. Server-Side Verification
- All tokens are verified server-side via Google's API
- Frontend verification is never trusted
- Secret key never exposed to client

### 2. Action Validation
- Each endpoint specifies expected action
- Tokens generated for one action cannot be used for another
- Prevents token replay attacks

### 3. IP Address Tracking
- User IP included in verification request
- Helps Google detect suspicious patterns
- Improves scoring accuracy

### 4. Token Expiration
- Tokens expire after 2 minutes
- Generated immediately before submission
- Each token can only be used once

### 5. Graceful Degradation
- reCAPTCHA can be disabled via environment variables
- When disabled, service returns success
- Allows development without keys

## Configuration

### Environment Variables

Located in `.env` file:

```env
RECAPTCHA_SITE_KEY=6Ld5ARosAAAAAP3Xo-vm2XcpUkCb-_j7_m5aR-k7
RECAPTCHA_SECRET_KEY=6Ld5ARosAAAAAFAFjBXrr1E6m6qKMGlKNvZadPBb
```

To disable reCAPTCHA (development):
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

## Error Handling

### HTTP 403 Responses

All reCAPTCHA failures return HTTP 403 with JSON:

```json
{
    "error": "User-friendly error message",
    "details": "Technical details (optional)"
}
```

### User-Friendly Error Messages

| Scenario | Message |
|----------|---------|
| Missing token | "Security verification is required. Please refresh the page and try again." |
| Low score | "Suspicious activity detected. Please try again or contact support." |
| reCAPTCHA error | "Security verification failed. Please refresh the page and try again." |
| Network error | "Connection error. Please try again." |

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
reCAPTCHA checks are skipped.

### Production Testing

1. Register domain at [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Use production keys
3. Test with real user interactions
4. Monitor logs for verification failures

## Monitoring

### Logs

reCAPTCHA verification results are logged via Symfony's logger:

```php
// Success
$this->logger->info('reCAPTCHA verification successful', [
    'action' => $action,
    'score' => $score
]);

// Failure
$this->logger->warning('reCAPTCHA verification failed', [
    'action' => $action,
    'score' => $score,
    'reason' => $error
]);
```

View logs:
```bash
tail -f var/log/dev.log | grep reCAPTCHA
```

### Metrics to Track

- **Verification success rate** - should be >95% for legitimate users
- **Average scores** - legitimate users typically score 0.7-1.0
- **Low score patterns** - may indicate attack attempts
- **Failed attempts by IP** - helps identify bot sources

## User Experience

### For Legitimate Users
- **Invisible** - no interaction required
- **Fast** - verification happens in background
- **Seamless** - no CAPTCHAs to solve
- **Reliable** - rarely blocks real users

### For Bots
- **Blocked** - low scores rejected
- **Logged** - attempts recorded
- **Rate-limited** - combined with other protections
- **Deterred** - makes automation harder

## Troubleshooting

### Issue: "Invalid site key"
**Solution**: Verify domain is registered in Google reCAPTCHA console

### Issue: All users getting low scores
**Solution**:
- Clear browser cookies
- Test from different IPs
- Lower score threshold temporarily
- Check for aggressive ad-blockers

### Issue: reCAPTCHA badge not showing
**Solution**:
- Check browser console for errors
- Verify script loaded correctly
- Check ad-blocker isn't blocking reCAPTCHA
- Confirm site key is correct

### Issue: "timeout-or-duplicate" error
**Solution**:
- Ensure token generated immediately before submission
- Don't reuse tokens
- Check network latency isn't causing delays

## Related Documentation

- **Full Setup Guide**: `RECAPTCHA_SETUP.md`
- **Service Implementation**: `src/Service/ReCaptchaService.php`
- **Example Usage**: `src/Controller/ExampleReCaptchaController.php`
- **Example Template**: `templates/recaptcha/example_form.html.twig`

## Support

- **Google reCAPTCHA Console**: https://www.google.com/recaptcha/admin
- **Official Docs**: https://developers.google.com/recaptcha/docs/v3
- **API Reference**: https://developers.google.com/recaptcha/docs/verify

---

**Integration Completed**: 2024
**Status**: Active and protecting all authentication endpoints
