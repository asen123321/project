# Google reCAPTCHA v3 Setup Guide

Complete guide for implementing Google reCAPTCHA v3 in your Symfony application with server-side verification.

## Table of Contents
- [What is reCAPTCHA v3?](#what-is-recaptcha-v3)
- [Obtaining reCAPTCHA Keys](#obtaining-recaptcha-keys)
- [Installation & Configuration](#installation--configuration)
- [Usage Examples](#usage-examples)
- [Frontend Integration](#frontend-integration)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Security Best Practices](#security-best-practices)

---

## What is reCAPTCHA v3?

reCAPTCHA v3 returns a **score** (0.0 - 1.0) for each request without user interaction:
- **1.0**: Very likely a legitimate user
- **0.0**: Very likely a bot

Unlike v2, it doesn't require users to solve challenges, providing a seamless user experience.

---

## Obtaining reCAPTCHA Keys

### Step 1: Go to Google reCAPTCHA Admin Console

Visit: [https://www.google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create)

### Step 2: Register a New Site

Fill in the registration form:

1. **Label**: Give your site a name (e.g., "My Symfony App")

2. **reCAPTCHA type**: Select **reCAPTCHA v3**

3. **Domains**: Add your domains (one per line)
   ```
   localhost           (for development)
   yourdomain.com      (for production)
   www.yourdomain.com  (if using www)
   ```

4. **Owners**: Add email addresses of owners (optional)

5. **Accept Terms**: Check the box to accept reCAPTCHA Terms of Service

6. Click **Submit**

### Step 3: Copy Your Keys

After registration, you'll see two keys:

- **Site Key** (Public): Used in frontend JavaScript
  - Example: `6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI`
  - ✅ Safe to expose in frontend code

- **Secret Key** (Private): Used in backend verification
  - Example: `6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe`
  - ⚠️ **NEVER** expose in frontend code

### Testing Keys (Development Only)

Google provides test keys that always succeed:

```
Site Key (Public):
6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI

Secret Key (Private):
6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

⚠️ **Do NOT use test keys in production!**

---

## Installation & Configuration

### 1. Environment Variables

Your `.env` file already has reCAPTCHA configuration. Update with your actual keys:

```env
###> Google reCAPTCHA v3 ###
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
###< Google reCAPTCHA v3 ###
```

**For production**, add to `.env.local` or `.env.prod`:
```env
RECAPTCHA_SITE_KEY=your_production_site_key
RECAPTCHA_SECRET_KEY=your_production_secret_key
```

### 2. Service Configuration

The service is already configured in `config/services.yaml`:

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

### 3. ReCaptchaService Class

The service is located at `src/Service/ReCaptchaService.php` and provides:

- ✅ Server-side token verification
- ✅ Score-based validation
- ✅ Action validation
- ✅ Error handling and logging
- ✅ Automatic disable when keys are missing

---

## Usage Examples

### Example 1: Basic Form Verification

```php
use App\Service\ReCaptchaService;
use Symfony\Component\HttpFoundation\Request;

class ContactController extends AbstractController
{
    public function __construct(
        private ReCaptchaService $recaptchaService
    ) {}

    #[Route('/api/contact', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verify reCAPTCHA
        $verification = $this->recaptchaService->verify(
            token: $data['recaptcha_token'],
            action: 'contact_submit',
            remoteIp: $request->getClientIp()
        );

        if (!$verification['success']) {
            return new JsonResponse([
                'error' => 'Security verification failed'
            ], 403);
        }

        // Process form...

        return new JsonResponse(['success' => true]);
    }
}
```

### Example 2: Login Protection

```php
#[Route('/api/login', methods: ['POST'])]
public function login(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    // Verify reCAPTCHA with lower threshold for login
    $verification = $this->recaptchaService->verify(
        token: $data['recaptcha_token'],
        action: 'login',
        remoteIp: $request->getClientIp(),
        minScore: 0.3  // Lower threshold for login
    );

    if (!$verification['success']) {
        return new JsonResponse([
            'error' => 'Suspicious activity detected'
        ], 403);
    }

    // Proceed with authentication...
}
```

### Example 3: Using the Score

```php
$verification = $this->recaptchaService->verify($token);

if ($verification['success']) {
    $score = $verification['score'];

    if ($score >= 0.9) {
        // Very likely human - fast track
    } elseif ($score >= 0.5) {
        // Likely human - normal processing
    } elseif ($score >= 0.3) {
        // Suspicious - add extra validation
    } else {
        // Very suspicious - reject or require additional verification
    }
}
```

---

## Frontend Integration

### Method 1: Standard Form with JavaScript

```html
<!-- Include reCAPTCHA script in <head> or before </body> -->
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>

<!-- Your form -->
<form id="myForm">
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <button type="submit">Submit</button>
</form>

<script>
document.getElementById('myForm').addEventListener('submit', async (e) => {
    e.preventDefault();

    // Execute reCAPTCHA
    const token = await grecaptcha.execute('YOUR_SITE_KEY', {
        action: 'form_submit'
    });

    // Add token to form data
    const formData = new FormData(e.target);

    // Send to server
    const response = await fetch('/api/submit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: formData.get('name'),
            email: formData.get('email'),
            recaptcha_token: token
        })
    });

    const result = await response.json();
    // Handle response...
});
</script>
```

### Method 2: Twig Template

```twig
{# In your template #}
{% block javascripts %}
    <script src="https://www.google.com/recaptcha/api.js?render={{ recaptcha_site_key }}"></script>

    <script>
        async function submitForm() {
            const token = await grecaptcha.execute('{{ recaptcha_site_key }}', {
                action: 'submit'
            });

            // Use token in your AJAX request
            const response = await fetch('/api/endpoint', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    recaptcha_token: token,
                    // ... other data
                })
            });
        }
    </script>
{% endblock %}
```

### Method 3: Passing Site Key from Controller

```php
// In your controller
public function showForm(): Response
{
    return $this->render('form.html.twig', [
        'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
        'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
    ]);
}
```

---

## Testing

### Testing with Development Keys

Use Google's test keys in development:

```env
# .env.local for development
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

