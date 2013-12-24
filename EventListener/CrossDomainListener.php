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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event listener that blocks cross domain redirects.
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class CrossDomainListener implements EventSubscriberInterface
{
    /**
     * Header to verify on the request.
     *
     * @var string
     */
    const REQUEST_HEADER = 'X-XHR-Referer';

    /**
     * Header to verify on the response.
     *
     * @var string
     */
    const RESPONSE_HEADER = 'Location';

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
     * Filters the Response.
     *
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$request->headers->has(self::REQUEST_HEADER) || !$response->headers->has(self::RESPONSE_HEADER)) {
            return;
        }

        if (!$this->hasSameOrigin($request, $response)) {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * Parse the given url into an origin array with the scheme, host and port.
     *
     * @param string $url
     *
     * @return array
     */
    private function getUrlOrigin($url)
    {
        return array(
            parse_url($url, PHP_URL_SCHEME),
            parse_url($url, PHP_URL_HOST),
            parse_url($url, PHP_URL_PORT),
        );
    }

    /**
     * Checks if the request and the response have the same origin.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return Boolean
     */
    private function hasSameOrigin(Request $request, Response $response)
    {
        $requestOrigin = $this->getUrlOrigin($request->headers->get(self::REQUEST_HEADER));
        $responseOrigin = $this->getUrlOrigin($response->headers->get(self::RESPONSE_HEADER));

        return $requestOrigin == $responseOrigin;
    }
}
