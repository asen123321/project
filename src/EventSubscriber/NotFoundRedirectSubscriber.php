<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotFoundRedirectSubscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Get the exception
        $exception = $event->getThrowable();

        // Only handle 404 Not Found exceptions
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        // Generate homepage URL
        $homepageUrl = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // Create redirect response to homepage
        $response = new RedirectResponse($homepageUrl);

        // Set the response
        $event->setResponse($response);
    }
}