These keys **always return success** with a score of 1.0.

### Testing Without reCAPTCHA

Leave keys empty to disable reCAPTCHA:

```env
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

The service will automatically disable and always return success.

### Manual Testing Checklist

- [ ] Form loads without errors
- [ ] reCAPTCHA badge appears in bottom-right corner
- [ ] Form submission generates a token
- [ ] Token is sent to backend
- [ ] Backend verifies token successfully
- [ ] Low scores (bots) are rejected
- [ ] High scores (humans) are accepted

### Testing Score Thresholds

```php
// In your test controller
public function testRecaptcha(): Response
{
    $token = 'test_token_here';

    // Test with different thresholds
    $result1 = $this->recaptchaService->verify($token, minScore: 0.3);
    $result2 = $this->recaptchaService->verify($token, minScore: 0.5);
    $result3 = $this->recaptchaService->verify($token, minScore: 0.7);

    return new JsonResponse([
        'threshold_0.3' => $result1['success'],
        'threshold_0.5' => $result2['success'],
        'threshold_0.7' => $result3['success'],
    ]);
}
```

---

## Troubleshooting

### Issue: "Invalid site key"

**Cause**: Wrong site key or domain not registered

**Solution**:
1. Verify site key matches your Google reCAPTCHA console
2. Check that your domain is registered in reCAPTCHA admin
3. For localhost, make sure "localhost" is in the domains list

### Issue: "Token validation failed"

**Cause**: Token expired or already used

**Solution**:
- reCAPTCHA tokens expire after 2 minutes
- Each token can only be used once
- Generate a new token for each submission

### Issue: "timeout-or-duplicate" error

**Cause**: Token is too old or has been used before

**Solution**:
```javascript
// Generate token right before submission
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = await grecaptcha.execute(siteKey, {action: 'submit'});
    // Immediately send to server
});
```

### Issue: All requests get low scores

**Cause**: Suspicious patterns detected by Google

**Solutions**:
- Clear browser cookies/cache
- Test from different IPs
- Avoid automated testing tools (Selenium, etc.)
- Don't submit too frequently
- Use real user interactions

### Issue: reCAPTCHA badge not showing

**Cause**: Script not loaded or blocked

**Solution**:
1. Check browser console for errors
2. Verify script is loaded: `<script src="https://www.google.com/recaptcha/api.js?render=YOUR_KEY"></script>`
3. Check ad-blockers aren't blocking reCAPTCHA
4. Verify site key is correct

### Debugging Tips

Check the logs:
```bash
tail -f var/log/dev.log | grep reCAPTCHA
```

Enable debug mode in service:
```php
// Add to ReCaptchaService for debugging
$this->logger->debug('reCAPTCHA verification details', [
    'token' => substr($token, 0, 20) . '...',
    'action' => $action,
    'score' => $score,
]);
```

---

## Security Best Practices

### ✅ DO

1. **Always verify on server-side**
   - Never trust frontend verification alone
   - Use the ReCaptchaService for all verifications

2. **Use environment variables**
   - Store keys in `.env` files
   - Never commit keys to version control

3. **Validate actions**
   ```php
   $this->recaptchaService->verify($token, action: 'expected_action');
   ```

4. **Log failed attempts**
   - Monitor for patterns
   - Detect potential attacks

5. **Use appropriate score thresholds**
   - Login: 0.3 - 0.4 (lower threshold)
   - Contact forms: 0.5 (medium threshold)
   - Financial transactions: 0.7 - 0.8 (higher threshold)

6. **Include user IP**
   ```php
   $this->recaptchaService->verify($token, remoteIp: $request->getClientIp());
   ```

### ❌ DON'T

1. **Never expose secret key in frontend**
   ```javascript
   // ❌ NEVER DO THIS
   const SECRET_KEY = '6LeIxAcTAAAAAGG...';
   ```

2. **Don't skip server-side verification**
   ```php
   // ❌ BAD - trusting frontend
   if ($data['recaptcha_verified']) { ... }

   // ✅ GOOD - verifying on server
   if ($this->recaptchaService->verify($token)['success']) { ... }
   ```

3. **Don't reuse tokens**
   - Generate a new token for each action

4. **Don't use same keys for multiple sites**
   - Create separate keys for each domain

5. **Don't ignore low scores**
   ```php
   // ❌ BAD
   $this->recaptchaService->verify($token); // Ignores score

   // ✅ GOOD
   $result = $this->recaptchaService->verify($token, minScore: 0.5);
   if (!$result['success']) { /* handle */ }
   ```

---

## Advanced Configuration

### Custom Score Thresholds by Action

```php
public function getScoreThreshold(string $action): float
{
    return match($action) {
        'login' => 0.3,
        'contact' => 0.5,
        'purchase' => 0.7,
        'admin_action' => 0.8,
        default => 0.5,
    };
}

