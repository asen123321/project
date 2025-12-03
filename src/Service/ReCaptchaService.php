<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Google reCAPTCHA v3 Verification Service
 *
 * This service handles server-side verification of reCAPTCHA v3 tokens
 * using Google's siteverify API endpoint.
 */
class ReCaptchaService
{
    private const GOOGLE_RECAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const MIN_SCORE = 0.5;

    private string $secretKey;
    private string $siteKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private bool $enabled;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $recaptchaSiteKey,
        string $recaptchaSecretKey
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->siteKey = $recaptchaSiteKey;
        $this->secretKey = $recaptchaSecretKey;

        // Disable reCAPTCHA if keys are not configured
        $this->enabled = !empty($recaptchaSiteKey) && !empty($recaptchaSecretKey);

        if (!$this->enabled) {
            $this->logger->warning('reCAPTCHA is disabled - missing site key or secret key');
        }
    }

    /**
     * Verify a reCAPTCHA token with Google's API
     *
     * @param string $token The reCAPTCHA token from the frontend
     * @param string|null $action Expected action name (optional but recommended)
     * @param string|null $remoteIp User's IP address (optional)
     * @param float $minScore Minimum acceptable score (default: 0.5)
     * @return array Response with 'success' boolean and optional 'score', 'action', 'error' keys
     */
    public function verify(
        string $token,
        ?string $action = null,
        ?string $remoteIp = null,
        float $minScore = self::MIN_SCORE
    ): array {
        // If reCAPTCHA is disabled, return success
        if (!$this->enabled) {
            $this->logger->info('reCAPTCHA verification skipped - service is disabled');
            return [
                'success' => true,
                'disabled' => true,
                'message' => 'reCAPTCHA is disabled'
            ];
        }

        // Validate token format
        if (empty($token)) {
            $this->logger->warning('reCAPTCHA verification failed - empty token provided');
            return [
                'success' => false,
                'error' => 'missing-input-response',
                'message' => 'reCAPTCHA token is required'
            ];
        }

        try {
            // Prepare request data
            $postData = [
                'secret' => $this->secretKey,
                'response' => $token,
            ];

            if ($remoteIp) {
                $postData['remoteip'] = $remoteIp;
            }

            // Send verification request to Google
            $response = $this->httpClient->request('POST', self::GOOGLE_RECAPTCHA_VERIFY_URL, [
                'body' => $postData,
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            // Log the response for debugging (without sensitive data)
            $this->logger->info('reCAPTCHA verification response received', [
                'status_code' => $statusCode,
                'success' => $content['success'] ?? false,
                'score' => $content['score'] ?? null,
                'action' => $content['action'] ?? null,
            ]);

            // Check if the request was successful
            if ($statusCode !== 200) {
                $this->logger->error('reCAPTCHA verification failed - HTTP error', [
                    'status_code' => $statusCode,
                ]);
                return [
                    'success' => false,
                    'error' => 'http-error',
                    'message' => 'Failed to verify reCAPTCHA token'
                ];
            }

            // Check Google's success flag
            if (!($content['success'] ?? false)) {
                $errorCodes = $content['error-codes'] ?? ['unknown-error'];
                $this->logger->warning('reCAPTCHA verification failed by Google', [
                    'error_codes' => $errorCodes,
                ]);
                return [
                    'success' => false,
                    'error' => implode(', ', $errorCodes),
                    'message' => $this->getErrorMessage($errorCodes[0] ?? 'unknown-error')
                ];
            }

            $score = $content['score'] ?? 0.0;
            $responseAction = $content['action'] ?? null;

            // Validate action if provided
            if ($action !== null && $responseAction !== $action) {
                $this->logger->warning('reCAPTCHA action mismatch', [
                    'expected' => $action,
                    'received' => $responseAction,
                ]);
                return [
                    'success' => false,
                    'error' => 'action-mismatch',
                    'message' => 'reCAPTCHA action does not match',
                    'score' => $score,
                    'action' => $responseAction
                ];
            }

            // Validate score
            if ($score < $minScore) {
                $this->logger->warning('reCAPTCHA score too low', [
                    'score' => $score,
                    'min_score' => $minScore,
                    'action' => $responseAction,
                ]);
                return [
                    'success' => false,
                    'error' => 'low-score',
                    'message' => 'reCAPTCHA verification failed - suspicious activity detected',
                    'score' => $score,
                    'action' => $responseAction
                ];
            }

            // Success!
            $this->logger->info('reCAPTCHA verification successful', [
                'score' => $score,
                'action' => $responseAction,
            ]);

            return [
                'success' => true,
                'score' => $score,
                'action' => $responseAction,
                'challenge_ts' => $content['challenge_ts'] ?? null,
                'hostname' => $content['hostname'] ?? null,
            ];

        } catch (TransportExceptionInterface $e) {
            $this->logger->error('reCAPTCHA verification failed - network error', [
                'exception' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => 'network-error',
                'message' => 'Failed to connect to reCAPTCHA service'
            ];
        } catch (\Exception $e) {
            $this->logger->error('reCAPTCHA verification failed - unexpected error', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => 'unexpected-error',
                'message' => 'An unexpected error occurred during verification'
            ];
        }
    }

    /**
     * Get the site key for frontend integration
     * This is safe to expose in the frontend
     *
     * @return string
     */
    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    /**
     * Check if reCAPTCHA is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get user-friendly error message for error codes
     *
     * @param string $errorCode
     * @return string
     */
    private function getErrorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'missing-input-secret' => 'The secret parameter is missing',
            'invalid-input-secret' => 'The secret parameter is invalid or malformed',
            'missing-input-response' => 'The response parameter is missing',
            'invalid-input-response' => 'The response parameter is invalid or malformed',
            'bad-request' => 'The request is invalid or malformed',
            'timeout-or-duplicate' => 'The response is no longer valid: either is too old or has been used previously',
            default => 'reCAPTCHA verification failed',
        };
    }
}
