<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for monitoring and logging reCAPTCHA verification events
 */
class ReCaptchaMonitorService
{
    private const LOG_CHANNEL = 'recaptcha';
    private const CACHE_TTL = 3600; // 1 hour
    private const FAILURE_COUNT_TTL = 7200; // 2 hours

    public function __construct(
        private LoggerInterface $logger,
        private CacheInterface $cache
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
            'score' => round($score, 3),
            'user_id' => $userId,
            'ip_address' => $this->maskIp($ip),
            'timestamp' => date('Y-m-d H:i:s'),
            'result' => 'SUCCESS'
        ]);

        // Update success statistics
        $this->incrementCounter('recaptcha_success_total');
        $this->incrementCounter("recaptcha_success_{$action}");
        $this->recordScore($action, $score);
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
            'score' => $score !== null ? round($score, 3) : null,
            'user_id' => $userId,
            'ip_address' => $this->maskIp($ip),
            'timestamp' => date('Y-m-d H:i:s'),
            'result' => 'FAILURE'
        ], $additionalContext);

        $this->logger->warning('[reCAPTCHA] Verification failed', $context);

        // Update failure statistics
        $this->incrementCounter('recaptcha_failure_total');
        $this->incrementCounter("recaptcha_failure_{$action}");

        if ($ip) {
            $this->incrementFailureCount($ip, $action);
        }

        // Check for patterns
        if ($ip && $this->getFailureCount($ip) >= 5) {
            $this->logLowScorePattern($ip, $this->getRecentScores($ip));
        }
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
            'severity' => 'HIGH',
            'result' => 'MISSING_TOKEN'
        ]);

        $this->incrementCounter('recaptcha_missing_token_total');
        $this->incrementCounter("recaptcha_missing_token_{$action}");
    }

    /**
     * Log low score patterns
     */
    public function logLowScorePattern(
        string $ip,
        array $recentScores
    ): void {
        if (empty($recentScores)) {
            return;
        }

        $avgScore = array_sum($recentScores) / count($recentScores);

        $this->logger->warning('[reCAPTCHA] Low score pattern detected', [
            'ip_address' => $this->maskIp($ip),
            'recent_scores' => array_map(fn($s) => round($s, 3), $recentScores),
            'average_score' => round($avgScore, 3),
            'sample_size' => count($recentScores),
            'timestamp' => date('Y-m-d H:i:s'),
            'severity' => 'HIGH',
            'result' => 'SUSPICIOUS_PATTERN'
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
            'action_required' => 'Review and consider IP ban',
            'result' => 'ATTACK_DETECTED'
        ]);

        $this->incrementCounter('recaptcha_attacks_total');
    }

    /**
     * Get failure statistics for monitoring dashboard
     */
    public function getFailureStats(int $hours = 24): array
    {
        $stats = [
            'total_verifications' => $this->getCounter('recaptcha_success_total') + $this->getCounter('recaptcha_failure_total'),
            'successful' => $this->getCounter('recaptcha_success_total'),
            'failed' => $this->getCounter('recaptcha_failure_total'),
            'missing_tokens' => $this->getCounter('recaptcha_missing_token_total'),
            'attacks_detected' => $this->getCounter('recaptcha_attacks_total'),
            'period' => $hours . ' hours'
        ];

        $stats['success_rate'] = $stats['total_verifications'] > 0
            ? round(($stats['successful'] / $stats['total_verifications']) * 100, 2)
            : 0;

        $stats['average_score'] = $this->getAverageScore();

        return $stats;
    }

    /**
     * Get statistics by action
     */
    public function getStatsByAction(): array
    {
        $actions = ['login', 'register', 'booking_create', 'google_login'];
        $stats = [];

        foreach ($actions as $action) {
            $success = $this->getCounter("recaptcha_success_{$action}");
            $failure = $this->getCounter("recaptcha_failure_{$action}");
            $total = $success + $failure;

            $stats[$action] = [
                'total' => $total,
                'successful' => $success,
                'failed' => $failure,
                'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
                'average_score' => $this->getAverageScoreByAction($action)
            ];
        }

        return $stats;
    }

    /**
     * Reset statistics (useful for testing)
     */
    public function resetStats(): void
    {
        $keys = [
            'recaptcha_success_total',
            'recaptcha_failure_total',
            'recaptcha_missing_token_total',
            'recaptcha_attacks_total',
            'recaptcha_scores',
        ];

        $actions = ['login', 'register', 'booking_create', 'google_login'];
        foreach ($actions as $action) {
            $keys[] = "recaptcha_success_{$action}";
            $keys[] = "recaptcha_failure_{$action}";
            $keys[] = "recaptcha_missing_token_{$action}";
            $keys[] = "recaptcha_scores_{$action}";
        }

        foreach ($keys as $key) {
            $this->cache->delete($key);
        }

        $this->logger->info('[reCAPTCHA] Statistics reset');
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
     * Increment counter in cache
     */
    private function incrementCounter(string $key): void
    {
        try {
            $this->cache->get($key, function (ItemInterface $item) {
                $item->expiresAfter(self::CACHE_TTL);
                return 1;
            });

            // Increment existing value
            $current = $this->cache->get($key, fn() => 0);
            $this->cache->delete($key);
            $this->cache->get($key, function (ItemInterface $item) use ($current) {
                $item->expiresAfter(self::CACHE_TTL);
                return $current + 1;
            });
        } catch (\Exception $e) {
            // Silently fail if cache unavailable
        }
    }

    /**
     * Get counter value
     */
    private function getCounter(string $key): int
    {
        try {
            return (int) $this->cache->get($key, fn() => 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Increment failure count for IP
     */
    private function incrementFailureCount(string $ip, string $action): void
    {
        $key = "recaptcha_ip_failures_{$ip}";

        try {
            $failures = $this->cache->get($key, function (ItemInterface $item) {
                $item->expiresAfter(self::FAILURE_COUNT_TTL);
                return [];
            });

            $failures[] = [
                'action' => $action,
                'timestamp' => time(),
                'score' => null
            ];

            // Keep only last 20 failures
            $failures = array_slice($failures, -20);

            $this->cache->delete($key);
            $this->cache->get($key, function (ItemInterface $item) use ($failures) {
                $item->expiresAfter(self::FAILURE_COUNT_TTL);
                return $failures;
            });
        } catch (\Exception $e) {
            // Silently fail if cache unavailable
        }
    }

    /**
     * Get failure count for IP
     */
    private function getFailureCount(string $ip): int
    {
        $key = "recaptcha_ip_failures_{$ip}";

        try {
            $failures = $this->cache->get($key, fn() => []);
            return count($failures);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get recent scores for IP
     */
    private function getRecentScores(string $ip): array
    {
        $key = "recaptcha_ip_failures_{$ip}";

        try {
            $failures = $this->cache->get($key, fn() => []);
            return array_filter(array_column($failures, 'score'), fn($s) => $s !== null);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Record score for statistics
     */
    private function recordScore(string $action, float $score): void
    {
        $key = "recaptcha_scores_{$action}";

        try {
            $scores = $this->cache->get($key, function (ItemInterface $item) {
                $item->expiresAfter(self::CACHE_TTL);
                return [];
            });

            $scores[] = $score;

            // Keep only last 100 scores
            $scores = array_slice($scores, -100);

            $this->cache->delete($key);
            $this->cache->get($key, function (ItemInterface $item) use ($scores) {
                $item->expiresAfter(self::CACHE_TTL);
                return $scores;
            });
        } catch (\Exception $e) {
            // Silently fail if cache unavailable
        }
    }

    /**
     * Get average score across all actions
     */
    private function getAverageScore(): float
    {
        try {
            $allScores = $this->cache->get('recaptcha_scores', fn() => []);
            if (empty($allScores)) {
                return 0;
            }
            return round(array_sum($allScores) / count($allScores), 3);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get average score by action
     */
    private function getAverageScoreByAction(string $action): float
    {
        $key = "recaptcha_scores_{$action}";

        try {
            $scores = $this->cache->get($key, fn() => []);
            if (empty($scores)) {
                return 0;
            }
            return round(array_sum($scores) / count($scores), 3);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
