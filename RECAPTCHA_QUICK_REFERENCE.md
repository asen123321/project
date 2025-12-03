# Google reCAPTCHA v3 - Quick Reference Guide

Essential commands, configurations, and troubleshooting for Google reCAPTCHA v3 in Symfony.

---

## 🚀 Quick Start

### 1. Get Your Keys

Visit: https://www.google.com/recaptcha/admin/create

- Select **reCAPTCHA v3**
- Add your domains (including `localhost` for development)
- Copy **Site Key** (public) and **Secret Key** (private)

### 2. Configure Environment

```env
# .env.local
RECAPTCHA_SITE_KEY=your_site_key_here
RECAPTCHA_SECRET_KEY=your_secret_key_here
```

### 3. Test Configuration

```bash
php bin/console app:recaptcha:debug --check-config
```

---

## 🔧 Configuration by Environment

### Development

```env
# .env.local
# Option 1: Test keys (always succeed)
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe

# Option 2: Disabled
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=

# Lower thresholds
RECAPTCHA_MIN_SCORE_LOGIN=0.3
RECAPTCHA_MIN_SCORE_REGISTER=0.4
RECAPTCHA_MIN_SCORE_BOOKING=0.4
```

### Production

```env
# .env.prod (NEVER commit this file!)
RECAPTCHA_SITE_KEY=your_production_site_key
RECAPTCHA_SECRET_KEY=your_production_secret_key

# Standard thresholds
RECAPTCHA_MIN_SCORE_LOGIN=0.4
RECAPTCHA_MIN_SCORE_REGISTER=0.5
RECAPTCHA_MIN_SCORE_BOOKING=0.5
```

---

## 📊 Score Thresholds

| Score Range | Interpretation | Recommended Action |
|-------------|----------------|-------------------|
| 0.9 - 1.0 | Very likely human | ✅ Allow |
| 0.7 - 0.9 | Likely human | ✅ Allow |
| 0.5 - 0.7 | Possibly human | ⚠️ Allow with logging |
| 0.3 - 0.5 | Suspicious | ⚠️ Allow with extra verification |
| 0.0 - 0.3 | Very likely bot | ❌ Block |

### Recommended Thresholds by Action

| Action | Threshold | Reason |
|--------|-----------|--------|
| Login | 0.4 | Lower to avoid blocking legitimate users |
| Registration | 0.5 | Medium - prevent spam accounts |
| Booking | 0.5 | Medium - prevent abuse |
| Google OAuth | 0.4 | Lower - OAuth already provides verification |

---

## 🛠️ Console Commands

### Debug & Check Configuration

```bash
# Check configuration
php bin/console app:recaptcha:debug --check-config

# Show overview
php bin/console app:recaptcha:debug

# View statistics
php bin/console app:recaptcha:debug --stats

# Reset statistics
php bin/console app:recaptcha:debug --reset-stats

# Test a token
php bin/console app:recaptcha:debug -t "token_here" -a "login"
```

### View Logs

```bash
# Real-time reCAPTCHA logs
tail -f var/log/recaptcha.log

# Only failures
tail -f var/log/recaptcha.log | grep "failed"

# Count today's failures
grep "Verification failed" var/log/recaptcha.log | grep "$(date +%Y-%m-%d)" | wc -l

# Find top failing IPs
grep "Verification failed" var/log/recaptcha.log | grep -oP 'ip_address:\K[^,]+' | sort | uniq -c | sort -nr | head -10
```

### Clear Cache

```bash
# Clear all cache
php bin/console cache:clear

# Warm up cache
php bin/console cache:warmup
```

---

## 🔐 Protected Endpoints

| Endpoint | Action | Threshold | Rate Limit |
|----------|--------|-----------|------------|
| `/api/login` | `login` | 0.4 | 5 per 15 min |
| `/api/register` | `register` | 0.5 | 3 per hour |
| `/booking/create` | `booking_create` | 0.5 | 10 per hour |
| `/api/google-login` | `google_login` | 0.4 | 10 per 15 min |

---

## 📈 Rate Limiting

### Check Rate Limit Status

```php
// In controller
$status = $this->rateLimiter->getStatus($request, 'login');
// Returns: ['accepted', 'limit', 'remaining', 'retry_after', ...]
```

### Rate Limit Configuration

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '15 minutes'
```

---

## 🐛 Troubleshooting

### "Invalid site key"

**Cause:** Wrong key or domain not registered

**Fix:**
```bash
# 1. Verify environment variables
php bin/console debug:container --env-vars | grep RECAPTCHA

