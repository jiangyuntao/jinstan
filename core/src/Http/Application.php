<?php

declare(strict_types=1);

namespace Jinstan\Core\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Application
{
    public function __construct(private readonly Kernel $kernel)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->kernel->handle($request);
    }
}
