<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Routing\UrlGenerator;

final class DashboardPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
    ) {
    }

    /**
     * @param array<string, mixed> $dashboard
     */
    public function present(array $dashboard): AdminPageView
    {
        $contentTypes = array_map(fn (array $type): array => [
            ...$type,
            'label' => $this->contentTypeLabel($type['type'], $type['label']),
            'url' => $this->urls->route('admin.content.index', ['type' => $type['type']]),
        ], $dashboard['contentTypes']);
        $database = $dashboard['database'];

        return new AdminPageView(
            'dashboard',
            $this->views->translate('后台概览'),
            [
                'cards' => array_map(static fn (array $item): array => [
                    'label' => $item['label'],
                    'value' => $item['value'],
                    'url' => $item['url'],
                ], $contentTypes),
                'systemItems' => [
                    ['label' => $this->views->translate('PHP'), 'value' => $dashboard['phpVersion']],
                    ['label' => $this->views->translate('Core 版本'), 'value' => $dashboard['coreVersion']],
                ],
                'dependencyItems' => array_map(fn (array $item): array => [
                    ...$item,
                    'value' => $item['value'] ?? $this->views->translate('未知'),
                ], $dashboard['dependencies']),
                'databaseItems' => [
                    ['label' => $this->views->translate('驱动'), 'value' => $database['driver']],
                    ['label' => $this->views->translate('数据表'), 'value' => (string) $database['tables']],
                    ['label' => $this->views->translate('已执行迁移'), 'value' => (string) $database['migrations']],
                    [
                        'label' => $this->views->translate('完整性'),
                        'value' => $database['integrity'] ?? $this->views->translate('由数据库服务器维护'),
                    ],
                ],
                'chartDataJson' => json_encode([
                    'content' => [
                        'labels' => array_column($contentTypes, 'label'),
                        'values' => array_column($contentTypes, 'value'),
                    ],
                    'status' => [
                        'labels' => [$this->views->translate('已发布'), $this->views->translate('草稿')],
                        'values' => [$dashboard['published'], $dashboard['drafts']],
                    ],
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR),
            ],
        );
    }

    private function contentTypeLabel(string $type, string $fallback): string
    {
        return match ($type) {
            'article' => $this->views->translate('admin.articles'),
            'page' => $this->views->translate('admin.pages'),
            'product' => $this->views->translate('admin.products'),
            default => $fallback,
        };
    }
}