# 2. Clear cache
php bin/console cache:clear

# 3. Check domain at https://www.google.com/recaptcha/admin
```

### All Users Getting Low Scores

**Cause:** Rapid testing, VPN, or new domain

**Fix:**
```env
# Temporarily lower threshold
RECAPTCHA_MIN_SCORE_LOGIN=0.3

# Wait 10-30 seconds between tests
# Clear cookies between tests
```

### "timeout-or-duplicate"

**Cause:** Token expired (>2 minutes) or reused

**Fix:**
```javascript
// Generate token immediately before submission
form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const token = await grecaptcha.execute(SITE_KEY, { action: 'submit' });
    // Immediately send to server
});
```

### reCAPTCHA Badge Not Showing

**Cause:** Script not loaded or ad-blocker

**Fix:**
```javascript
// Check in console
console.log('reCAPTCHA loaded:', typeof grecaptcha !== 'undefined');

// Disable ad-blocker
// Check CSP headers
```

---

## 📝 Testing Checklist

### Frontend Tests

- [ ] reCAPTCHA script loads without errors
- [ ] Badge appears in bottom-right corner
- [ ] No console errors
- [ ] Token generated successfully
- [ ] Token included in request payload
- [ ] Error handling works

**Test in Console:**
```javascript
grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'test' })
    .then(token => console.log('Token:', token.substring(0, 20) + '...'))
    .catch(error => console.error('Error:', error));
```

### Backend Tests

- [ ] ReCaptchaService configured
- [ ] Verification succeeds with valid token
- [ ] Verification fails with invalid token
- [ ] Verification fails with missing token
- [ ] Score threshold enforcement works
- [ ] Action validation works
- [ ] Logging captures all events
- [ ] Error responses user-friendly

**Test with curl:**
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password",
    "recaptcha_token": "valid_token_here"
  }'
```

### Integration Tests

- [ ] Login works with reCAPTCHA
- [ ] Registration works with reCAPTCHA
- [ ] Booking creation works with reCAPTCHA
- [ ] Google login works with reCAPTCHA
- [ ] Disabled reCAPTCHA allows requests
- [ ] Test keys work as expected

---

## 🔒 Security Checklist

### Critical Requirements

- [ ] **Secret keys NOT in version control**
  ```bash
  git log -p | grep -i "RECAPTCHA_SECRET"  # Should return nothing
  ```

- [ ] **.env files in .gitignore**
  ```bash
  grep "\.env\.local" .gitignore  # Should exist
  grep "\.env\.prod" .gitignore   # Should exist
  ```

- [ ] **Server-side verification only**
  ```php
  // ✅ CORRECT
  if ($this->recaptchaService->verify($token)['success']) { ... }

  // ❌ NEVER trust frontend
  if ($data['is_verified']) { ... }
  ```

- [ ] **No sensitive data in logs**
  ```php
  // ❌ NEVER log tokens or passwords
  $this->logger->info('Token', ['token' => $token]);

  // ✅ CORRECT
  $this->logger->info('Token received');
  ```

- [ ] **HTTPS enabled in production**
  ```apache
  # .htaccess
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
  ```

- [ ] **Rate limiting enabled**
  ```bash
  grep "rate_limiter:" config/packages/rate_limiter.yaml
  ```

- [ ] **Monitoring enabled**
  ```bash
  tail -f var/log/recaptcha.log  # Should show activity
  ```

---

## 📊 Monitoring & Metrics

### Key Metrics to Track

1. **Success Rate** (should be >95%)
2. **Average Score** (should be >0.7)
3. **Failed Verifications** (track spikes)
4. **Missing Tokens** (possible automation)
5. **Top Failing IPs** (possible attacks)

### Dashboard Queries

```sql
-- Success rate over last 24 hours
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN success = true THEN 1 ELSE 0 END) as successful,
    ROUND(100.0 * SUM(CASE WHEN success = true THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
FROM recaptcha_logs
WHERE timestamp > NOW() - INTERVAL '24 hours';

-- Average score by action
SELECT
    action,
    ROUND(AVG(score), 3) as avg_score,
    COUNT(*) as total
FROM recaptcha_logs
WHERE success = true
GROUP BY action;

-- Top failing IPs
SELECT
    ip_address,
    COUNT(*) as failures
FROM recaptcha_logs
WHERE success = false
GROUP BY ip_address
ORDER BY failures DESC
LIMIT 10;
```

