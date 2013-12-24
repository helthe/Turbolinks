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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that adds the necessary request method cookie to the response.
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class RequestMethodListener implements EventSubscriberInterface
{
    /**
     * Cookie attribute name for the request method.
     *
     * @var string
     */
    const COOKIE_ATTR_NAME = 'request_method';

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

        $event->getResponse()->headers->setCookie(
            new Cookie(
                self::COOKIE_ATTR_NAME,
                $event->getRequest()->getMethod()
            )
        );
    }
}
