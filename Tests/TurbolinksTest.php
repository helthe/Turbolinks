<?php

/*
 * This file is part of the Helthe Turbolinks package.
 *
 * (c) Carl Alexander <carlalexander@helthe.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Helthe\Component\Turbolinks\Tests;

use Helthe\Component\Turbolinks\Turbolinks;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TurbolinksTest extends \PHPUnit_Framework_TestCase
{
    private $turbolinks;

    protected function setUp()
    {
        $this->turbolinks = new Turbolinks();
    }

    protected function tearDown()
    {
        $this->turbolinks = null;
    }

    public function testDoesNothingWhenNoLocationHeader()
    {
        $request = new Request();
        $response = new Response('foo', Response::HTTP_FOUND);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertFalse($response->headers->has('X-XHR-Redirected-To'));

    }

    public function testDoesNothingWhenNoOriginRequestHeader()
    {
        $request = $this->createRequest('/');
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://bar.foo'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testDoesNothingWhenNoOriginResponseHeader()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo'));
        $response = new Response('foo', Response::HTTP_OK);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testDoesNothingWhenNormalResponse()
    {
        $request = new Request();
        $response = new Response('foo');

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertFalse($response->headers->has('X-XHR-Redirected-To'));
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsForbiddenForDifferentHost()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsForbiddenForDifferentPort()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://foo.bar:8080'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsHeaderWhenNormalResponse()
    {
        $url = 'http://foo.bar';
        $request = new Request();
        $response = new Response('foo', Response::HTTP_MOVED_PERMANENTLY, array('Location' => $url));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertTrue($response->headers->has('X-XHR-Redirected-To'));
        $this->assertEquals($url, $response->headers->get('X-XHR-Redirected-To'));
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsHeaderWhenRedirectResponse()
    {
        $url = 'http://foo.bar';
        $request = new Request();
        $response = new RedirectResponse($url);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertTrue($response->headers->has('X-XHR-Redirected-To'));
        $this->assertEquals($url, $response->headers->get('X-XHR-Redirected-To'));
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsForbiddenForDifferentScheme()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'https://foo.bar'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertContainsRequestMethodCookie($response);
    }

    public function testSetsRequestMethodCookie()
    {
        $method = 'POST';
        $response = new Response('foo');

        $this->turbolinks->decorateResponse(Request::create('/', $method), $response);

        $this->assertContainsRequestMethodCookie($response, $method);
    }

    /**
     * Asserts that a response contains the request method cookie.
     *
     * @param Response $response
     * @param string   $method
     */
    private function assertContainsRequestMethodCookie(Response $response, $method = 'GET')
    {
        $this->assertContains(sprintf('Set-Cookie: %s=%s; path=/; httponly', 'request_method', $method), explode("\r\n", $response->headers->__toString()));
    }

    /**
     * Create a request with specific headers.
     *
     * @param string $uri
     * @param array  $server
     *
     * @return Request
     */
    private function createRequest($uri, array $server = array())
    {
        return Request::create($uri, 'GET', array(), array(), array(), $server);
    }
}
