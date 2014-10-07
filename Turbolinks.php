<?php

/*
 * This file is part of the Helthe Turbolinks package.
 *
 * (c) Carl Alexander <carlalexander@helthe.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Helthe\Component\Turbolinks;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Turbolinks implements the server-side logic expected by Turbolinks javascript.
 *
 * @link https://github.com/rails/turbolinks/blob/master/lib/turbolinks.rb
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class Turbolinks
{
    /**
     * Request header used for origin validation.
     *
     * @var string
     */
    const ORIGIN_REQUEST_HEADER = 'X-XHR-Referer';

    /**
     * Response header used for origin validation.
     *
     * @var string
     */
    const ORIGIN_RESPONSE_HEADER = 'Location';

    /**
     * Redirect header inserted in the response.
     *
     * @var string
     */
    const REDIRECT_RESPONSE_HEADER = 'X-XHR-Redirected-To';

    /**
     * Session attribute name for the redirect location.
     *
     * @var string
     */
    const REDIRECT_SESSION_ATTR_NAME = 'helthe_turbolinks_redirect_to';

    /**
     * Cookie attribute name for the request method.
     *
     * @var string
     */
    const REQUEST_METHOD_COOKIE_ATTR_NAME = 'request_method';

    /**
     * Modifies the HTTP headers and status code of the Response so that it can be
     * properly handled by the Turbolinks javascript.
     *
     * @param Request  $request
     * @param Response $response
     */
    public function decorateResponse(Request $request, Response $response)
    {
        $this->addRequestMethodCookie($request, $response);
        $this->modifyStatusCode($request, $response);

        if (!$this->canHandleRedirect($request)) {
            return;
        }

        $session = $request->getSession();

        if ($session->has(self::REDIRECT_SESSION_ATTR_NAME)) {
            $response->headers->add(array(self::REDIRECT_RESPONSE_HEADER => $session->remove(self::REDIRECT_SESSION_ATTR_NAME)));
        }

        if ($response->isRedirect() && $response->headers->has('Location')) {
            $session->set(self::REDIRECT_SESSION_ATTR_NAME, $response->headers->get('Location'));
        }
    }

    /**
     * Adds a cookie with the request method for non-GET requests. If a cookie is present and the request is GET, the
     * cookie is removed to work better with caching solutions. The turbolinks will not initialize if the cookie is set.
     *
     * @param Request  $request
     * @param Response $response
     */
    private function addRequestMethodCookie(Request $request, Response $response)
    {
        if ($request->isMethod('GET') && $request->cookies->has(self::REQUEST_METHOD_COOKIE_ATTR_NAME)) {
            $response->headers->clearCookie(self::REQUEST_METHOD_COOKIE_ATTR_NAME);
        }

        if (!$request->isMethod('GET')) {
            $response->headers->setCookie(new Cookie(self::REQUEST_METHOD_COOKIE_ATTR_NAME, $request->getMethod()));
        }
    }

    /**
     * Checks if the request can handle a Turbolink redirect. You need to have a
     * session and a XHR request header to handle a redirect.
     *
     * @param Request $request
     *
     * @return bool
     */
    private function canHandleRedirect(Request $request)
    {
        $session = $request->getSession();

        return $session instanceof SessionInterface && $request->headers->has(self::ORIGIN_REQUEST_HEADER);
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
     * @return bool
     */
    private function haveSameOrigin(Request $request, Response $response)
    {
        $requestOrigin = $this->getUrlOrigin($request->headers->get(self::ORIGIN_REQUEST_HEADER));
        $responseOrigin = $this->getUrlOrigin($response->headers->get(self::ORIGIN_RESPONSE_HEADER));

        return $requestOrigin == $responseOrigin;
    }

    /**
     * Modifies the response status code. Checks for cross domain redirects and
     * blocks them.
     *
     * @param Request  $request
     * @param Response $response
     */
    private function modifyStatusCode(Request $request, Response $response)
    {
        if ($request->headers->has(self::ORIGIN_REQUEST_HEADER)
            && $response->headers->has(self::ORIGIN_RESPONSE_HEADER)
            && !$this->haveSameOrigin($request, $response)
        ) {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
        }
    }
}
