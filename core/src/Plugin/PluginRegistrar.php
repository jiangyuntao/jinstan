<?php

declare(strict_types=1);

namespace Jinstan\Core\Plugin;

use Jinstan\Core\Component\ComponentRegistrar;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTypeRegistrar;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\I18n\TranslationRegistrar;
use Jinstan\Core\Field\FieldTypeRegistrar;
use Jinstan\Core\Event\EventListenerRegistrar;
use Jinstan\Core\Routing\RouteRegistrar;
use Jinstan\Core\Security\CsrfTokenProvider;
use Jinstan\Core\Security\RateLimiter;
use Jinstan\Core\SEO\SchemaRegistrar;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\View\ViewRegistrar;
use Jinstan\Core\View\ViewRenderer;

final readonly class PluginRegistrar
{
    public function __construct(
        private RouteRegistrar $routes,
        private ComponentRegistrar $components,
        private ContentReadRepository $contents,
        private CsrfTokenProvider $csrf,
        private SettingsReadRepository $settings,
        private ResponseFactory $responses,
        private ?ContentTypeRegistrar $contentTypes = null,
        private ?PluginServiceRegistrar $services = null,
        private ?FieldTypeRegistrar $fieldTypes = null,
        private ?EventListenerRegistrar $events = null,
        private ?TranslationRegistrar $translations = null,
        private ?ViewRegistrar $views = null,
        private ?ViewRenderer $viewRenderer = null,
        private ?SchemaRegistrar $schemas = null,
        private ?RateLimiter $rateLimiter = null,
    ) {
    }

    public function routes(): RouteRegistrar
    {
        return $this->routes;
    }

    public function components(): ComponentRegistrar
    {
        return $this->components;
    }

    public function contents(): ContentReadRepository
    {
        return $this->contents;
    }

    public function csrf(): CsrfTokenProvider
    {
        return $this->csrf;
    }

    public function settings(): SettingsReadRepository
    {
        return $this->settings;
    }

    public function responses(): ResponseFactory
    {
        return $this->responses;
    }

    public function contentTypes(): ContentTypeRegistrar
    {
        return $this->contentTypes ?? throw new \RuntimeException('Content Type Registrar 不可用。');
    }

    public function services(): PluginServiceRegistrar
    {
        return $this->services ?? throw new \RuntimeException('Plugin Service Registrar 不可用。');
    }

    public function fieldTypes(): FieldTypeRegistrar
    {
        return $this->fieldTypes ?? throw new \RuntimeException('Field Type Registrar 不可用。');
    }

    public function events(): EventListenerRegistrar
    {
        return $this->events ?? throw new \RuntimeException('Event Listener Registrar 不可用。');
    }

    public function translations(): TranslationRegistrar
    {
        return $this->translations ?? throw new \RuntimeException('Translation Registrar 不可用。');
    }

    public function views(): ViewRegistrar
    {
        return $this->views ?? throw new \RuntimeException('View Registrar 不可用。');
    }

    public function viewRenderer(): ViewRenderer
    {
        return $this->viewRenderer ?? throw new \RuntimeException('View Renderer 不可用。');
    }

    public function schemas(): SchemaRegistrar
    {
        return $this->schemas ?? throw new \RuntimeException('Schema Registrar 不可用。');
    }

    public function rateLimiter(): RateLimiter
    {
        return $this->rateLimiter ?? throw new \RuntimeException('Rate Limiter 不可用。');
    }
}
