<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\DatabaseOperationsAdapter;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\DashboardPresenter;
use Jinstan\Core\Application\Dashboard\Queries\DashboardQuery;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Tests\Support\InstalledTestProject;
use Jinstan\Tests\Support\TestUrlGenerator;
use PHPUnit\Framework\TestCase;

final class DashboardApplicationTest extends TestCase
{
    public function testItQueriesSystemContentAndDatabaseStatus(): void
    {
        $project = InstalledTestProject::create();
        $types = new ContentTypeRegistry();
        $types->register(new ContentTypeDefinition('example', '示例内容', [], 'content-detail', '/example/{slug}'));
        $query = new DashboardQuery(
            new ContentRepository($project->connection()),
            new DatabaseOperationsAdapter($project->connection()),
            $types,
        );

        $dashboard = $query->execute();

        self::assertSame('example', $dashboard['contentTypes'][0]['type']);
        self::assertSame('示例内容', $dashboard['contentTypes'][0]['label']);
        self::assertSame(0, $dashboard['contentTypes'][0]['value']);
        self::assertSame(0, $dashboard['published']);
        self::assertSame(0, $dashboard['drafts']);
        self::assertSame('sqlite', $dashboard['database']['driver']);
        self::assertGreaterThan(0, $dashboard['database']['tables']);
        self::assertGreaterThan(0, $dashboard['database']['migrations']);
        self::assertSame('ok', $dashboard['database']['integrity']);
        self::assertSame('Chart.js', $dashboard['dependencies'][7]['label']);
    }

    public function testPresenterBuildsLocalizedCardsAndSafeChartJson(): void
    {
        $presenter = new DashboardPresenter(
            new AdminViewRenderer(
                dirname(__DIR__, 2) . '/core/resources/views/admin',
                sys_get_temp_dir() . '/jinstan-dashboard-presenter',
                TestUrlGenerator::adminViews(),
            ),
            TestUrlGenerator::contentAdmin(),
        );
        $view = $presenter->present([
            'contentTypes' => [
                ['type' => 'article', 'label' => 'Article', 'value' => 2],
                ['type' => 'custom', 'label' => '<Custom>', 'value' => 1],
            ],
            'published' => 2,
            'drafts' => 1,
            'phpVersion' => '8.3.0',
            'coreVersion' => '1.0.0',
            'dependencies' => [
                ['label' => 'missing/package', 'value' => null, 'url' => 'https://example.test'],
            ],
            'database' => ['driver' => 'pgsql', 'tables' => 4, 'migrations' => 3, 'integrity' => null],
        ]);

        self::assertSame('dashboard', $view->template);
        self::assertSame('文章', $view->data['cards'][0]['label']);
        self::assertSame('/admin/content/custom', $view->data['cards'][1]['url']);
        self::assertSame('未知', $view->data['dependencyItems'][0]['value']);
        self::assertSame('由数据库服务器维护', $view->data['databaseItems'][3]['value']);
        self::assertStringContainsString('\\u003CCustom\\u003E', $view->data['chartDataJson']);
        self::assertStringContainsString('"values":[2,1]', $view->data['chartDataJson']);
    }
}
