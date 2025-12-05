<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class AppCustomAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const LOGIN_ROUTE = 'app_login_page';
    private const REDIRECT_LOOP_KEY = '_security_redirect_count';
    private const MAX_REDIRECTS = 2;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private TokenStorageInterface $tokenStorage,
        private RequestStack $requestStack
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/login'  // Changed from '/' to '/login'
            && $request->request->has('email')
            && $request->request->has('password');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');
        $csrfToken = $request->request->get('_csrf_token');

        if (!$email || !$password) {
            throw new CustomUserMessageAuthenticationException('Email and password are required.');
        }

        // Note: CSRF protection is currently disabled in framework.yaml
        // If you re-enable it, uncomment the CsrfTokenBadge line below
        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                // new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Проверка дали има сесия
        if ($request->hasSession() && ($session = $request->getSession())->isStarted()) {

            // 👇 ПОПРАВКА ЗА VULN-009: Session Fixation
            // Сменяме ID-то на сесията и изтриваме старото.
            // Така, ако хакер е подхвърлил сесия, тя става невалидна.
            $session->migrate(true);

            // Clear redirect counter on successful login
            $session->remove(self::REDIRECT_LOOP_KEY);
        }

        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('booking_index'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Store error in session BEFORE creating response
        if ($request->hasSession() && ($session = $request->getSession())->isStarted()) {
            $session->set('_security.last_error', $exception);
        }

        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        // Clear the security token
        $this->tokenStorage->setToken(null);

        // Check if we're already on the login page
        $currentRoute = $request->attributes->get('_route');
        if ($currentRoute === self::LOGIN_ROUTE) {
            // Avoid redirect loop - just return 401
            return new Response('Authentication required', Response::HTTP_UNAUTHORIZED);
        }

        // Only work with session if it exists and is started
        if ($request->hasSession() && ($session = $request->getSession())->isStarted()) {
            // Loop detection
            $redirectCount = $session->get(self::REDIRECT_LOOP_KEY, 0);
            $redirectCount++;
            $session->set(self::REDIRECT_LOOP_KEY, $redirectCount);

            // Safety break if too many redirects
            if ($redirectCount >= self::MAX_REDIRECTS) {
                $session->remove(self::REDIRECT_LOOP_KEY);
                return new RedirectResponse('/');
            }
        }

        // Standard redirect to login
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }
}