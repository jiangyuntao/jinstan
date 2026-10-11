<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Routing\ProtectedSlug;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class ProtectedSlugTest extends TestCase
{
    public function testCommonWebsiteRoutesAreProtectedByDefault(): void
    {
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $settings = new SettingsRepository($db);

        $paths = (new ProtectedSlug($types, $settings))->paths();

        self::assertContains('category', $paths);
        self::assertContains('tag', $paths);
        self::assertContains('sitemap.xml', $paths);
        self::assertContains('rss', $paths);
        self::assertContains('search', $paths);
        self::assertContains('api', $paths);
        self::assertContains('assets', $paths);
    }

    public function testRootPageCannotUseProtectedPathButNestedArticleCan(): void
    {
        $db = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($db, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $settings = new SettingsRepository($db);
        $service = new ContentService($types, new ContentRepository($db), protectedSlugs: new ProtectedSlug($types, $settings));

        $article = $service->create('article', new ContentTranslation('en', 'Category article', 'category', null, []));
        self::assertGreaterThan(0, $article->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('URL 路径受系统保护');
        $service->create('page', new ContentTranslation('en', 'Conflicting page', 'category', null, []));
    }
}
