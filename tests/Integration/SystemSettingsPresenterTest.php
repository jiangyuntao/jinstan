<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\View\Presenters\Admin\SystemSettingsPresenter;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;
use PHPUnit\Framework\TestCase;

final class SystemSettingsPresenterTest extends TestCase
{
    public function testItBuildsSettingsViewModelAndSafelyNormalizesTab(): void
    {
        $root = dirname(__DIR__, 2);
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, $root . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en zh-CN');
        $settings->set('site.homepage_id', '999');
        $settings->set('routing.protected_slugs', "/admin\n/custom\n/custom/");
        $contents = new ContentRepository($connection);
        $page = $contents->create(new Content(0, 'page', null, 'draft', null, null, []), new ContentTranslation('en', 'Homepage', 'home', null, []));
        $presenter = new SystemSettingsPresenter(
            $contents,
            $settings,
            new SystemSettingsRequest(
                $settings,
                new \Jinstan\Core\Infrastructure\Filesystem\Theme\ThemeFileManager($root . '/themes'),
                $contents,
            ),
        );

        $data = $presenter->present('reading');
        self::assertSame('site', $data['activeTab']);
        self::assertSame('Homepage', $data['pages'][$page->id]);
        self::assertSame('0', $data['settings']['site.homepage_id']);
        self::assertSame(['zh-CN' => '简体中文', 'ko' => '한국어'], $data['adminLanguages']);
        self::assertSame(1, substr_count($data['settings']['routing.protected_slugs'], '/custom'));
        self::assertSame('site', $presenter->present(['nested'])['activeTab']);
    }
}
