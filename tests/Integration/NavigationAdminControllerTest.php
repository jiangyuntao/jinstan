<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Http\Controllers\Admin\NavigationAdminController;
use Jinstan\Core\View\Presenters\Admin\NavigationListPresenter;
use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\Application\Navigation\Queries\NavigationTranslationResolver;
use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Navigation\NavigationRepository;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\TestUrlGenerator;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class NavigationAdminControllerTest extends TestCase
{
    public function testListPresenterAddsTheUntranslatedPlaceholder(): void
    {
        $presenter = new NavigationListPresenter($this->views());

        $data = $presenter->present([
            'items' => [['id' => 1, 'label' => null]],
            'menus' => [],
            'selectedMenu' => '',
            'locale' => 'en',
            'locales' => [],
        ]);

        self::assertSame('(未翻译)', $data['items'][0]['label']);
    }

    public function testDatabaseErrorsDoNotLeakThroughWriteResponses(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $navigation = new NavigationRepository($connection);
        $navigation->createMenu('primary', '主导航');
        $settings = new SettingsRepository($connection);
        $translations = new NavigationTranslationResolver($navigation, $settings);
        $views = $this->views();
        $controller = new NavigationAdminController(
            new CsrfToken(),
            $settings,
            new NavigationListQuery($navigation, $settings, $translations),
            new NavigationWriter($navigation, $settings),
            new NavigationItemFormQuery($navigation, $settings, $translations),
            new NavigationListPresenter($views),
            $views,
            TestUrlGenerator::adminDestinations(),
            new \Jinstan\Core\Http\ResponseFactory(),
        );
        $request = (new ServerRequest('POST', '/admin/navigation'))->withParsedBody([
            'handle' => 'primary',
            'name' => '重复菜单',
        ]);

        $response = $controller->createMenu($request, []);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            '保存失败，数据可能已被修改或标识重复，请检查后重试。',
            (string) $response->getBody(),
        );
        self::assertStringNotContainsString('UNIQUE', (string) $response->getBody());
    }

    private function views(): AdminViewRenderer
    {
        return new AdminViewRenderer(
            dirname(__DIR__, 2) . '/core/resources/views/admin',
            sys_get_temp_dir() . '/jinstan-navigation-controller',
            TestUrlGenerator::adminViews(),
        );
    }
}
