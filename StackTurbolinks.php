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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Stack middleware for Turbolinks.
 *
 * @author Carl Alexander <carlalexander@helthe.co>
 */
class StackTurbolinks implements HttpKernelInterface
{
    /**
     * @var HttpKernelInterface
     */
    private $app;

    /**
     * @var Turbolinks
     */
    private $turbolinks;

    /**
     * Constructor.
     *
     * @param HttpKernelInterface $app
     * @param Turbolinks          $turbolinks
     */
    public function __construct(HttpKernelInterface $app, Turbolinks $turbolinks)
    {
        $this->app = $app;
        $this->turbolinks = $turbolinks;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        $response = $this->app->handle($request, $type, $catch);

        if (self::MASTER_REQUEST === $type) {
            $this->turbolinks->decorateResponse($request, $response);
        }

        return $response;
    }
}
