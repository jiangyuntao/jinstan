<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class StartSessionMiddleware implements Middleware
{
    private const IDLE_TIMEOUT = 7200;
    private const ABSOLUTE_TIMEOUT = 43200;
    private const STARTED_AT = '_jinstan_session_started_at';
    private const LAST_ACTIVITY_AT = '_jinstan_session_last_activity_at';

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new \RuntimeException('PHP Session is unavailable.');
        }
        if (session_status() === PHP_SESSION_NONE) {
            $this->configureCookie($request);
            if (!session_start()) {
                throw new \RuntimeException('Unable to start PHP Session.');
            }
        }
        $this->expireStaleSession();

        return $next->handle($request);
    }

    private function configureCookie(ServerRequestInterface $request): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $current = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $current['lifetime'],
            'path' => $current['path'] !== '' ? $current['path'] : '/',
            'domain' => $current['domain'],
            'secure' => $current['secure'] || strtolower($request->getUri()->getScheme()) === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function expireStaleSession(): void
    {
        $now = time();
        $startedAt = $_SESSION[self::STARTED_AT] ?? null;
        $lastActivityAt = $_SESSION[self::LAST_ACTIVITY_AT] ?? null;
        $expired = is_int($startedAt) && $startedAt + self::ABSOLUTE_TIMEOUT < $now;
        $expired = $expired || (is_int($lastActivityAt) && $lastActivityAt + self::IDLE_TIMEOUT < $now);
        if ($expired) {
            $_SESSION = [];
            session_regenerate_id(true);
            $startedAt = null;
        }
        $_SESSION[self::STARTED_AT] = is_int($startedAt) ? $startedAt : $now;
        $_SESSION[self::LAST_ACTIVITY_AT] = $now;
    }
}
