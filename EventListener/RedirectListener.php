<?php

/*
 * This file is part of the Helthe Turbolinks package.
 *
 * (c) Carl Alexander <carlalexander@helthe.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Helthe\Component\Turbolinks\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that adds the necessary redirect header to a RedirectResponse.
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class RedirectListener implements EventSubscriberInterface
{
    /**
     * Header inserted in the response.
     *
     * @var string
     */
    const RESPONSE_HEADER = 'X-XHR-Redirected-To';

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();

        if ($response->isRedirect() && $response->headers->has('Location')) {
            $response->headers->add(array(self::RESPONSE_HEADER => $response->headers->get('Location')));
        }
    }
}
