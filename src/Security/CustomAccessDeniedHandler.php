<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Custom Access Denied Handler
 *
 * Intercepts ALL AccessDeniedException (403 errors) and redirects to homepage
 * This prevents static 403 error pages and transparently resets user state
 *
 * Handles scenarios:
 * - Stale session cookies after browser reopening
 * - Expired sessions
 * - Role mismatches
 * - Any other 403 error
 */
class CustomAccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle AccessDeniedException by redirecting to homepage and clearing state
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
    {
        // Log the access denial for monitoring
        $this->logger->warning('403 Access Denied - Redirecting to homepage and clearing state', [
            'requested_path' => $request->getPathInfo(),
            'requested_uri' => $request->getUri(),
            'user' => $this->tokenStorage->getToken()?->getUserIdentifier() ?? 'anonymous',
            'ip' => $request->getClientIp(),
            'reason' => $accessDeniedException->getMessage()
        ]);

        // Step 1: Clear authentication token to force logout
        $this->tokenStorage->setToken(null);

        // Step 2: Invalidate session to clear all stale data
        $session = $request->getSession();
        if ($session && $session->isStarted()) {
            try {
                $session->invalidate();
                $this->logger->info('Session invalidated after 403', [
                    'path' => $request->getPathInfo()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to invalidate session', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Step 3: Create redirect response to homepage (/)
        $response = new RedirectResponse('/');

        // Step 4: Clear all authentication cookies
        // This is CRITICAL for handling stale cookies after browser reopening
        $response->headers->clearCookie('PHPSESSID', '/', null);
        $response->headers->clearCookie('REMEMBERME', '/', null);

        $this->logger->info('Redirecting to homepage after 403', [
            'original_path' => $request->getPathInfo(),
            'redirect_to' => '/'
        ]);

        return $response;
    }
}
