<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Application\Update\MaintenanceModeGateway;
use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MaintenanceModeMiddleware implements Middleware
{
    /** @param list<string> $allowedPaths */
    public function __construct(
        private readonly MaintenanceModeGateway $maintenance,
        private readonly ResponseFactory $responses,
        private readonly array $allowedPaths = [],
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if (!$this->maintenance->enabled() || $this->isAllowed($request)) {
            return $next->handle($request);
        }

        return $this->responses->text('Service Unavailable', 503, [
            'Cache-Control' => 'no-store',
            'Retry-After' => '60',
        ]);
    }

    private function isAllowed(ServerRequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD'], true)
            && in_array($request->getUri()->getPath(), $this->allowedPaths, true);
    }
}
