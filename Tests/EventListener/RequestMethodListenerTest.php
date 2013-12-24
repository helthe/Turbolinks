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

use Helthe\Component\Turbolinks\EventListener\RequestMethodListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;

class RequestMethodListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $kernel;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $listener = new RequestMethodListener();
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

        $this->assertNotContains("Set-Cookie: request_method=GET; path=/; httponly", explode("\r\n", $event->getResponse()->headers->__toString()));
    }

    public function testFilterSetsCookieForMasterRequests()
    {
        $method = 'POST';
        $response = new Response('foo');

        $event = new FilterResponseEvent($this->kernel, Request::create('/', $method), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertContains("Set-Cookie: request_method=$method; path=/; httponly", explode("\r\n", $event->getResponse()->headers->__toString()));
    }
}
