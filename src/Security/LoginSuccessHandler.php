<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        // Get user from token
        $user = $token->getUser();

        // Check if user has ROLE_ADMIN
        $roles = $token->getRoleNames();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            // Redirect admins to Admin Dashboard
            return new RedirectResponse($this->urlGenerator->generate('admin_dashboard'));
        }

        // Redirect regular users to Booking Calendar
        return new RedirectResponse($this->urlGenerator->generate('booking_index'));
    }
}
