<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminFormFeedbackMiddleware implements Middleware
{
    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        $response = $next->handle($request);
        if ($request->getMethod() !== 'POST'
            || !str_starts_with($request->getUri()->getPath(), '/admin')
            || $response->getStatusCode() < 300
            || $response->getStatusCode() >= 400) {
            return $response;
        }

        $location = $response->getHeaderLine('Location');
        if ($location === '' || !str_starts_with($location, '/admin')) {
            return $response;
        }

        [$target, $fragment] = array_pad(explode('#', $location, 2), 2, null);
        $target .= (str_contains($target, '?') ? '&' : '?') . '_feedback=saved';

        return $response->withHeader('Location', $target . ($fragment === null ? '' : '#' . $fragment));
    }
}
