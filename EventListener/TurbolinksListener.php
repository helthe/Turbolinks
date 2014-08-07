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

use Helthe\Component\Turbolinks\Turbolinks;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that modifies the kernel response for the turbolinks javascript.
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class TurbolinksListener implements EventSubscriberInterface
{
    /**
     * @var Turbolinks
     */
    private $turbolinks;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array('onKernelResponse', -128),
        );
    }

    /**
     * Constructor.
     *
     * @param Turbolinks $turbolinks
     */
    public function __construct(Turbolinks $turbolinks)
    {
        $this->turbolinks = $turbolinks;
    }

    /**
     * Filters the Response.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $this->turbolinks->decorateResponse($event->getRequest(), $event->getResponse());
    }
}
