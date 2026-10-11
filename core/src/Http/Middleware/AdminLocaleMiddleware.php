<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Middleware;

use Jinstan\Core\Http\Contracts\Middleware;
use Jinstan\Core\Http\Contracts\RequestHandler;
use Jinstan\Core\I18n\AdminLocale;
use Jinstan\Core\Settings\SettingsReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminLocaleMiddleware implements Middleware
{
    public function __construct(
        private readonly SettingsReadRepository $settings,
        private readonly AdminLocale $locale,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandler $next): ResponseInterface
    {
        $this->locale->use($this->settings->get('admin.locale', 'zh-CN') ?? 'zh-CN');

        return $next->handle($request);
    }
}
