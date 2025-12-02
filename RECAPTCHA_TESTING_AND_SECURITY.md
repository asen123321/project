# Google reCAPTCHA v3 - Testing & Security Guide

Comprehensive guide for testing, securing, monitoring, and troubleshooting Google reCAPTCHA v3 implementation in Symfony.

## Table of Contents

- [Testing in Development](#testing-in-development)
- [Multi-Environment Configuration](#multi-environment-configuration)
- [Security Best Practices Checklist](#security-best-practices-checklist)
- [Monitoring and Logging](#monitoring-and-logging)
- [Rate Limiting](#rate-limiting)
- [Troubleshooting](#troubleshooting)
- [Performance Optimization](#performance-optimization)

---

## Testing in Development

### Option 1: Using Google Test Keys (Recommended for Development)

Google provides test keys that always return success with a score of 1.0.

**Configuration:**

```env
# .env.local (for development)
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

**Behavior:**
- ✅ Always returns `success: true`
- ✅ Always returns `score: 1.0`
- ✅ Works on any domain (including localhost)
- ✅ No need to register domain with Google
- ⚠️ **NEVER use in production!**

**When to use:**
- Local development
- Automated testing
- CI/CD pipelines
- Quick prototyping

### Option 2: Disable reCAPTCHA Completely

For development where you don't want any reCAPTCHA overhead.

**Configuration:**

```env
# .env.local (for development)
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
```

**Behavior:**
- ✅ `ReCaptchaService->isEnabled()` returns `false`
- ✅ All verification checks skipped
- ✅ All requests proceed without reCAPTCHA
- ✅ No external API calls to Google
- ⚠️ No bot protection (development only!)

**When to use:**
- Early development stages
- Testing non-security features
- Debugging unrelated issues
- Offline development

### Option 3: Using Real Keys in Development

For testing actual reCAPTCHA behavior and scoring.

**Setup:**

1. **Register localhost at Google reCAPTCHA Console:**
   - Visit: https://www.google.com/recaptcha/admin/create
   - Create new site
   - Select reCAPTCHA v3
   - Add domain: `localhost`
   - Copy site key and secret key

2. **Configure development environment:**

```env
# .env.local
RECAPTCHA_SITE_KEY=your_dev_site_key_here
RECAPTCHA_SECRET_KEY=your_dev_secret_key_here
```

**Behavior:**
- ✅ Real scoring (may vary 0.0-1.0)
- ✅ Actual Google API calls
- ✅ Tests real-world scenarios
- ⚠️ May get lower scores during rapid testing

**When to use:**
- Testing score thresholds
- Verifying error handling
- Pre-production testing
- Debugging score-related issues

### Testing Checklist

Use this checklist when testing reCAPTCHA integration:

#### Frontend Tests

- [ ] reCAPTCHA script loads without errors
- [ ] reCAPTCHA badge appears in bottom-right corner
- [ ] Badge shows correct site key
- [ ] No console errors during page load
- [ ] Token generation succeeds
- [ ] Token included in request payload
- [ ] Error handling works for token generation failure

**Test Script:**

```javascript
// Open browser console and run:
console.log('reCAPTCHA loaded:', typeof grecaptcha !== 'undefined');
console.log('Site key:', RECAPTCHA_SITE_KEY);
console.log('Enabled:', RECAPTCHA_ENABLED);

// Test token generation
grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'test' })
    .then(token => console.log('Token generated:', token.substring(0, 20) + '...'))
    .catch(error => console.error('Token generation failed:', error));
```

#### Backend Tests

- [ ] ReCaptchaService properly configured
- [ ] Service injection works in controllers
- [ ] Verification succeeds with valid token
- [ ] Verification fails with invalid token
- [ ] Verification fails with missing token
- [ ] Score threshold enforcement works
- [ ] Action validation works
- [ ] Logging captures all events
- [ ] Error responses are user-friendly

**Manual Test:**

```bash
# Test with curl (replace with actual token)
curl -X POST http://localhost:8000/booking/create \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "stylist_id": 1,
    "service_id": 1,
    "booking_date": "2024-12-25",
    "booking_time": "10:00",
    "recaptcha_token": "valid_token_here"
  }'
```

#### Integration Tests

- [ ] Login with reCAPTCHA works
- [ ] Registration with reCAPTCHA works
- [ ] Booking creation with reCAPTCHA works
- [ ] Google login with reCAPTCHA works
- [ ] All protected endpoints verify tokens
- [ ] Disabled reCAPTCHA allows all requests
- [ ] Test keys work as expected

---

## Multi-Environment Configuration

### Environment Files Structure

```
project/
├── .env                    # Default values (committed)
├── .env.local             # Local overrides (not committed)
├── .env.dev               # Development (optional)
├── .env.staging           # Staging (not committed)
├── .env.prod              # Production (not committed)
└── .env.test              # Testing (committed)
```

### Development Environment (.env.local)

```env
###> Environment ###
APP_ENV=dev
APP_DEBUG=true
###< Environment ###

###> Google reCAPTCHA v3 - DEVELOPMENT ###
# Option 1: Test keys (always succeed)
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe

# Option 2: Disabled (empty values)
# RECAPTCHA_SITE_KEY=
# RECAPTCHA_SECRET_KEY=

# Option 3: Real dev keys
# RECAPTCHA_SITE_KEY=your_dev_site_key
# RECAPTCHA_SECRET_KEY=your_dev_secret_key
###< Google reCAPTCHA v3 ###
```

### Staging Environment (.env.staging)

```env
###> Environment ###
APP_ENV=staging
APP_DEBUG=false
###< Environment ###

###> Google reCAPTCHA v3 - STAGING ###
# Use real staging keys with staging domain registered
RECAPTCHA_SITE_KEY=your_staging_site_key_here
RECAPTCHA_SECRET_KEY=your_staging_secret_key_here
###< Google reCAPTCHA v3 ###

###> Staging-specific settings ###
# Lower threshold for easier testing
RECAPTCHA_MIN_SCORE_LOGIN=0.3
RECAPTCHA_MIN_SCORE_REGISTER=0.4
RECAPTCHA_MIN_SCORE_BOOKING=0.4
###< Staging-specific settings ###
```

### Production Environment (.env.prod)

```env
###> Environment ###
APP_ENV=prod
APP_DEBUG=false
###< Environment ###

###> Google reCAPTCHA v3 - PRODUCTION ###
# CRITICAL: Use production keys only
RECAPTCHA_SITE_KEY=your_production_site_key_here
RECAPTCHA_SECRET_KEY=your_production_secret_key_here
###< Google reCAPTCHA v3 ###

###> Production-specific settings ###
# Standard thresholds
RECAPTCHA_MIN_SCORE_LOGIN=0.4
RECAPTCHA_MIN_SCORE_REGISTER=0.5
RECAPTCHA_MIN_SCORE_BOOKING=0.5
###< Production-specific settings ###
```

### Test Environment (.env.test)

```env
###> Environment ###
APP_ENV=test
###< Environment ###

###> Google reCAPTCHA v3 - TESTING ###
# Disabled for automated tests
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=
###< Google reCAPTCHA v3 ###
```

### Environment-Specific Score Thresholds

**Enhanced ReCaptchaService with configurable thresholds:**

```php
<?php
// src/Service/ReCaptchaService.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReCaptchaService
{
    private const GOOGLE_RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    // Default thresholds (can be overridden by env vars)
    private const DEFAULT_MIN_SCORE = 0.5;

    private bool $enabled;
    private string $siteKey;
    private string $secretKey;
    private array $scoreThresholds;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $recaptchaSiteKey,
        string $recaptchaSecretKey
    ) {
        $this->siteKey = $recaptchaSiteKey;
        $this->secretKey = $recaptchaSecretKey;
        $this->enabled = !empty($recaptchaSiteKey) && !empty($recaptchaSecretKey);

        // Load environment-specific thresholds
        $this->scoreThresholds = [
            'login' => (float) ($_ENV['RECAPTCHA_MIN_SCORE_LOGIN'] ?? 0.4),
            'register' => (float) ($_ENV['RECAPTCHA_MIN_SCORE_REGISTER'] ?? 0.5),
            'booking_create' => (float) ($_ENV['RECAPTCHA_MIN_SCORE_BOOKING'] ?? 0.5),
            'google_login' => (float) ($_ENV['RECAPTCHA_MIN_SCORE_LOGIN'] ?? 0.4),
            'default' => self::DEFAULT_MIN_SCORE,
        ];

        if ($this->enabled) {
            $this->logger->info('reCAPTCHA service initialized', [
                'enabled' => true,
                'environment' => $_ENV['APP_ENV'] ?? 'unknown',
                'thresholds' => $this->scoreThresholds
            ]);
        } else {
            $this->logger->warning('reCAPTCHA service disabled - no keys configured');
        }
    }

    public function verify(
        string $token,
        ?string $action = null,
        ?string $remoteIp = null,
        ?float $minScore = null
    ): array {
        if (!$this->enabled) {
            return [
                'success' => true,
                'disabled' => true,
                'message' => 'reCAPTCHA is disabled'
            ];
        }

        // Use action-specific threshold if not provided
        if ($minScore === null) {
            $minScore = $this->scoreThresholds[$action] ?? $this->scoreThresholds['default'];
        }

        // Validate token
        if (empty($token)) {
            $this->logger->warning('reCAPTCHA verification failed: Empty token', [
                'action' => $action,
                'remote_ip' => $remoteIp
            ]);

            return [
                'success' => false,
                'error' => 'missing-input-response',
                'message' => 'reCAPTCHA token is required'
            ];
        }

        try {
            // Call Google API
            $response = $this->httpClient->request('POST', self::GOOGLE_RECAPTCHA_VERIFY_URL, [
                'body' => [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp
                ],
                'timeout' => 5 // 5 second timeout
            ]);

            $content = $response->toArray(false);

            // Check Google's success flag
            if (!($content['success'] ?? false)) {
                $errorCodes = $content['error-codes'] ?? [];

                $this->logger->warning('reCAPTCHA verification failed: Google returned error', [
                    'action' => $action,
                    'error_codes' => $errorCodes,
                    'remote_ip' => $remoteIp
                ]);

                return [
                    'success' => false,
                    'error' => implode(', ', $errorCodes),
                    'message' => $this->getErrorMessage($errorCodes)
                ];
            }

            $score = $content['score'] ?? 0;
            $returnedAction = $content['action'] ?? null;

            // Validate action if specified
            if ($action !== null && $returnedAction !== $action) {
                $this->logger->warning('reCAPTCHA action mismatch', [
                    'expected' => $action,
                    'received' => $returnedAction,
                    'score' => $score,
                    'remote_ip' => $remoteIp
                ]);

                return [
                    'success' => false,
                    'error' => 'action-mismatch',
                    'message' => 'Invalid reCAPTCHA action',
                    'score' => $score
                ];
            }

            // Validate score
            if ($score < $minScore) {
                $this->logger->warning('reCAPTCHA score below threshold', [
                    'action' => $action,
                    'score' => $score,
                    'threshold' => $minScore,
                    'remote_ip' => $remoteIp,
                    'hostname' => $content['hostname'] ?? null
                ]);

                return [
                    'success' => false,
                    'error' => 'low-score',
                    'message' => 'reCAPTCHA verification failed - suspicious activity detected',
                    'score' => $score,
                    'threshold' => $minScore
                ];
            }

            // Success!
            $this->logger->info('reCAPTCHA verification successful', [
                'action' => $action,
                'score' => $score,
                'threshold' => $minScore,
                'remote_ip' => $remoteIp
            ]);

            return [
                'success' => true,
                'score' => $score,
                'action' => $returnedAction,
                'challenge_ts' => $content['challenge_ts'] ?? null,
                'hostname' => $content['hostname'] ?? null
            ];

        } catch (\Exception $e) {
            $this->logger->error('reCAPTCHA verification exception', [
                'action' => $action,
                'error' => $e->getMessage(),
                'remote_ip' => $remoteIp
            ]);

            return [
                'success' => false,
                'error' => 'network-error',
                'message' => 'Failed to verify reCAPTCHA - network error'
            ];
        }
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getScoreThreshold(string $action): float
    {
        return $this->scoreThresholds[$action] ?? $this->scoreThresholds['default'];
    }

    private function getErrorMessage(array $errorCodes): string
    {
        if (empty($errorCodes)) {
            return 'Unknown reCAPTCHA error';
        }

        $firstError = $errorCodes[0];

        return match ($firstError) {
            'missing-input-secret' => 'reCAPTCHA configuration error',
            'invalid-input-secret' => 'reCAPTCHA configuration error',
            'missing-input-response' => 'reCAPTCHA token is missing',
            'invalid-input-response' => 'reCAPTCHA token is invalid or expired',
            'bad-request' => 'Invalid reCAPTCHA request',
            'timeout-or-duplicate' => 'reCAPTCHA token has expired or was already used',
            default => 'reCAPTCHA verification failed'
        };
    }
}
```

### Deployment Checklist by Environment

#### Development
- [ ] Use test keys or disable reCAPTCHA
- [ ] Enable debug logging
- [ ] Lower score thresholds for testing
- [ ] No rate limiting needed

#### Staging
- [ ] Use staging-specific keys
- [ ] Register staging domain with Google
- [ ] Medium score thresholds
- [ ] Enable comprehensive logging
- [ ] Light rate limiting for testing

#### Production
- [ ] Use production-specific keys
- [ ] Register production domain(s) with Google
- [ ] Standard/high score thresholds
- [ ] Enable error logging only (not debug)
- [ ] Full rate limiting enabled
- [ ] Monitor continuously

---

## Security Best Practices Checklist

### Critical Security Requirements

#### ✅ Secret Key Protection

- [ ] **NEVER commit secret keys to version control**
  ```bash
  # Ensure .env.local and .env.prod are in .gitignore
  echo ".env.local" >> .gitignore
  echo ".env.prod" >> .gitignore
  echo ".env.staging" >> .gitignore
  ```

- [ ] **NEVER expose secret key in frontend code**
  ```javascript
  // ❌ NEVER DO THIS
  const SECRET_KEY = '6LeIxAcTAAAAAGG...';

  // ✅ CORRECT - only site key in frontend
  const SITE_KEY = '{{ recaptcha_site_key }}';
  ```

- [ ] **NEVER log secret keys**
  ```php
  // ❌ NEVER DO THIS
  $this->logger->info('Keys', ['secret' => $this->secretKey]);

  // ✅ CORRECT - log only non-sensitive data
  $this->logger->info('reCAPTCHA enabled', ['has_keys' => $this->enabled]);
  ```

- [ ] **Use environment variables for all keys**
  ```php
  // ✅ CORRECT
  $recaptchaSiteKey = $_ENV['RECAPTCHA_SITE_KEY'];
  ```

#### ✅ Server-Side Verification

- [ ] **Always verify tokens server-side**
  ```php
  // ❌ NEVER trust frontend
  if ($data['is_verified']) { ... }

  // ✅ CORRECT - verify server-side
  if ($this->recaptchaService->verify($token)['success']) { ... }
  ```

- [ ] **Never skip verification in production**
  ```php
  // ✅ CORRECT - check if enabled
  if ($this->recaptchaService->isEnabled()) {
      $verification = $this->recaptchaService->verify($token);
      if (!$verification['success']) {
          return $this->json(['error' => '...'], 403);
      }
  }
  ```

- [ ] **Validate action names match**
  ```php
  // ✅ CORRECT
  $verification = $this->recaptchaService->verify(
      token: $token,
      action: 'booking_create' // Must match frontend
  );
  ```

#### ✅ No Sensitive Data Exposure

- [ ] **Never log full reCAPTCHA tokens**
  ```php
  // ❌ NEVER DO THIS
  $this->logger->info('Token', ['token' => $token]);

  // ✅ CORRECT - log only first few chars or don't log at all
  $this->logger->debug('Token received', ['token_prefix' => substr($token, 0, 10)]);
  ```

- [ ] **Never log user passwords (obviously)**
  ```php
  // ❌ NEVER DO THIS
  $this->logger->info('Login attempt', ['password' => $password]);

  // ✅ CORRECT - log only non-sensitive data
  $this->logger->info('Login attempt', ['email' => $email, 'ip' => $ip]);
  ```

- [ ] **Never expose booking details in console**
  ```javascript
  // ❌ AVOID THIS
  console.log('Booking data:', bookingData);

  // ✅ BETTER - remove in production or use debug mode
  if (DEBUG_MODE) {
      console.debug('Booking submitted');
  }
  ```

- [ ] **Sanitize error messages for users**
  ```php
  // ❌ NEVER expose technical details
  return $this->json(['error' => 'SQL error: ' . $e->getMessage()], 500);

  // ✅ CORRECT - user-friendly message, log details
  $this->logger->error('Booking creation failed', ['error' => $e->getMessage()]);
  return $this->json(['error' => 'Failed to create booking. Please try again.'], 500);
  ```

#### ✅ Token Handling

- [ ] **Generate new token for each action**
  ```javascript
  // ✅ CORRECT - generate on every submit
  const token = await grecaptcha.execute(SITE_KEY, { action: 'login' });
  ```

- [ ] **Never reuse tokens**
  ```javascript
  // ❌ NEVER DO THIS
  let cachedToken;
  if (!cachedToken) {
      cachedToken = await grecaptcha.execute(SITE_KEY, { action: 'login' });
  }

  // ✅ CORRECT - new token each time
  const token = await grecaptcha.execute(SITE_KEY, { action: 'login' });
  ```

- [ ] **Handle token expiration (2 minutes)**
  ```javascript
  // ✅ CORRECT - generate immediately before use
  form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const token = await grecaptcha.execute(...); // Fresh token
      // Immediately send to server
  });
  ```

#### ✅ HTTPS Requirements

- [ ] **Use HTTPS in production**
  ```yaml
  # config/packages/framework.yaml
  framework:
      router:
          default_uri: 'https://yourdomain.com'
  ```

- [ ] **Redirect HTTP to HTTPS**
  ```apache
  # .htaccess
  RewriteEngine On
  RewriteCond %{HTTPS} off
  RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
  ```

#### ✅ Rate Limiting (see dedicated section below)

- [ ] Implement rate limiting for protected endpoints
- [ ] Track failed verification attempts
- [ ] Block IPs with excessive failures
- [ ] Monitor for attack patterns

### Security Audit Commands

```bash
# Check for exposed secrets in git history
git log -p | grep -i "RECAPTCHA_SECRET"

# Check for committed .env files
git log --all --full-history -- .env.local
git log --all --full-history -- .env.prod

# Search codebase for hardcoded secrets
grep -r "6Le" src/ templates/ public/
grep -r "RECAPTCHA_SECRET" src/ templates/

# Check file permissions
ls -la .env*

# Ensure .env files are not web-accessible
curl http://localhost:8000/.env
curl http://localhost:8000/.env.local
```

---

## Monitoring and Logging

### Enhanced Logging Service

Create a dedicated service for reCAPTCHA monitoring:

```php
<?php
// src/Service/ReCaptchaMonitorService.php

namespace App\Service;

use Psr\Log\LoggerInterface;

class ReCaptchaMonitorService
{
    private const LOG_CHANNEL = 'recaptcha';

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Log successful verification
     */
    public function logSuccess(
        string $action,
        float $score,
        ?string $userId = null,
        ?string $ip = null
    ): void {
        $this->logger->info('[reCAPTCHA] Verification successful', [
            'action' => $action,
            'score' => $score,
            'user_id' => $userId,
            'ip_address' => $this->maskIp($ip),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Log failed verification with detailed reason
     */
    public function logFailure(
        string $action,
        string $reason,
        ?float $score = null,
        ?string $userId = null,
        ?string $ip = null,
        ?array $additionalContext = []
    ): void {
        $context = array_merge([
            'action' => $action,
            'reason' => $reason,
            'score' => $score,
            'user_id' => $userId,
            'ip_address' => $this->maskIp($ip),
            'timestamp' => date('Y-m-d H:i:s')
        ], $additionalContext);

        $this->logger->warning('[reCAPTCHA] Verification failed', $context);

        // Also increment failure counter for this IP
        $this->incrementFailureCount($ip, $action);
    }

    /**
     * Log missing token attempt
     */
    public function logMissingToken(
        string $action,
        ?string $userId = null,
        ?string $ip = null
    ): void {
        $this->logger->warning('[reCAPTCHA] Missing token', [
            'action' => $action,
            'user_id' => $userId,
            'ip_address' => $this->maskIp($ip),
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'HIGH' // Possible automation attempt
        ]);
    }

    /**
     * Log low score patterns
     */
    public function logLowScorePattern(
        string $ip,
        array $recentScores
    ): void {
        $avgScore = array_sum($recentScores) / count($recentScores);

        $this->logger->warning('[reCAPTCHA] Low score pattern detected', [
            'ip_address' => $this->maskIp($ip),
            'recent_scores' => $recentScores,
            'average_score' => round($avgScore, 2),
            'sample_size' => count($recentScores),
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'HIGH'
        ]);
    }

    /**
     * Log potential attack
     */
    public function logPotentialAttack(
        string $ip,
        string $pattern,
        array $evidence
    ): void {
        $this->logger->error('[reCAPTCHA] Potential attack detected', [
            'ip_address' => $this->maskIp($ip),
            'attack_pattern' => $pattern,
            'evidence' => $evidence,
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'CRITICAL',
            'action_required' => 'Review and consider IP ban'
        ]);
    }

    /**
     * Get failure statistics for monitoring dashboard
     */
    public function getFailureStats(int $hours = 24): array
    {
        // This would query your logging system or database
        // Example implementation with cache
        $cacheKey = "recaptcha_stats_{$hours}h";

        // In real implementation, parse logs or query database
        return [
            'total_verifications' => 1234,
            'successful' => 1150,
            'failed' => 84,
            'success_rate' => 93.2,
            'average_score' => 0.78,
            'low_scores' => 45,
            'missing_tokens' => 15,
            'top_failing_ips' => [
                '192.168.1.xxx' => 12,
                '10.0.0.xxx' => 8,
            ],
            'period' => $hours . ' hours'
        ];
    }

    /**
     * Mask IP address for privacy (GDPR compliance)
     */
    private function maskIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        // IPv4: mask last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = 'xxx';
            return implode('.', $parts);
        }

        // IPv6: mask last section
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = 'xxxx';
            return implode(':', $parts);
        }

        return 'invalid_ip';
    }

    /**
     * Increment failure counter (implement with cache or database)
     */
    private function incrementFailureCount(?string $ip, string $action): void
    {
        if ($ip === null) {
            return;
        }

        // Example with Symfony cache
        // $cacheKey = "recaptcha_failures_{$ip}_{$action}";
        // $this->cache->increment($cacheKey);

        // Could also store in database for persistence
    }
}
```

### Configure Dedicated Log Channel

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - recaptcha

when@dev:
    monolog:
        handlers:
            # Main log
            main:
                type: stream
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: debug
                channels: ["!event", "!recaptcha"]

            # Dedicated reCAPTCHA log
            recaptcha:
                type: stream
                path: "%kernel.logs_dir%/recaptcha.log"
                level: info
                channels: ["recaptcha"]

            # Console output for development
            console:
                type: console
                channels: ["recaptcha"]

when@prod:
    monolog:
        handlers:
            # Main log (errors only)
            main:
                type: fingers_crossed
                action_level: error
                handler: nested
                channels: ["!recaptcha"]

            nested:
                type: stream
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: debug

            # Dedicated reCAPTCHA log
            recaptcha:
                type: stream
                path: "%kernel.logs_dir%/recaptcha.log"
                level: warning
                channels: ["recaptcha"]

            # Critical failures go to syslog
            recaptcha_critical:
                type: syslog
                level: critical
                channels: ["recaptcha"]
```

### Using the Monitor Service

```php
<?php
// src/Controller/BookingController.php

use App\Service\ReCaptchaMonitorService;

class BookingController extends AbstractController
{
    public function __construct(
        // ... other dependencies
        private ReCaptchaService $recaptchaService,
        private ReCaptchaMonitorService $recaptchaMonitor
    ) {
    }

    #[Route('/create', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = $this->getUser()?->getId();
        $ip = $request->getClientIp();

        if ($this->recaptchaService->isEnabled()) {
            $recaptchaToken = $data['recaptcha_token'] ?? null;

            if (!$recaptchaToken) {
                // Log missing token
                $this->recaptchaMonitor->logMissingToken(
                    action: 'booking_create',
                    userId: (string) $userId,
                    ip: $ip
                );

                return $this->json([
                    'error' => 'Security verification is required.'
                ], Response::HTTP_FORBIDDEN);
            }

            $verification = $this->recaptchaService->verify(
                token: $recaptchaToken,
                action: 'booking_create',
                remoteIp: $ip,
                minScore: 0.5
            );

            if (!$verification['success']) {
                // Log failure with details
                $this->recaptchaMonitor->logFailure(
                    action: 'booking_create',
                    reason: $verification['error'] ?? 'unknown',
                    score: $verification['score'] ?? null,
                    userId: (string) $userId,
                    ip: $ip,
                    additionalContext: [
                        'threshold' => 0.5,
                        'message' => $verification['message'] ?? null
                    ]
                );

                return $this->json([
                    'error' => 'Suspicious activity detected.',
                    'code' => 'RECAPTCHA_FAILED'
                ], Response::HTTP_FORBIDDEN);
            }

            // Log success
            $this->recaptchaMonitor->logSuccess(
                action: 'booking_create',
                score: $verification['score'],
                userId: (string) $userId,
                ip: $ip
            );
        }

        // Continue with booking creation...
    }
}
```

### Log Analysis Commands

```bash
# View reCAPTCHA logs in real-time
tail -f var/log/recaptcha.log

# View only failures
tail -f var/log/recaptcha.log | grep "failed"

# Count failures in last hour
grep "Verification failed" var/log/recaptcha.log | grep "$(date +%Y-%m-%d\ %H)" | wc -l

# Find IPs with multiple failures
grep "Verification failed" var/log/recaptcha.log | grep -oP 'ip_address:\K[^,]+' | sort | uniq -c | sort -nr

# Average score over last 100 verifications
grep "score" var/log/recaptcha.log | tail -100 | grep -oP 'score":\K[0-9.]+' | awk '{sum+=$1} END {print sum/NR}'

# Find low score patterns
grep "score" var/log/recaptcha.log | grep -oP 'score":[0-2]\.[0-9]+'

# Export to CSV for analysis
grep "Verification" var/log/recaptcha.log | \
  sed 's/.*action"://; s/,.*score":/,/; s/,.*ip_address":/,/; s/,.*//' > recaptcha_analysis.csv
```

### Monitoring Dashboard Query Examples

If using a monitoring tool like Grafana, Datadog, or custom dashboard:

```sql
-- Example queries for metrics database

-- Success rate over time
SELECT
    DATE_TRUNC('hour', timestamp) as hour,
    COUNT(*) as total,
    SUM(CASE WHEN success = true THEN 1 ELSE 0 END) as successful,
    ROUND(100.0 * SUM(CASE WHEN success = true THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
FROM recaptcha_logs
WHERE timestamp > NOW() - INTERVAL '24 hours'
GROUP BY hour
ORDER BY hour DESC;

-- Average score by action
SELECT
    action,
    COUNT(*) as total_verifications,
    ROUND(AVG(score), 3) as avg_score,
    ROUND(MIN(score), 3) as min_score,
    ROUND(MAX(score), 3) as max_score
FROM recaptcha_logs
WHERE success = true
  AND timestamp > NOW() - INTERVAL '7 days'
GROUP BY action;

-- Top failing IP addresses
SELECT
    ip_address,
    COUNT(*) as failures,
    ARRAY_AGG(DISTINCT action) as actions,
    MIN(timestamp) as first_failure,
    MAX(timestamp) as last_failure
FROM recaptcha_logs
WHERE success = false
  AND timestamp > NOW() - INTERVAL '24 hours'
GROUP BY ip_address
HAVING COUNT(*) > 5
ORDER BY failures DESC
LIMIT 20;

-- Score distribution
SELECT
    CASE
        WHEN score >= 0.9 THEN '0.9-1.0 (Excellent)'
        WHEN score >= 0.7 THEN '0.7-0.9 (Good)'
        WHEN score >= 0.5 THEN '0.5-0.7 (Medium)'
        WHEN score >= 0.3 THEN '0.3-0.5 (Low)'
        ELSE '0.0-0.3 (Very Low)'
    END as score_range,
    COUNT(*) as count,
    ROUND(100.0 * COUNT(*) / SUM(COUNT(*)) OVER(), 2) as percentage
FROM recaptcha_logs
WHERE success = true
  AND timestamp > NOW() - INTERVAL '7 days'
GROUP BY score_range
ORDER BY MIN(score) DESC;
```

---

## Rate Limiting

### Why Rate Limiting is Important

Even with reCAPTCHA, you should implement rate limiting:

1. **Defense in Depth** - Multiple layers of protection
2. **API Abuse Prevention** - Limits excessive requests
3. **Resource Protection** - Prevents server overload
4. **Cost Control** - Reduces unnecessary Google API calls
5. **Attack Mitigation** - Slows down brute force attempts

### Symfony Rate Limiter Implementation

```php
<?php
// src/Service/RateLimiterService.php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

class RateLimiterService
{
    public function __construct(
        private RateLimiterFactory $loginLimiter,
        private RateLimiterFactory $registrationLimiter,
        private RateLimiterFactory $bookingLimiter,
        private RateLimiterFactory $recaptchaFailureLimiter,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Check login rate limit
     */
    public function checkLoginLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->loginLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('Login rate limit exceeded', [
                'ip' => $ip,
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'limit' => $limit->getLimit()
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check registration rate limit
     */
    public function checkRegistrationLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->registrationLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('Registration rate limit exceeded', [
                'ip' => $ip,
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Check booking rate limit
     */
    public function checkBookingLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->bookingLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('Booking rate limit exceeded', [
                'ip' => $ip,
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Track reCAPTCHA failures and auto-block aggressive IPs
     */
    public function trackRecaptchaFailure(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->recaptchaFailureLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->error('IP blocked due to excessive reCAPTCHA failures', [
                'ip' => $ip,
                'severity' => 'CRITICAL',
                'action_required' => 'Possible bot attack'
            ]);

            return [
                'blocked' => true,
                'reason' => 'Too many failed security verifications',
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ];
        }

        return ['blocked' => false];
    }
}
```

### Rate Limiter Configuration

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        # Login attempts: 5 per 15 minutes per IP
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '15 minutes'

        # Registration: 3 per hour per IP
        registration:
            policy: 'fixed_window'
            limit: 3
            interval: '1 hour'

        # Booking creation: 10 per hour per IP
        booking:
            policy: 'sliding_window'
            limit: 10
            interval: '1 hour'

        # reCAPTCHA failures: 10 failures = 1 hour block
        recaptcha_failure:
            policy: 'fixed_window'
            limit: 10
            interval: '1 hour'

        # Aggressive blocking for repeated failures
        recaptcha_aggressive:
            policy: 'token_bucket'
            limit: 3
            rate: { interval: '5 minutes', amount: 1 }
```

### Using Rate Limiter in Controllers

```php
<?php
// src/Controller/LoginController.php

use App\Service\RateLimiterService;

class LoginController extends AbstractController
{
    public function __construct(
        private ReCaptchaService $recaptchaService,
        private RateLimiterService $rateLimiter
    ) {
    }

    #[Route('/api/login', methods: ['POST'])]
    public function loginApi(Request $request, ...): JsonResponse
    {
        // Step 1: Check rate limit FIRST
        $rateLimit = $this->rateLimiter->checkLoginLimit($request);

        if (!$rateLimit['allowed']) {
            return $this->json([
                'error' => 'Too many login attempts. Please try again later.',
                'retry_after' => $rateLimit['retry_after']
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $data = json_decode($request->getContent(), true);

        // Step 2: Verify reCAPTCHA
        if ($this->recaptchaService->isEnabled()) {
            $recaptchaToken = $data['recaptcha_token'] ?? null;

            if (!$recaptchaToken) {
                return $this->json([
                    'error' => 'Security verification is required.'
                ], Response::HTTP_FORBIDDEN);
            }

            $verification = $this->recaptchaService->verify(
                token: $recaptchaToken,
                action: 'login',
                remoteIp: $request->getClientIp(),
                minScore: 0.4
            );

            if (!$verification['success']) {
                // Track failure for rate limiting
                $block = $this->rateLimiter->trackRecaptchaFailure($request);

                if ($block['blocked']) {
                    return $this->json([
                        'error' => 'Your IP has been temporarily blocked due to suspicious activity.',
                        'retry_after' => $block['retry_after']
                    ], Response::HTTP_FORBIDDEN);
                }

                return $this->json([
                    'error' => 'Security verification failed. Please try again.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Step 3: Proceed with authentication
        // ...
    }
}
```

### Rate Limiting Recommendations by Endpoint

| Endpoint | Limit | Interval | Policy | Reason |
|----------|-------|----------|--------|---------|
| `/api/login` | 5 | 15 min | Sliding Window | Prevent brute force |
| `/api/register` | 3 | 1 hour | Fixed Window | Prevent spam accounts |
| `/booking/create` | 10 | 1 hour | Sliding Window | Prevent booking abuse |
| `/api/google-login` | 10 | 15 min | Sliding Window | OAuth usually legitimate |
| reCAPTCHA failures | 10 | 1 hour | Fixed Window | Auto-block attackers |

---

## Troubleshooting

### Common Issues and Solutions

#### Issue 1: "Invalid site key" Error

**Symptoms:**
- Console error: "Invalid site key"
- reCAPTCHA badge shows error icon
- Token generation fails

**Causes:**
- Wrong site key in environment
- Domain not registered with Google
- Using production key on localhost

**Solutions:**

```bash
# 1. Verify environment variables
php bin/console debug:container --env-vars | grep RECAPTCHA

# 2. Check if keys are loaded
php bin/console debug:config framework | grep recaptcha

# 3. Clear cache
php bin/console cache:clear

# 4. Verify domain registration at Google Console
# https://www.google.com/recaptcha/admin
```

**Quick fix for development:**
```env
# Use test keys
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

#### Issue 2: All Users Getting Low Scores

**Symptoms:**
- Legitimate users blocked
- Scores consistently below threshold
- Many false positives

**Causes:**
- Aggressive testing (rapid submissions)
- VPN/proxy users
- Shared office IP
- Browser extensions blocking tracking
- New domain (low trust score)

**Solutions:**

```php
// 1. Temporarily lower threshold for testing
$verification = $this->recaptchaService->verify(
    token: $token,
    action: 'test',
    minScore: 0.3  // Lowered from 0.5
);

// 2. Add score-based handling
if ($verification['success']) {
    $score = $verification['score'];

    if ($score >= 0.7) {
        // High confidence - proceed normally
    } elseif ($score >= 0.5) {
        // Medium confidence - proceed with logging
        $this->logger->info('Medium score user', ['score' => $score]);
    } elseif ($score >= 0.3) {
        // Low confidence - add extra verification (email, etc.)
        // Or allow but flag for review
    } else {
        // Very low - block
    }
}

// 3. Environment-specific thresholds
// .env.local (dev)
RECAPTCHA_MIN_SCORE_LOGIN=0.3

// .env.prod (production)
RECAPTCHA_MIN_SCORE_LOGIN=0.4
```

**Testing recommendations:**
- Wait 10-30 seconds between test submissions
- Clear cookies between tests
- Test from different IPs
- Use real user interactions (not automation)

#### Issue 3: "timeout-or-duplicate" Error

**Symptoms:**
- Error: "timeout-or-duplicate"
- Tokens rejected as expired
- Random verification failures

**Causes:**
- Token older than 2 minutes
- Token reused
- Slow network
- User took too long to submit

**Solutions:**

```javascript
// ✅ CORRECT - Generate token immediately before submission
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Generate fresh token
    const token = await grecaptcha.execute(SITE_KEY, {
        action: 'submit'
    });

    // Immediately send to server (don't wait)
    const response = await fetch('/api/endpoint', {
        method: 'POST',
        body: JSON.stringify({
            ...formData,
            recaptcha_token: token
        })
    });
});

// ❌ WRONG - Token generated too early
let token;
form.addEventListener('focus', async () => {
    // Generated when user focuses form (might be minutes before submit)
    token = await grecaptcha.execute(SITE_KEY, { action: 'submit' });
});

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    // Token might be expired by now
    sendToServer(token);
});
```

#### Issue 4: reCAPTCHA Badge Not Showing

**Symptoms:**
- No badge in bottom-right corner
- Token generation fails
- `grecaptcha is not defined` error

**Causes:**
- Script not loaded
- Ad blocker blocking Google
- Content Security Policy (CSP) blocking
- Script load order issue

**Solutions:**

```html
<!-- 1. Verify script is loaded -->
{% if recaptcha_enabled %}
<script src="https://www.google.com/recaptcha/api.js?render={{ recaptcha_site_key }}" async defer></script>
{% endif %}

<!-- 2. Check CSP headers -->
<meta http-equiv="Content-Security-Policy"
      content="script-src 'self' https://www.google.com https://www.gstatic.com">

<!-- 3. Add error handling -->
<script>
window.addEventListener('load', () => {
    if (typeof grecaptcha === 'undefined') {
        console.error('reCAPTCHA failed to load. Check ad-blockers and CSP.');
        // Optionally disable form submission or show warning
    }
});
</script>
```

**Debug checklist:**
```javascript
// Run in browser console
console.log('reCAPTCHA loaded:', typeof grecaptcha !== 'undefined');
console.log('Site key:', RECAPTCHA_SITE_KEY);
console.log('Enabled:', RECAPTCHA_ENABLED);

// Check network tab for failed script loads
// Check console for CSP violations
```

#### Issue 5: High Server Load from Google API Calls

**Symptoms:**
- Slow verification times
- Timeouts
- Increased latency

**Causes:**
- Too many verification requests
- No rate limiting
- Synchronous verification blocking requests
- Google API slow/down

**Solutions:**

```php
// 1. Add timeout to prevent hanging
$response = $this->httpClient->request('POST', self::GOOGLE_RECAPTCHA_VERIFY_URL, [
    'body' => [...],
    'timeout' => 5  // 5 second timeout
]);

// 2. Cache successful verifications (BE CAREFUL - security implications)
// Only cache for very short periods
$cacheKey = "recaptcha_" . md5($token);
if ($cached = $this->cache->get($cacheKey)) {
    return $cached;
}
$result = $this->verify($token);
$this->cache->set($cacheKey, $result, 60); // Cache for 1 minute

// 3. Implement rate limiting (see Rate Limiting section)

// 4. Add fallback for Google API failures
try {
    $verification = $this->recaptchaService->verify($token);
} catch (\Exception $e) {
    $this->logger->error('reCAPTCHA API failed', ['error' => $e->getMessage()]);

    // Decide: Block user or allow through?
    // Option A: Block (more secure)
    return $this->json(['error' => 'Security verification unavailable'], 503);

    // Option B: Allow through with logging (better UX)
    $this->logger->critical('reCAPTCHA bypassed due to API failure');
    // Continue with request...
}
```

#### Issue 6: Different Scores in Dev vs Production

**Symptoms:**
- Works fine locally
- Fails in production
- Inconsistent scores

**Causes:**
- Different thresholds per environment
- Production domain not registered
- Using wrong keys in production

**Solutions:**

```env
# .env.local (development)
APP_ENV=dev
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI  # Test key
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
RECAPTCHA_MIN_SCORE_LOGIN=0.3  # Lower for testing

# .env.prod (production)
APP_ENV=prod
RECAPTCHA_SITE_KEY=your_production_site_key
RECAPTCHA_SECRET_KEY=your_production_secret_key
RECAPTCHA_MIN_SCORE_LOGIN=0.4  # Standard threshold
```

**Verification checklist:**
```bash
# 1. Verify environment
php bin/console about

# 2. Check which env file is loaded
php bin/console debug:container --env-vars

# 3. Verify domain registered at Google
# Go to https://www.google.com/recaptcha/admin
# Ensure production domain listed

# 4. Test with curl
curl -X POST https://www.google.com/recaptcha/api/siteverify \
  -d "secret=YOUR_SECRET_KEY&response=YOUR_TOKEN"
```

---

## Performance Optimization

### Frontend Optimization

```html
<!-- 1. Lazy load reCAPTCHA script -->
<script>
// Only load when user interacts with protected form
document.getElementById('loginForm').addEventListener('focus', () => {
    if (!window.recaptchaLoaded) {
        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?render={{ recaptcha_site_key }}';
        script.async = true;
        document.head.appendChild(script);
        window.recaptchaLoaded = true;
    }
}, { once: true, capture: true });
</script>

<!-- 2. Use async/defer for script loading -->
<script src="https://www.google.com/recaptcha/api.js?render={{ key }}" async defer></script>

<!-- 3. Preconnect to Google domains -->
<link rel="preconnect" href="https://www.google.com">
<link rel="preconnect" href="https://www.gstatic.com" crossorigin>
```

### Backend Optimization

```php
// 1. Use connection pooling for HTTP client
// config/packages/framework.yaml
framework:
    http_client:
        default_options:
            max_redirects: 0
            timeout: 5
        scoped_clients:
            recaptcha.client:
                base_uri: 'https://www.google.com'
                timeout: 5
                max_duration: 10

// 2. Implement circuit breaker for Google API
// If Google API is down, fail open after N failures
private int $consecutiveFailures = 0;
private const MAX_FAILURES = 5;

public function verify(string $token, ...): array
{
    // If too many failures, temporarily disable
    if ($this->consecutiveFailures >= self::MAX_FAILURES) {
        $this->logger->error('reCAPTCHA circuit breaker open - too many API failures');
        // Fail open: allow requests through
        return ['success' => true, 'circuit_breaker' => 'open'];
    }

    try {
        $result = $this->callGoogleAPI($token);
        $this->consecutiveFailures = 0; // Reset on success
        return $result;
    } catch (\Exception $e) {
        $this->consecutiveFailures++;
        throw $e;
    }
}
```

---

## Summary

### Quick Reference

**Development:**
- Use test keys or disable reCAPTCHA
- Lower thresholds (0.3-0.4)
- Enable verbose logging

**Staging:**
- Use staging-specific keys
- Medium thresholds (0.4-0.5)
- Test rate limiting

**Production:**
- Use production keys only
- Standard thresholds (0.4-0.5)
- Full rate limiting
- Monitor continuously

**Security Checklist:**
- [ ] No secret keys in version control
- [ ] Server-side verification only
- [ ] No sensitive data in logs
- [ ] HTTPS enabled
- [ ] Rate limiting configured
- [ ] Monitoring enabled
- [ ] Error handling tested

**Monitoring:**
- Watch success rates (should be >95%)
- Track average scores (should be >0.7)
- Alert on sudden score drops
- Review failed attempts daily
- Block IPs with excessive failures

For more details, see:
- `RECAPTCHA_SETUP.md` - Initial setup
- `RECAPTCHA_LOGIN_INTEGRATION.md` - Login integration
- `RECAPTCHA_BOOKING_INTEGRATION.md` - Booking integration
