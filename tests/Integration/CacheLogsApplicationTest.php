<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Filesystem\System\RuntimeStorage;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Application\System\Services\CacheLogsMaintenance;
use Jinstan\Core\View\Presenters\Admin\CacheLogsPresenter;
use Jinstan\Core\Application\System\Queries\CacheLogsQuery;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Support\FileSizeFormatter;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class CacheLogsApplicationTest extends TestCase
{
    public function testItReadsCacheSizesAndOnlyTheLogTail(): void
    {
        $storage = $this->storage();
        mkdir($storage . '/cache/latte', 0750, true);
        mkdir($storage . '/logs', 0750, true);
        file_put_contents($storage . '/cache/root.cache', '123');
        file_put_contents($storage . '/cache/latte/view.cache', '12345');
        file_put_contents($storage . '/logs/app.log', str_repeat('a', 10001) . 'tail');

        $query = new CacheLogsQuery(new RuntimeStorage($storage));
        $caches = $query->caches();
        $logs = $query->logs();

        self::assertSame('all', $caches[0]['name']);
        self::assertSame(8, $caches[0]['size']);
        self::assertSame('latte', $caches[1]['name']);
        self::assertSame(5, $caches[1]['size']);
        self::assertSame(10005, $logs['size']);
        self::assertSame(10000, strlen($logs['log']));
        self::assertStringEndsWith('tail', $logs['log']);
    }

    public function testPresenterBuildsCacheAndLogViewModels(): void
    {
        $presenter = new CacheLogsPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-cache-logs-presenter',
                TestUrlGenerator::adminViews(),
            ),
            new CsrfToken(),
            new FileSizeFormatter(),
        );

        $cacheView = $presenter->caches([['name' => 'all', 'path' => '/cache', 'size' => 1024]]);
        $logView = $presenter->logs(['log' => 'entry', 'size' => 2048]);

        self::assertSame('cache', $cacheView->template);
        self::assertSame('全部缓存', $cacheView->data['caches'][0]['name']);
        self::assertSame('1 KB', $cacheView->data['caches'][0]['sizeLabel']);
        self::assertNotSame('', $cacheView->data['csrfToken']);
        self::assertSame('logs', $logView->template);
        self::assertSame('entry', $logView->data['log']);
        self::assertSame('2 KB', $logView->data['sizeLabel']);
    }

    public function testMaintenanceClearsRuntimeFilesAndPreservesGitkeepDirectories(): void
    {
        $storage = $this->storage();
        mkdir($storage . '/cache/removable/nested', 0750, true);
        mkdir($storage . '/cache/preserved', 0750, true);
        mkdir($storage . '/logs', 0750, true);
        file_put_contents($storage . '/cache/removable/nested/item.cache', 'cached');
        file_put_contents($storage . '/cache/preserved/.gitkeep', '');
        file_put_contents($storage . '/cache/preserved/item.cache', 'cached');
        file_put_contents($storage . '/logs/app.log', 'logged');

        $maintenance = new CacheLogsMaintenance(new RuntimeStorage($storage));
        $maintenance->clearCache();
        $maintenance->clearLogs();

        self::assertDirectoryDoesNotExist($storage . '/cache/removable');
        self::assertDirectoryExists($storage . '/cache/preserved');
        self::assertFileExists($storage . '/cache/preserved/.gitkeep');
        self::assertFileDoesNotExist($storage . '/cache/preserved/item.cache');
        self::assertSame('', file_get_contents($storage . '/logs/app.log'));
    }

    private function storage(): string
    {
        return sys_get_temp_dir() . '/jinstan-cache-logs-' . bin2hex(random_bytes(6));
    }
}
