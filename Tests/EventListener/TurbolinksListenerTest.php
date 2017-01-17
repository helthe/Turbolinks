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

use Helthe\Component\Turbolinks\EventListener\TurbolinksListener;
use Helthe\Component\Turbolinks\Turbolinks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TurbolinksListenerTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $kernel;

    protected function setUp()
    {
        $this->dispatcher = new EventDispatcher();
        $this->kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->kernel = null;
    }

    public function testFilterDoesNothingForSubRequests()
    {
        $response = new Response('foo');

        $turbolinks = $this->getTurbolinksMock();
        $turbolinks->expects($this->never())->method('decorateResponse');

        $this->addTurbolinksListener($turbolinks);

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::SUB_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);
    }

    public function testFilterDoesSomethingForMasterRequests()
    {
        $response = new Response('foo');

        $turbolinks = $this->getTurbolinksMock();
        $turbolinks->expects($this->once())->method('decorateResponse');

        $this->addTurbolinksListener($turbolinks);

        $event = new FilterResponseEvent($this->kernel, new Request(), HttpKernelInterface::MASTER_REQUEST, $response);
        $this->dispatcher->dispatch(KernelEvents::RESPONSE, $event);
    }

    /**
     * Gets a mock of the Turbolinks object.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    private function getTurbolinksMock()
    {
        return $this->getMockBuilder('Helthe\Component\Turbolinks\Turbolinks')->getMock();
    }

    /**
     * Get an instance of TurbolinksListener.
     *
     * @param Turbolinks $turbolinks
     *
     * @return TurbolinksListener
     */
    private function addTurbolinksListener(Turbolinks $turbolinks)
    {
        $this->dispatcher->addListener(KernelEvents::RESPONSE, array(new TurbolinksListener($turbolinks), 'onKernelResponse'));
    }
}
