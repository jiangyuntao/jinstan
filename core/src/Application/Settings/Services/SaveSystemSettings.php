<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Settings\Services;

use Jinstan\Core\Settings\SettingsRepositoryContract;
use Psr\Http\Message\UriInterface;

final class SaveSystemSettings
{
    public function __construct(private readonly SettingsRepositoryContract $settings)
    {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public function execute(array $validated, UriInterface $requestUri): void
    {
        $this->settings->saveSystem([
            'site.name' => $validated['site_name'],
            'site.tagline' => $validated['site_tagline'],
            'site.description' => $validated['site_description'],
            'site.url' => $this->requestOrigin($requestUri),
            'site.contact_email' => $validated['contact_email'],
            'site.footer' => $validated['site_footer'],
            'site.default_locale' => $validated['frontend_locale'],
            'admin.locale' => $validated['admin_locale'],
            'site.timezone' => $validated['timezone'],
            'site.theme' => $validated['theme'],
            ...$validated['predefined_pages'],
            'site.date_format' => $validated['date_format'],
            'content.per_page' => (string) $validated['content_per_page'],
            'content.sort' => $validated['content_sort'],
            'content.default_status' => $validated['default_content_status'],
            ...$validated['seo_values'],
            ...$validated['sns_values'],
            'routing.protected_slugs' => implode("\n", $validated['protected_slugs']),
        ], $validated['custom_values']);
    }

    private function requestOrigin(UriInterface $uri): string
    {
        $scheme = mb_strtolower($uri->getScheme());
        $host = $uri->getHost();
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return rtrim($this->settings->get('site.url', '') ?? '', '/');
        }

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }
        $port = $uri->getPort();

        return $scheme . '://' . $host . ($port === null ? '' : ':' . $port);
    }
}
