<?php

/*
 * This file is part of the Helthe Turbolinks package.
 *
 * (c) Carl Alexander <carlalexander@helthe.co>
 * (c) Tortue Torche <tortuetorche@spam.me>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Helthe\Component\Turbolinks;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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
     * Map between Turbolinks options names and Turbolinks HTTP header names
     * See: https://github.com/rails/turbolinks/blob/master/README.md#partial-replacements-with-turbolinks-30
     *
     * @var array
     */
    public static $turbolinksOptionsMap = array(
        // Force a render or normal redirection with Turbolinks.
        // Possible values: `true` or `false`
        'turbolinks' => 'X-Turbolinks-Enabled',

        // Refresh any or partially replace any `data-turbolinks-temporary` nodes
        // and nodes with `id`s matching `comments` or `comments:*`.
        'change'     => 'X-Turbolinks-Change',

        // Refresh or partially replace any `data-turbolinks-temporary` nodes
        // and nodes with `id` not matching `something` and `something:*`.
        'keep'       => 'X-Turbolinks-Keep',

        // Replace the entire `body` of the document,
        // including `data-turbolinks-permanent` nodes. Possible value: `true`
        'flush'      => 'X-Turbolinks-Flush'
    );

    /**
     * Modifies the HTTP headers and status code of the Response so that it can be
     * properly handled by the Turbolinks javascript.
     *
     * @param Request  $request
     * @param Response $response
     */
    public function decorateResponse(Request $request, Response $response)
    {
        if ($request->headers->has(self::ORIGIN_REQUEST_HEADER)) {
            $request->headers->set('referer', $request->headers->get(self::ORIGIN_REQUEST_HEADER));
        }

        $this->addRequestMethodCookie($request, $response);
        $this->modifyStatusCode($request, $response);

        $session = $request->getSession();

        // set 'X-XHR-Redirected-To' header
        if ($session->has(self::REDIRECT_SESSION_ATTR_NAME)) {
            $response->headers->add(
                array(self::REDIRECT_RESPONSE_HEADER => $session->remove(self::REDIRECT_SESSION_ATTR_NAME))
            );
        }

        if (! $response->isRedirect()) {
            $this->render($request, $response);
        } elseif ($response->headers->has('Location')) {
            // Stores the return value (the redirect target url) to persist through to the redirect
            // request, where it will be used to set the X-XHR-Redirected-To response header. The
            // Turbolinks script will detect the header and use replaceState to reflect the redirected
            // url.
            if ($this->canHandleRedirect($request)) {
                $session->set(self::REDIRECT_SESSION_ATTR_NAME, $response->headers->get('Location'));
            }

            $this->redirectTo($request, $response);
        }
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    public function redirectTo($request, $response)
    {
        list($turbolinks, $options) = $this->extractTurbolinksOptions($response->headers);

        if ($turbolinks || (
                $turbolinks !== false &&
                $request->isXmlHttpRequest() &&
                (count($options) > 0 || ! $request->isMethod('GET'))
            )
        ) {
            $this->performTurbolinksResponse(
                $request,
                $response,
                "Turbolinks.visit('".$response->headers->get('Location')."'".$this->turbolinksJsOptions($options).");"
            );
        }

        return $response;
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    public function render($request, $response)
    {
        list($turbolinks, $options) = $this->extractTurbolinksOptions($response->headers);

        if ($turbolinks ||
            ($turbolinks !== false && $request->isXmlHttpRequest() && count($options) > 0)
        ) {
            $this->performTurbolinksResponse(
                $request,
                $response,
                "Turbolinks.replace(".json_encode($response->getContent()).$this->turbolinksJsOptions($options).");"
            );
        }

        return $response;
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
            $response->headers->setCookie(
                new Cookie(self::REQUEST_METHOD_COOKIE_ATTR_NAME, $request->getMethod())
            );
        }
    }

    /**
     * Checks if the request can handle a Turbolink redirect. You need to have a
     * session and a XHR request header to handle a redirect.
     *
     * @param  Request $request
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
     * @param  string $url
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
     * @param  Request  $request
     * @param  Response $response
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

    /**
     * @param  ResponseHeaderBag $headers
     * @return array
     */
    private function extractTurbolinksOptions($headers)
    {
        $optionsFromHeaders = $this->extractTurbolinksHeaders($headers);
        // $headers->add($optionsFromHeaders);

        // $turbolinks = $headers->get('turbolinks');
        // $headers->remove('turbolinks');

        // Equivalent of the `array_pull()` Laravel helper:
        //   $turbolinks = array_pull($optionsFromHeaders, 'turbolinks');
        // See: http://laravel.com/docs/5.1/helpers#method-array-pull
        $turbolinks = null;
        if (isset($optionsFromHeaders['turbolinks'])) {
            $turbolinks = $optionsFromHeaders['turbolinks'];
            unset($optionsFromHeaders['turbolinks']);
        }

        $optionsKeys = array('keep', 'change', 'flush');
        // $turbolinksOptions = array();
        // foreach ($optionsKeys as $key) {
        //     if ($headers->has($key)) {
        //         $turbolinksOptions[$key] = $headers->get($key);
        //         $headers->remove($key);
        //     }
        // }

        // Complex code, equivalent of the `array_only()` Laravel helper:
        //   $turbolinksOptions = array_only($optionsFromHeaders, $optionsKeys);
        // See: http://laravel.com/docs/5.1/helpers#method-array-only
        $turbolinksOptions = array_filter(
            array_intersect_key($optionsFromHeaders, array_flip((array) $optionsKeys))
        );

        if (count($turbolinksOptions) > 1) {
            throw new \InvalidArgumentException("cannot combine 'keep', 'change' and 'flush' options");
        }

        return array($turbolinks, $turbolinksOptions);
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param string   $body
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    private function performTurbolinksResponse(Request $request, Response $response, $body)
    {
        $response->setStatusCode(200);
        $response->setContent($body);
        $response->headers->set('Content-Type', $request->getMimeType('js'));
    }

    /**
     * @param  array $options
     *
     * @return string
     */
    private function turbolinksJsOptions($options)
    {
        if (isset($options['change'])) {
            return ", { change: ['".implode("', '", (array) $options['change'])."'] }";
        }
        if (isset($options['keep'])) {
            return ", { keep: ['".implode("', '", (array) $options['keep'])."'] }";
        }
        if (isset($options['flush'])) {
            return ", { flush: true }";
        }
    }

    /**
     * @param  ResponseHeaderBag $headers
     *
     * @return array Turbolinks Options
     */
    public function extractTurbolinksHeaders($headers)
    {
        $options = array();
        $optionsMap = self::$turbolinksOptionsMap;

        foreach ($headers as $key => $value) {
            if ($result = array_search($key, array_map('strtolower', $optionsMap))) {
                $options[$result] = $value;
                if (is_array($headers)) {
                    unset($headers[$key]);
                } elseif ($headers instanceof ResponseHeaderBag) {
                    $headers->remove($key);
                }
            }
        }

        return $options;
    }

    /**
     * Return HTTP headers equivalent of the given Turbolinks options.
     * E.G. `['change'  => 'comments']` becomes `['X-Turbolinks-Change' => 'comments']`
     * @param  array $options
     *
     * @return array
     */
    public function convertTurbolinksOptions($options = array())
    {
        $headers = array();
        $optionsMap = self::$turbolinksOptionsMap;

        foreach ($options as $key => $value) {
            if (in_array($key, array_keys($optionsMap))) {
                $headers[$optionsMap[$key]] = $value;
            }
        }

        return $headers;
    }
}
