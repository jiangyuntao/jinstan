<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentListingDatabaseTest extends TestCase
{
    public static function drivers(): array
    {
        return [['sqlite'], ['mysql'], ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function testPublishedPaginationFiltersLocaleAndType(string $driver): void
    {
        $prefix = 'JINSTAN_TEST_' . strtoupper($driver);
        $dsn = $driver === 'sqlite' ? 'sqlite::memory:' : getenv($prefix . '_DSN');
        if (!$dsn) {
            self::markTestSkipped('未配置独立测试数据库：' . $prefix . '_DSN');
        }
        $db = new Connection(new \PDO($dsn, getenv($prefix . '_USER') ?: null, getenv($prefix . '_PASSWORD') ?: null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]), $driver);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $handle = 'listing_' . bin2hex(random_bytes(5));
        $types = new ContentTypeRegistry();
        $types->register(new ContentTypeDefinition($handle, 'Listing test', [], 'content-detail', '/listing/{slug}'));
        $repository = new ContentRepository($db);
        $service = new ContentService($types, $repository);
        $ids = [];
        try {
            for ($i = 1; $i <= 4; ++$i) {
                $content = $service->create($handle, new ContentTranslation('en', 'Item ' . $i, $handle . '-' . $i, null, []));
                $ids[] = $content->id;
                if ($i < 4) {
                    $service->publish($content->id);
                }
            }
            $service->update($ids[0], new ContentTranslation('zh-CN', '中文', $handle . '-zh', null, []), []);
            $adminPage = $repository->adminPage($handle, 'published', 2, 2);
            self::assertSame(3, $adminPage['total']);
            self::assertSame(2, $adminPage['pages']);
            self::assertSame([$ids[0]], array_map(static fn ($content): int => $content->id, $adminPage['items']));
            $page = $repository->publishedPage($handle, 'en', 1, 2);
            self::assertSame(3, $page['total']);
            self::assertSame(2, $page['pages']);
            self::assertSame(['Item 3', 'Item 2'], array_column($page['items'], 'title'));
            self::assertSame(['Item 1'], array_column($repository->publishedPage($handle, 'en', 2, 2)['items'], 'title'));
            self::assertSame([], $repository->publishedPage($handle, 'en', 3, 2)['items']);
            self::assertSame(1, $repository->publishedPage($handle, 'zh-CN')['total']);
            self::assertSame(0, $repository->publishedPage($handle . '_other', 'en')['total']);
        } finally {
            foreach ($ids as $id) {
                $repository->delete($id);
            }
        }
    }
}