// Usage
$threshold = $this->getScoreThreshold('login');
$result = $this->recaptchaService->verify($token, action: 'login', minScore: $threshold);
```

### Rate Limiting Integration

```php
// Combine with rate limiting for extra security
if (!$verification['success'] || $verification['score'] < 0.5) {
    // Implement rate limiting for suspicious requests
    $this->rateLimiter->hit($request->getClientIp());
}
```

---

## Quick Reference

### ReCaptchaService Methods

```php
// Verify token
$result = $recaptchaService->verify(
    token: string,           // Required: reCAPTCHA token
    action: ?string,         // Optional: Expected action
    remoteIp: ?string,       // Optional: User IP
    minScore: float          // Optional: Min score (default: 0.5)
);

// Get site key (safe for frontend)
$siteKey = $recaptchaService->getSiteKey();

// Check if enabled
$enabled = $recaptchaService->isEnabled();
```

### Response Structure

```php
// Success response
[
    'success' => true,
    'score' => 0.9,           // 0.0 - 1.0
    'action' => 'submit',
    'challenge_ts' => '2024-01-01T12:00:00Z',
    'hostname' => 'yourdomain.com'
]

// Failure response
[
    'success' => false,
    'error' => 'low-score',
    'message' => 'reCAPTCHA verification failed',
    'score' => 0.2
]
```

---

## Support & Resources

- **Google reCAPTCHA Console**: [https://www.google.com/recaptcha/admin](https://www.google.com/recaptcha/admin)
- **Official Documentation**: [https://developers.google.com/recaptcha/docs/v3](https://developers.google.com/recaptcha/docs/v3)
- **API Reference**: [https://developers.google.com/recaptcha/docs/verify](https://developers.google.com/recaptcha/docs/verify)

---

## Example Files in This Project

- **Service**: `src/Service/ReCaptchaService.php`
- **Example Controller**: `src/Controller/ExampleReCaptchaController.php`
- **Example Template**: `templates/recaptcha/example_form.html.twig`
- **Configuration**: `config/services.yaml`
- **Environment**: `.env` (update with your keys)

---

*Last Updated: 2024*
