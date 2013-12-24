<?php

/*
 * This file is part of the Helthe Turbolinks package.
 *
 * (c) Carl Alexander <carlalexander@helthe.co>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Helthe\Component\Turbolinks\Tests\EventListener;

use Helthe\Component\Turbolinks\EventListener\CrossDomainListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CrossDomainListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $kernel;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $listener = new CrossDomainListener();
        $this->dispatcher->addListener(KernelEvents::RESPONSE, array($listener, 'onKernelResponse'));

        $this->kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->kernel = null;
    }

    public function testFilterDoesNothingForSubRequests()
    {
        $response = new Response('foo');

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals($response->getStatusCode(), $event->getResponse()->getStatusCode());
    }

    public function testFilterDoesNothingWhenNoLocationHeader()
    {
        $response = new Response('foo');

        $event = new FilterResponseEvent($this->kernel, $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo')), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals($response->getStatusCode(), $event->getResponse()->getStatusCode());
    }

    public function testFilterDoesNothingWhenNoXHRRefererHeader()
    {
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals($response->getStatusCode(), $event->getResponse()->getStatusCode());
    }

    public function testFilterSetsForbiddenForDifferentHost()
    {
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $event = new FilterResponseEvent($this->kernel, $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://bar.foo')), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    public function testFilterSetsForbiddenForDifferentPort()
    {
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $event = new FilterResponseEvent($this->kernel, $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'http://foo.bar:8080')), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    public function testFilterSetsForbiddenForDifferentScheme()
    {
        $response = new Response('foo', Response::HTTP_OK, array('Location' => 'http://foo.bar'));

        $event = new FilterResponseEvent($this->kernel, $this->createRequest('/', array('HTTP_X_XHR_REFERER' => 'https://foo.bar')), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $event->getResponse()->getStatusCode());
    }

    /**
     * Create a request.
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
