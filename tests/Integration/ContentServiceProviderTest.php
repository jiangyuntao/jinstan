<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentAdminReadRepository;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentWriteRepository;
use Jinstan\Core\Content\ContentRelationReadRepository;
use Jinstan\Core\Content\ContentRelationWriteRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;
use Jinstan\Core\Taxonomy\TaxonomyWriteRepository;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Providers\ContentServiceProvider;
use Jinstan\Core\Providers\DatabaseServiceProvider;
use Jinstan\Core\Providers\FoundationServiceProvider;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Settings\SettingsRepositoryContract;
use Jinstan\Core\Settings\SettingsWriteRepository;
use Jinstan\Tests\Support\InstalledTestProject;
use PHPUnit\Framework\TestCase;

final class ContentServiceProviderTest extends TestCase
{
    public function testItDoesNotRegisterContentServicesBeforeInstallation(): void
    {
        $application = $this->application(InstalledTestProject::create(false));
        $container = $application->container();

        self::assertFalse($container->has(SettingsRepository::class));
        self::assertFalse($container->has(ContentTypeRegistry::class));
        self::assertFalse($container->has(ContentRepository::class));
    }

    public function testItRegistersSharedContentInfrastructureForAnInstalledProject(): void
    {
        $application = $this->application(InstalledTestProject::create());
        $container = $application->container();

        self::assertInstanceOf(SettingsRepository::class, $container->get(SettingsRepository::class));
        self::assertSame($container->get(SettingsRepository::class), $container->get(SettingsReadRepository::class));
        self::assertSame($container->get(SettingsRepository::class), $container->get(SettingsWriteRepository::class));
        self::assertSame($container->get(SettingsRepository::class), $container->get(SettingsRepositoryContract::class));
        self::assertInstanceOf(EditableContentTypes::class, $container->get(EditableContentTypes::class));
        self::assertInstanceOf(ContentRepository::class, $container->get(ContentRepository::class));
        self::assertSame(
            $container->get(ContentRepository::class),
            $container->get(ContentReadRepository::class),
        );
        self::assertSame(
            $container->get(ContentRepository::class),
            $container->get(ContentWriteRepository::class),
        );
        self::assertSame(
            $container->get(ContentRepository::class),
            $container->get(ContentRepositoryContract::class),
        );
        self::assertSame($container->get(ContentRepository::class), $container->get(ContentAdminReadRepository::class));
        self::assertSame($container->get(ContentRepository::class), $container->get(ContentRelationReadRepository::class));
        self::assertSame($container->get(ContentRepository::class), $container->get(ContentRelationWriteRepository::class));
        self::assertSame($container->get(TaxonomyRepository::class), $container->get(TaxonomyReadRepository::class));
        self::assertSame($container->get(TaxonomyRepository::class), $container->get(TaxonomyWriteRepository::class));
        self::assertSame($container->get(TaxonomyRepository::class), $container->get(TaxonomyRepositoryContract::class));
        self::assertInstanceOf(TaxonomyRepository::class, $container->get(TaxonomyRepository::class));
        self::assertInstanceOf(ContentAttachmentStorage::class, $container->get(ContentAttachmentStorage::class));

        $types = $container->get(ContentTypeRegistry::class);
        self::assertSame(['article', 'page', 'product'], array_keys($types->all()));
    }

    private function application(InstalledTestProject $project): Application
    {
        $application = new Application($project->path, 'testing');
        $application->register(new FoundationServiceProvider());
        $application->register(new DatabaseServiceProvider());
        $application->register(new ContentServiceProvider());
        $application->boot();

        return $application;
    }
}
