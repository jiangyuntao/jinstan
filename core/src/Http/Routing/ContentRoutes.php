<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Routing;

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Http\Controllers\Frontend\PublicContentController;
use Jinstan\Core\Routing\RouteRegistrar;

final class ContentRoutes
{
    public static function register(RouteRegistrar $routes, ContentTypeRegistry $types, PublicContentController $controller): void
    {
        // 先注册全部静态列表，再注册具体详情；通用 Page 规则最后注册。
        foreach ($types->all() as $type) {
            if ($type->listEnabled && !$routes->has('GET', $type->listPath)) {
                $routes->get($type->listPath, static fn ($request, $params) => $controller->listing($request, ['type' => $type->handle]));
            }
        }
        $definitions = array_values($types->all());
        usort($definitions, static fn ($a, $b): int => [strlen(strstr($b->urlPattern, '{slug}', true)), strlen($b->urlPattern)] <=> [strlen(strstr($a->urlPattern, '{slug}', true)), strlen($a->urlPattern)]);
        foreach ($definitions as $type) {
            if (!$routes->has('GET', $type->urlPattern)) {
                $routes->get($type->urlPattern, static fn ($request, $params) => $controller->show($request, ['type' => $type->handle] + $params));
            }
        }
        $prefix = '/{locale:[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*}';
        foreach ($definitions as $type) {
            if ($type->listEnabled) {
                $routes->get($prefix . $type->listPath, static fn ($request, $params) => $controller->listing($request, ['type' => $type->handle] + $params));
            }
        }
        foreach ($definitions as $type) {
            $routes->get($prefix . $type->urlPattern, static fn ($request, $params) => $controller->show($request, ['type' => $type->handle] + $params));
        }
    }
}
