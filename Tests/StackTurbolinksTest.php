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

use Helthe\Component\Turbolinks\StackTurbolinks;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class StackTurbolinksTest extends \PHPUnit_Framework_TestCase
{
    public function testDoesNothingForSubRequests()
    {
        $app = $this->getDecoratedAppMock();
        $turbolinks = $this->getTurbolinksMock();
        $turbolinks->expects($this->never())->method('decorateResponse');

        $stack = new StackTurbolinks($app, $turbolinks);

        $stack->handle(new Request(), HttpKernelInterface::SUB_REQUEST);
    }

    public function testDoesSomethingForMasterRequests()
    {
        $app = $this->getDecoratedAppMock();
        $turbolinks = $this->getTurbolinksMock();
        $turbolinks->expects($this->once())->method('decorateResponse');

        $stack = new StackTurbolinks($app, $turbolinks);

        $stack->handle(new Request(), HttpKernelInterface::MASTER_REQUEST);
    }

    /**
     * Gets a mock of the decorated HttpKernelInterface app.
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    public function getDecoratedAppMock()
    {
        $decoratedApp = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $decoratedApp->expects($this->once())->method('handle')->will($this->returnValue(new Response()));

        return $decoratedApp;
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
}
