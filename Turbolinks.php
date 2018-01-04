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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    const ORIGIN_REQUEST_HEADER = 'Turbolinks-Referrer';

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
    const REDIRECT_RESPONSE_HEADER = 'Turbolinks-Location';

    /**
     * Session attribute name for the redirect location.
     *
     * @var string
     */
    const LOCATION_SESSION_ATTR_NAME = 'helthe_turbolinks_location';

    /**
     * @var array
     */
    public static $turbolinksOptionsMap = array(
        // Handles normal redirection with Turbolinks, if not set to `false`.
        // Possible values: `null`, `'replace'`, `'advance'` or `false`
        'turbolinks' => 'X-Turbolinks',
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

        $this->setTurbolinksLocationHeaderFromSession($request, $response);

        if ($response->isRedirect() && $response->headers->has(self::ORIGIN_RESPONSE_HEADER)) {
            $this->redirectTo($request, $response);
        }

        $this->modifyStatusCode($request, $response);
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function redirectTo($request, $response)
    {
        $turbolinks = $this->extractTurbolinksOptions($response->headers);

        if (
            $turbolinks !== false &&
            $request->isXmlHttpRequest() && ! $request->isMethod('GET')
        ) {
            $location = $response->headers->get(self::ORIGIN_RESPONSE_HEADER);
            $turbolinksContent = $this->visitLocationWithTurbolinks($location, $turbolinks);
            $this->performTurbolinksResponse($request, $response, $turbolinksContent);
        } elseif ($this->canHandleRedirect($request)) {
            $this->storeTurbolinksLocationInSession($request, $response);
        }

        return $response;
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
        return (is_a($session, '\Symfony\Component\HttpFoundation\Session\SessionInterface') ||  is_a($session, '\Illuminate\Contracts\Session\Session')) &&
            $request->headers->has(self::ORIGIN_REQUEST_HEADER);
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
     * @return mixed
     */
    private function extractTurbolinksOptions($headers)
    {
        $options = $this->extractTurbolinksHeaders($headers);

        // Equivalent of the `array_pull()` Laravel helper:
        //   $turbolinks = array_pull($options, 'turbolinks');
        // See: http://laravel.com/docs/5.1/helpers#method-array-pull
        $turbolinks = null;
        if (isset($options['turbolinks'])) {
            $turbolinks = $options['turbolinks'];
            unset($options['turbolinks']);
        }

        return $turbolinks;
    }

    private function visitLocationWithTurbolinks($location, $action)
    {
        $visitOptions = array(
          'action' => is_string($action) && $action === "advance" ? $action : "replace"
        );

        $script = array();
        $script[] = "Turbolinks.clearCache();";
        $script[] = "Turbolinks.visit(".json_encode($location, JSON_UNESCAPED_SLASHES).", ".json_encode($visitOptions).");";

        return implode(PHP_EOL, $script);
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    private function storeTurbolinksLocationInSession(Request $request, Response $response)
    {
        // Stores the return value (the redirect target url) to persist through to the redirect
        // request, where it will be used to set the Turbolinks-Location response header. The
        // Turbolinks script will detect the header and use replaceState to reflect the redirected
        // url.
        $session = $request->getSession();
        if ($session) {
            $location = $response->headers->get(self::ORIGIN_RESPONSE_HEADER);
            $setMethod = method_exists($session, 'put') ? 'put' : 'set';
            $session->$setMethod(self::LOCATION_SESSION_ATTR_NAME, $location);
        }
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param string   $body Content of the response
     */
    private function performTurbolinksResponse(Request $request, Response $response, $body)
    {
        $response->headers->set('Content-Type', $request->getMimeType('js'));
        $response->setStatusCode(200);
        $response->setContent($body);
    }

    /**
     * @param Request  $request
     * @param Response $response
     */
    private function setTurbolinksLocationHeaderFromSession(Request $request, Response $response)
    {
        $session = $request->getSession();

        // set 'Turbolinks-Location' header
        if ($session && $session->has(self::LOCATION_SESSION_ATTR_NAME)) {
            $location = $session->remove(self::LOCATION_SESSION_ATTR_NAME);
            $response->headers->add(
                array(self::REDIRECT_RESPONSE_HEADER => $location)
            );
        }
    }

    /**
     * @param  ResponseHeaderBag $headers
     *
     * @return array Turbolinks options
     */
    public function extractTurbolinksHeaders($headers)
    {
        $options = array();
        $optionsMap = self::$turbolinksOptionsMap;

        foreach ($headers as $key => $value) {
            if ($result = array_search($key, array_map('strtolower', $optionsMap))) {
                if (is_array($value) && count($value) === 1 && array_key_exists(0, $value)) {
                    $value = $value[0];
                }
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
     * E.G. `['turbolinks'  => 'advance']` becomes `['X-Turbolinks' => 'advance']`
     *
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
