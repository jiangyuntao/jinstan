<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Middleware
{
    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface;
}
