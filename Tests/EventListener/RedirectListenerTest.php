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

use Helthe\Component\Turbolinks\EventListener\RedirectListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RedirectListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $kernel;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $listener = new RedirectListener();
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

        $this->assertFalse($event->getResponse()->headers->has(RedirectListener::RESPONSE_HEADER));
    }

    public function testFilterDoesNothingWhenNormalResponse()
    {
        $response = new Response('foo');

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertFalse($event->getResponse()->headers->has(RedirectListener::RESPONSE_HEADER));
    }

    public function testFilterDoesNothingWhenNoLocationHeader()
    {
        $response = new Response('foo', Response::HTTP_FOUND);

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertFalse($event->getResponse()->headers->has(RedirectListener::RESPONSE_HEADER));
    }

    public function testFilterSetsHeaderWhenRedirectResponse()
    {
        $url = 'http://foo.bar';
        $response = new RedirectResponse($url);

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertTrue($event->getResponse()->headers->has(RedirectListener::RESPONSE_HEADER));
        $this->assertEquals($url, $event->getResponse()->headers->get(RedirectListener::RESPONSE_HEADER));
    }

    public function testFilterSetsHeaderWhenNormalResponse()
    {
        $url = 'http://foo.bar';
        $response = new Response('foo', Response::HTTP_MOVED_PERMANENTLY, array('Location' => $url));

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertTrue($event->getResponse()->headers->has(RedirectListener::RESPONSE_HEADER));
        $this->assertEquals($url, $event->getResponse()->headers->get(RedirectListener::RESPONSE_HEADER));
    }
}
