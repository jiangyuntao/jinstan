<?php

declare(strict_types=1);

namespace Jinstan\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ControllerApplicationBoundaryTest extends TestCase
{
    public function testAdminControllersDependOnViewContract(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Http/Controllers/Admin';
        $violations = [];
        foreach (glob($root . '/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (str_contains($source, 'Infrastructure\\View\\AdminViewRenderer')) {
                $violations[] = basename($file);
            }
        }

        self::assertSame([], $violations);
    }

    public function testFrontendControllersReceiveResponseFactory(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Http/Controllers/Frontend';
        $sources = (string) file_get_contents($root . '/PublicContentController.php')
            . (string) file_get_contents($root . '/PublicMediaController.php')
            . (string) file_get_contents($root . '/SitemapController.php');

        self::assertStringNotContainsString('new ResponseFactory', $sources);
        self::assertStringNotContainsString('?ResponseFactory', $sources);
    }

    public function testAdminControllersReceiveResponseFactory(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Http/Controllers/Admin';
        $sources = '';
        foreach (glob($root . '/*Controller.php') ?: [] as $file) {
            $sources .= (string) file_get_contents($file);
        }

        self::assertStringNotContainsString('new ResponseFactory', $sources);
        self::assertStringNotContainsString('?ResponseFactory', $sources);
    }

    public function testContentTypeControllerRequiresItsValidationDependencies(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/core/src/Http/Controllers/Admin/ContentTypeAdminController.php',
        );

        self::assertStringNotContainsString('?TaxonomyReadRepository', $source);
        self::assertStringNotContainsString('?TemplateCatalogReader', $source);
        self::assertStringNotContainsString('?Router', $source);
        self::assertStringNotContainsString('?ContentTypeDeletionService', $source);
        self::assertStringNotContainsString('?->', $source);
    }

    public function testContentFormChainRequiresTaxonomyAndTemplateDependencies(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src';
        $sources = (string) file_get_contents($root . '/View/Presenters/Admin/ContentFormPresenter.php')
            . (string) file_get_contents($root . '/Application/Content/Queries/ContentSearchQuery.php');

        self::assertStringNotContainsString('?TaxonomyReadRepository', $sources);
        self::assertStringNotContainsString('?TemplateCatalogReader', $sources);
        self::assertStringNotContainsString('$this->taxonomy?->', $sources);
        self::assertStringNotContainsString('$this->templates?->', $sources);
    }

    public function testMigratedControllersDoNotConstructQueriesOrDependOnDatabaseConnections(): void
    {
        $root = dirname(__DIR__, 2) . '/core/src/Http/Controllers';
        $files = [
            $root . '/Admin/DashboardController.php',
            $root . '/Admin/DatabaseToolsController.php',
            $root . '/Admin/CacheLogsController.php',
            $root . '/Admin/ContentAdminController.php',
            $root . '/Admin/PluginAdminController.php',
            $root . '/Admin/NavigationAdminController.php',
            $root . '/Admin/RoleAdminController.php',
            $root . '/Admin/SettingsAdminController.php',
            $root . '/Admin/ThemeAdminController.php',
            $root . '/Admin/TaxonomyAdminController.php',
            $root . '/Admin/UserAdminController.php',
            $root . '/Frontend/SitemapController.php',
            $root . '/Frontend/PublicContentController.php',
            $root . '/Installer/InstallController.php',
        ];

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            self::assertStringNotContainsString('Jinstan\\Core\\Database\\Connection', $source, $file);
            self::assertDoesNotMatchRegularExpression(
                '/new (DashboardQuery|DatabaseToolsQuery|DatabaseMaintenance|CacheLogsQuery|CacheLogsPresenter|CacheLogsMaintenance|ContentRequest|ContentAttachmentCoordinator|ContentListQuery|ContentListPresenter|ContentFormPresenter|ContentSearchQuery|ContentEditQuery|CreateContent|UpdateContent|DeleteContent|PluginAdminQuery|PluginAdminPresenter|PluginWriter|NavigationTranslationResolver|NavigationListQuery|NavigationWriter|NavigationItemFormQuery|NavigationListPresenter|RoleAdminQuery|RoleAdminPresenter|RoleWriter|SystemSettingsPresenter|SaveSystemSettings|TaxonomyTermListQuery|TaxonomyTranslationResolver|ReorderTaxonomyTerms|SubmitTaxonomyForm|TaxonomyFormQuery|TaxonomyFormPresenter|TaxonomyIndexQuery|TaxonomyListPresenter|ThemeFileManager|ThemeManagerQuery|ThemeWriter|UserAdminQuery|UserAdminPresenter|UserWriter|SitemapQuery|PublicHomepageQuery|PublicContentPresenter|PublicContentQuery|PublicNotFoundQuery|PublicMediaFileReader|InstallPresenter)\\b/',
                $source,
                $file,
            );
        }
    }
}
