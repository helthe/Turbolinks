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
        // Block cross domain redirects
        if ($request->headers->has(self::ORIGIN_REQUEST_HEADER)
            && $response->headers->has(self::ORIGIN_RESPONSE_HEADER)
            && !$this->haveSameOrigin($request, $response)
        ) {
            $response->setStatusCode(Response::HTTP_FORBIDDEN);
        }

        // Add redirect response headers to redirects
        if ($response->isRedirect() && $response->headers->has('Location')) {
            $response->headers->add(array(self::REDIRECT_RESPONSE_HEADER => $response->headers->get('Location')));
        }

        // Mandatory request method cookie
        $response->headers->setCookie(
            new Cookie(
                self::REQUEST_METHOD_COOKIE_ATTR_NAME,
                $request->getMethod()
            )
        );
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
    private function haveSameOrigin(Request $request, Response $response)
    {
        $requestOrigin = $this->getUrlOrigin($request->headers->get(self::ORIGIN_REQUEST_HEADER));
        $responseOrigin = $this->getUrlOrigin($response->headers->get(self::ORIGIN_RESPONSE_HEADER));

        return $requestOrigin == $responseOrigin;
    }
}
