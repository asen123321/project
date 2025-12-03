<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Strips fbclid (Facebook Click ID) parameter from URLs to prevent OAuth URI mismatches
 * This is especially important for OAuth flows in Facebook Messenger's in-app browser
 */
class FacebookClickIdStripperSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 33], // High priority, before routing
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check if fbclid parameter exists
        if (!$request->query->has('fbclid')) {
            return;
        }

        // Get all query parameters except fbclid
        $queryParams = $request->query->all();
        unset($queryParams['fbclid']);

        // Build clean URL without fbclid
        $cleanUrl = $request->getSchemeAndHttpHost() . $request->getPathInfo();

        if (!empty($queryParams)) {
            $cleanUrl .= '?' . http_build_query($queryParams);
        }

        // Redirect to clean URL (301 permanent redirect for SEO)
        $response = new RedirectResponse($cleanUrl, 301);
        $event->setResponse($response);
    }
}
