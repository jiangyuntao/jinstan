<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface RequestHandler
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
}
