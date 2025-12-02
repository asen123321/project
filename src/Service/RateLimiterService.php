<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Psr\Log\LoggerInterface;

/**
 * Service for rate limiting protected endpoints
 */
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
     * Limit: 5 attempts per 15 minutes per IP
     */
    public function checkLoginLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->loginLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('[RateLimit] Login limit exceeded', [
                'ip' => $this->maskIp($ip),
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'limit' => $limit->getLimit()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'retry_after_seconds' => $limit->getRetryAfter()->getTimestamp() - time(),
                'limit' => $limit->getLimit(),
                'message' => 'Too many login attempts. Please try again later.'
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $limit->getRemainingTokens()
        ];
    }

    /**
     * Check registration rate limit
     * Limit: 3 registrations per hour per IP
     */
    public function checkRegistrationLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->registrationLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('[RateLimit] Registration limit exceeded', [
                'ip' => $this->maskIp($ip),
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'retry_after_seconds' => $limit->getRetryAfter()->getTimestamp() - time(),
                'message' => 'Too many registration attempts. Please try again later.'
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $limit->getRemainingTokens()
        ];
    }

    /**
     * Check booking rate limit
     * Limit: 10 bookings per hour per IP
     */
    public function checkBookingLimit(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->bookingLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->warning('[RateLimit] Booking limit exceeded', [
                'ip' => $this->maskIp($ip),
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'allowed' => false,
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'retry_after_seconds' => $limit->getRetryAfter()->getTimestamp() - time(),
                'message' => 'Too many booking attempts. Please try again later.'
            ];
        }

        return [
            'allowed' => true,
            'remaining' => $limit->getRemainingTokens()
        ];
    }

    /**
     * Track reCAPTCHA failures and auto-block aggressive IPs
     * Limit: 10 failures per hour = block
     */
    public function trackRecaptchaFailure(Request $request): array
    {
        $ip = $request->getClientIp();
        $limiter = $this->recaptchaFailureLimiter->create($ip);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->logger->error('[RateLimit] IP blocked due to excessive reCAPTCHA failures', [
                'ip' => $this->maskIp($ip),
                'severity' => 'CRITICAL',
                'action_required' => 'Possible bot attack',
                'retry_after' => $limit->getRetryAfter()->getTimestamp()
            ]);

            return [
                'blocked' => true,
                'reason' => 'Too many failed security verifications',
                'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                'retry_after_seconds' => $limit->getRetryAfter()->getTimestamp() - time(),
                'message' => 'Your IP has been temporarily blocked due to suspicious activity. Please try again later.'
            ];
        }

        // Log the failure but not blocked yet
        $remaining = $limit->getRemainingTokens();
        if ($remaining <= 3) {
            $this->logger->warning('[RateLimit] reCAPTCHA failures approaching limit', [
                'ip' => $this->maskIp($ip),
                'remaining_attempts' => $remaining,
                'severity' => 'MEDIUM'
            ]);
        }

        return [
            'blocked' => false,
            'remaining' => $remaining
        ];
    }

    /**
     * Check if IP is currently blocked
     */
    public function isBlocked(Request $request, string $limiterType = 'recaptcha_failure'): bool
    {
        $ip = $request->getClientIp();

        $limiter = match ($limiterType) {
            'login' => $this->loginLimiter,
            'registration' => $this->registrationLimiter,
            'booking' => $this->bookingLimiter,
            'recaptcha_failure' => $this->recaptchaFailureLimiter,
            default => $this->recaptchaFailureLimiter
        };

        $limit = $limiter->create($ip)->consume(0); // Check without consuming

        return !$limit->isAccepted();
    }

    /**
     * Get rate limit status for IP
     */
    public function getStatus(Request $request, string $limiterType): array
    {
        $ip = $request->getClientIp();

        $limiter = match ($limiterType) {
            'login' => $this->loginLimiter,
            'registration' => $this->registrationLimiter,
            'booking' => $this->bookingLimiter,
            'recaptcha_failure' => $this->recaptchaFailureLimiter,
            default => $this->recaptchaFailureLimiter
        };

        $limit = $limiter->create($ip)->consume(0); // Check without consuming

        return [
            'type' => $limiterType,
            'ip' => $this->maskIp($ip),
            'accepted' => $limit->isAccepted(),
            'limit' => $limit->getLimit(),
            'remaining' => $limit->getRemainingTokens(),
            'retry_after' => $limit->isAccepted() ? null : $limit->getRetryAfter()->getTimestamp(),
            'reset_at' => $limit->getRetryAfter()->getTimestamp()
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
}
