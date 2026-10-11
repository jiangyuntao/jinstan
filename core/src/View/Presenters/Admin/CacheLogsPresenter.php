<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Support\FileSizeFormatter;

final class CacheLogsPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly CsrfToken $csrf,
        private readonly FileSizeFormatter $fileSizes,
    ) {
    }

    /**
     * @param list<array{name: string, path: string, size: int}> $caches
     */
    public function caches(array $caches): AdminPageView
    {
        foreach ($caches as &$cache) {
            if ($cache['name'] === 'all') {
                $cache['name'] = $this->views->translate('全部缓存');
            }
            $cache['sizeLabel'] = $this->fileSizes->format($cache['size']);
        }
        unset($cache);

        return new AdminPageView(
            'cache',
            $this->views->translate('缓存管理'),
            ['csrfToken' => $this->csrf->value(), 'caches' => $caches],
        );
    }

    /**
     * @param array{log: string, size: int} $logs
     */
    public function logs(array $logs): AdminPageView
    {
        return new AdminPageView(
            'logs',
            $this->views->translate('日志'),
            [
                'csrfToken' => $this->csrf->value(),
                'log' => $logs['log'],
                'size' => $logs['size'],
                'sizeLabel' => $this->fileSizes->format($logs['size']),
            ],
        );
    }
}