---

## 🚨 Alert Thresholds

Set up alerts for:

| Metric | Threshold | Action |
|--------|-----------|--------|
| Success Rate | <90% | Investigate immediately |
| Average Score | <0.6 | Check for legitimate users being blocked |
| Failed Verifications | >100/hour | Possible attack |
| Missing Tokens | >50/hour | Automation attempt |
| Single IP Failures | >10/hour | Auto-block IP |

---

## 🧪 Testing Tokens

### Generate Test Token

```javascript
// In browser console on your site
grecaptcha.execute('YOUR_SITE_KEY', { action: 'test' })
    .then(token => {
        console.log('Token:', token);
        // Copy this token for testing
    });
```

### Test Token via Command

```bash
php bin/console app:recaptcha:debug -t "your_token_here" -a "test"
```

### Test Token via curl

```bash
curl -X POST https://www.google.com/recaptcha/api/siteverify \
  -d "secret=YOUR_SECRET_KEY&response=YOUR_TOKEN" | jq .
```

---

## 📚 Documentation Files

| File | Description |
|------|-------------|
| `RECAPTCHA_SETUP.md` | Complete setup guide |
| `RECAPTCHA_LOGIN_INTEGRATION.md` | Login integration details |
| `RECAPTCHA_BOOKING_INTEGRATION.md` | Booking integration details |
| `RECAPTCHA_TESTING_AND_SECURITY.md` | Testing & security guide |
| `RECAPTCHA_QUICK_REFERENCE.md` | This file |

---

## 🔗 Useful Links

- **Google reCAPTCHA Admin**: https://www.google.com/recaptcha/admin
- **Official Docs**: https://developers.google.com/recaptcha/docs/v3
- **API Reference**: https://developers.google.com/recaptcha/docs/verify
- **Privacy Policy**: https://policies.google.com/privacy
- **Terms of Service**: https://policies.google.com/terms

---

## ⚡ Common Code Snippets

### Frontend: Generate Token

```javascript
const token = await grecaptcha.execute(RECAPTCHA_SITE_KEY, {
    action: 'booking_create'
});
```

### Backend: Verify Token

```php
$verification = $this->recaptchaService->verify(
    token: $recaptchaToken,
    action: 'booking_create',
    remoteIp: $request->getClientIp(),
    minScore: 0.5
);

if (!$verification['success']) {
    return $this->json([
        'error' => 'Security verification failed'
    ], Response::HTTP_FORBIDDEN);
}
```

### Check Rate Limit

```php
$rateLimit = $this->rateLimiter->checkBookingLimit($request);

if (!$rateLimit['allowed']) {
    return $this->json([
        'error' => 'Too many requests',
        'retry_after' => $rateLimit['retry_after']
    ], Response::HTTP_TOO_MANY_REQUESTS);
}
```

### Log Failure

```php
$this->recaptchaMonitor->logFailure(
    action: 'booking_create',
    reason: 'low-score',
    score: $verification['score'],
    userId: (string) $this->getUser()->getId(),
    ip: $request->getClientIp()
);
```

---

## 🎯 Performance Tips

1. **Lazy load reCAPTCHA script** - Only load when needed
2. **Use async/defer** - Don't block page rendering
3. **Cache configuration** - Reduce database queries
4. **Set timeouts** - Prevent hanging requests (5 seconds)
5. **Monitor API response times** - Alert if >2 seconds

---

## 🔄 Deployment Checklist

### Before Deployment

- [ ] Test with production keys in staging
- [ ] Verify domain registered at Google
- [ ] Check environment variables
- [ ] Test all protected endpoints
- [ ] Verify logging works
- [ ] Enable monitoring/alerts
- [ ] Clear cache

### After Deployment

- [ ] Verify reCAPTCHA badge shows
- [ ] Test login flow
- [ ] Test registration flow
- [ ] Test booking flow
- [ ] Monitor logs for errors
- [ ] Check success rates
- [ ] Verify rate limiting works

---

## 📞 Support

For issues:
1. Check logs: `tail -f var/log/recaptcha.log`
2. Run debug: `php bin/console app:recaptcha:debug --check-config`
3. Review docs: See documentation files above
4. Check Google Console: https://www.google.com/recaptcha/admin

---

**Last Updated:** 2024
**Version:** 1.0
