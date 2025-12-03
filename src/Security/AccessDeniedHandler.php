<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * Custom Access Denied Handler
 *
 * Improves user experience by redirecting users to the homepage
 * instead of showing a 403 error page when access is denied.
 *
 * Handles cases such as:
 * - Stale authentication cookies
 * - Insufficient permissions
 * - Session timeout issues
 * - Role-based access violations
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
    {
        // Log the access denied event for security monitoring
        $this->logger->warning('Access denied - clearing state and redirecting to login', [
            'uri' => $request->getUri(),
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'user' => $this->tokenStorage->getToken()?->getUserIdentifier() ?? 'anonymous',
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'reason' => $accessDeniedException->getMessage()
        ]);

        // Capture the original requested URL for post-login redirect
        $requestedPath = $request->getPathInfo();
        $isProtectedRoute = $this->isProtectedRoute($requestedPath);

        // Force logout to clear stale authentication state
        $this->tokenStorage->setToken(null);

        $this->logger->info('Authentication token cleared', [
            'path' => $requestedPath,
            'was_protected_route' => $isProtectedRoute
        ]);

        // Get or start session for flash messages and return URL
        $session = $request->getSession();

        // Store the requested URL if it was a legitimate protected route
        // This allows redirecting back after successful login
        if ($isProtectedRoute && $request->getMethod() === 'GET') {
            try {
                $session->set('_security.main.target_path', $requestedPath);

                $this->logger->info('Stored return URL for post-login redirect', [
                    'return_url' => $requestedPath
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to store return URL', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Add user-friendly flash message
        try {
            $session->getFlashBag()->add(
                'warning',
                'Your session has expired or you do not have permission to access that page. Please log in again.'
            );
        } catch (\Exception $e) {
            $this->logger->error('Failed to add flash message', [
                'error' => $e->getMessage()
            ]);
        }

        // Invalidate the session to clear stale cookies and state
        if ($session->isStarted()) {
            try {
                $session->invalidate();

                $this->logger->info('Session invalidated successfully', [
                    'path' => $requestedPath
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to invalidate session', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // Determine redirect URL - login page for clean re-authentication
        // Check if login route exists, otherwise fall back to homepage
        try {
            $redirectUrl = $this->urlGenerator->generate('app_login_page');
            $redirectTarget = 'login page';
        } catch (\Exception $e) {
            // Fallback to root if login route doesn't exist
            $redirectUrl = '/';
            $redirectTarget = 'homepage';

            $this->logger->warning('Login route not found, falling back to homepage', [
                'error' => $e->getMessage()
            ]);
        }

        $this->logger->info('Redirecting after access denial', [
            'redirect_url' => $redirectUrl,
            'redirect_target' => $redirectTarget,
            'original_path' => $requestedPath
        ]);

        // Create redirect response
        $response = new RedirectResponse($redirectUrl);

        // Clear authentication cookies to ensure clean state
        // This is critical for handling stale sessions after browser reopening
        $response->headers->clearCookie('PHPSESSID');
        $response->headers->clearCookie('REMEMBERME', '/', null);

        return $response;
    }

    /**
     * Determine if the requested path is a protected route that requires authentication
     */
    private function isProtectedRoute(string $path): bool
    {
        // Routes that require authentication (not public)
        $protectedPrefixes = [
            '/booking',
            '/admin',
            '/home',
            '/api/bookings',
            '/api/user'
        ];

        foreach ($protectedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
