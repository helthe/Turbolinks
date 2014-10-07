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
    /**
     * @var Turbolinks
     */
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
        $this->assertResponseHasNoCookies($response);

    }

    public function testDoesNothingWhenNoOriginRequestHeader()
    {
        $request = $this->createRequest('/');
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://bar.foo'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertResponseHasNoCookies($response);
    }

    public function testDoesNothingWhenNoOriginResponseHeader()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo'));
        $response = new Response('foo', Response::HTTP_OK);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertResponseHasNoCookies($response);
    }

    public function testDoesNothingWhenNormalResponse()
    {
        $request = new Request();
        $response = new Response('foo');

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertFalse($response->headers->has('X-XHR-Redirected-To'));
        $this->assertResponseHasNoCookies($response);
    }

    public function testDoesNothingWhenNoSession()
    {
        $request = new Request();
        $response = new RedirectResponse('http://foo.bar/redirect');

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertFalse($response->headers->has('X-XHR-Redirected-To'));
        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsForbiddenForDifferentHost()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsForbiddenForDifferentPort()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://foo.bar:8080'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertResponseHasNoCookies($response);
    }

    public function testDoesntSetSessionRedirectWhenNoHeader()
    {
        $url = 'http://foo.bar/redirect';
        $request = new Request();
        $response = new RedirectResponse($url);
        $session = $this->getSessionMock();

        $session->expects($this->never())
                ->method('set');

        $request->setSession($session);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertFalse($response->headers->has('X-XHR-Redirected-To'));
        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsSessionRedirectWhenRedirect()
    {
        $url = 'http://foo.bar/redirect';
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://foo.bar'));
        $response = new RedirectResponse($url);
        $session = $this->getSessionMock();

        $session->expects($this->once())
                ->method('set')
                ->with($this->equalTo('helthe_turbolinks_redirect_to'), $this->equalTo($url));

        $request->setSession($session);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsHeaderWhenSessionHasRedirect()
    {
        $url = 'http://foo.bar/redirect';
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://foo.bar'));
        $response = new Response();
        $session = $this->getSessionMock();

        $session->expects($this->once())
                ->method('has')
                ->with($this->equalTo('helthe_turbolinks_redirect_to'))
                ->will($this->returnValue(true));

        $session->expects($this->once())
                ->method('remove')
                ->with($this->equalTo('helthe_turbolinks_redirect_to'))
                ->will($this->returnValue($url));

        $request->setSession($session);

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertTrue($response->headers->has('X-XHR-Redirected-To'));
        $this->assertEquals($url, $response->headers->get('X-XHR-Redirected-To'));
        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsForbiddenForDifferentScheme()
    {
        $request = $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'https://foo.bar'));
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertResponseHasNoCookies($response);
    }

    public function testSetsRequestMethodCookie()
    {
        $method = 'POST';
        $response = new Response('foo');

        $this->turbolinks->decorateResponse(Request::create('/', $method), $response);

        $this->assertContainsRequestMethodCookie($response, $method);
    }

    public function testDeletesRequestMethodCookie()
    {
        $request = Request::create('/', 'GET', array(), array('request_method' => 'POST'));
        $response = new Response('foo');

        $this->turbolinks->decorateResponse($request, $response);

        $this->assertRegExp('/Set-Cookie: request_method=deleted;/m', $response->headers->__toString());
    }

    /**
     * Asserts that a response contains the request method cookie.
     *
     * @param Response $response
     * @param string   $method
     */
    private function assertContainsRequestMethodCookie(Response $response, $method)
    {
        $this->assertContains(sprintf('Set-Cookie: %s=%s; path=/; httponly', 'request_method', $method), explode("\r\n", $response->headers->__toString()));
    }

    /**
     * Asserts that a response has no cookies.
     *
     * @param Response $response
     */
    private function assertResponseHasNoCookies(Response $response)
    {
        $this->assertEmpty($response->headers->getCookies());
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

    /**
     * Get a mock of a Symfony Session.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function getSessionMock()
    {
        return $this->getMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
    }
}
