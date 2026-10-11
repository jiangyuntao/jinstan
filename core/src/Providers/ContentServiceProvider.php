<?php

declare(strict_types=1);

namespace Jinstan\Core\Providers;

use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Content\ContentAdminReadRepository;
use Jinstan\Core\Content\ContentAttachmentStore;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentWriteRepository;
use Jinstan\Core\Content\ContentRelationReadRepository;
use Jinstan\Core\Content\ContentRelationWriteRepository;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\DefaultContentTypes;
use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Foundation\Application;
use Jinstan\Core\Field\DefaultFieldTypes;
use Jinstan\Core\Field\FieldTypeRegistrar;
use Jinstan\Core\Field\FieldTypeRegistry;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Settings\SettingsRepositoryContract;
use Jinstan\Core\Settings\SettingsWriteRepository;
use Jinstan\Core\Infrastructure\Persistence\Taxonomy\TaxonomyRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;
use Jinstan\Core\Taxonomy\TaxonomyWriteRepository;

final class ContentServiceProvider implements ServiceProvider
{
    public function register(Application $application): void
    {
        $container = $application->container();
        if (!$container->has(Connection::class)) {
            return;
        }

        $connection = $container->get(Connection::class);
        $settings = new SettingsRepository($connection);
        $types = new ContentTypeRegistry();
        DefaultContentTypes::register($types);
        $fieldTypes = new FieldTypeRegistry();
        DefaultFieldTypes::register($fieldTypes);

        $editableTypes = new EditableContentTypes($settings, $fieldTypes);
        $editableTypes->load($types);

        $container->set(SettingsRepository::class, $settings);
        $container->set(SettingsReadRepository::class, $settings);
        $container->set(SettingsWriteRepository::class, $settings);
        $container->set(SettingsRepositoryContract::class, $settings);
        $container->set(ContentTypeRegistry::class, $types);
        $container->set(FieldTypeRegistry::class, $fieldTypes);
        $container->set(FieldTypeRegistrar::class, $fieldTypes);
        $container->set(EditableContentTypes::class, $editableTypes);
        $contents = new ContentRepository($connection);
        $container->set(ContentRepository::class, $contents);
        $container->set(ContentReadRepository::class, $contents);
        $container->set(ContentWriteRepository::class, $contents);
        $container->set(ContentRepositoryContract::class, $contents);
        $container->set(ContentAdminReadRepository::class, $contents);
        $container->set(ContentRelationReadRepository::class, $contents);
        $container->set(ContentRelationWriteRepository::class, $contents);
        $taxonomy = new TaxonomyRepository($connection, $settings);
        $container->set(TaxonomyRepository::class, $taxonomy);
        $container->set(TaxonomyReadRepository::class, $taxonomy);
        $container->set(TaxonomyWriteRepository::class, $taxonomy);
        $container->set(TaxonomyRepositoryContract::class, $taxonomy);
        $container->set(
            ContentAttachmentStorage::class,
            new ContentAttachmentStorage($application->projectPath('storage/content-attachments')),
        );
        $container->set(ContentAttachmentStore::class, $container->get(ContentAttachmentStorage::class));
        $container->set(
            \Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage::class,
            $container->get(ContentAttachmentStorage::class),
        );
    }

    public function boot(Application $application): void
    {
    }
}
