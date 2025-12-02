<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ErrorController extends AbstractController
{
    #[Route('/error/access-denied', name: 'error_access_denied')]
    public function accessDenied(
        TokenStorageInterface $tokenStorage,
        RequestStack $requestStack
    ): Response
    {
        // FORCE LOGOUT - This is the circuit breaker
        $tokenStorage->setToken(null);

        $session = $requestStack->getSession();
        if ($session) {
            $session->invalidate();
        }

        // Return static 403 page
        $response = $this->render('bundles/TwigBundle/Exception/error403.html.twig');

        // Clear all authentication cookies
        $response->headers->clearCookie('PHPSESSID');
        $response->headers->clearCookie('REMEMBERME', '/', null);

        return $response;
    }
}
