<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxonomyDatabaseTest extends TestCase
{
    public static function drivers(): array
    {
        return [['sqlite'], ['mysql'], ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function testEditingAndDeletingPreservesContentsAcrossDatabases(string $driver): void
    {
        $prefix = 'JINSTAN_TEST_' . strtoupper($driver);
        $dsn = $driver === 'sqlite' ? 'sqlite::memory:' : getenv($prefix . '_DSN');
        if (!$dsn) {
            self::markTestSkipped('未配置独立测试数据库：' . $prefix . '_DSN');
        }
        $pdo = new \PDO($dsn, getenv($prefix . '_USER') ?: null, getenv($prefix . '_PASSWORD') ?: null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        $db = new Connection($pdo, $driver);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $repository = new TaxonomyRepository($db);
        $handle = 'test_' . bin2hex(random_bytes(6));
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $contents = new ContentRepository($db);
        $service = new ContentService($types, $contents);
        $contentId = null;
        $repository->createTaxonomy($handle, '测试');
        try {
            $id = $repository->createTerm($handle, 'en', 'Before', $handle);
            $content = $service->create('article', new ContentTranslation('en', 'Preserved', $handle, null, []), [], null);
            $contentId = $content->id;
            $repository->assign($contentId, $id);
            $repository->updateTaxonomy($handle, 'Updated');
            $repository->updateTerm($handle, $id, 'en', 'After', $handle . '-updated');
            self::assertSame('Updated', $repository->taxonomy($handle)['label']);
            self::assertSame('After', $repository->termPage($handle)['items'][0]['name']);
            self::assertSame(1, (int) $repository->termPage($handle)['items'][0]['content_count']);
            self::assertCount(1, $repository->selected($contentId));
            $repository->deleteTerm($handle, $id);
            self::assertSame([], $repository->selected($contentId));
            self::assertSame('Preserved', $contents->translation($contentId, 'en')->title);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM term_translations WHERE term_id = ?', [$id]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM taxonomy_term_positions WHERE term_id = ?', [$id]));
            $repository->deleteTaxonomy($handle, $types);
        } finally {
            // 仅清理本次测试创建的对象，不清空测试库中的其他数据。
            if ($contentId !== null) {
                $db->delete('contents', ['id' => $contentId]);
            }
            $db->delete('taxonomies', ['handle' => $handle]);
        }
    }
}
