<?php

declare(strict_types=1);

namespace Jinstan\Tests\Support;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Application\Content\Queries\ContentEditQuery;
use Jinstan\Core\Application\Content\Queries\ContentListQuery;
use Jinstan\Core\Application\Content\Queries\ContentSearchQuery;
use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Services\ContentService;
use Jinstan\Core\Application\Content\Services\CreateContent;
use Jinstan\Core\Application\Content\Services\DeleteContent;
use Jinstan\Core\Application\Content\Services\UpdateContent;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Event\EventDispatcher;
use Jinstan\Core\Http\Controllers\Admin\ContentAdminController;
use Jinstan\Core\Http\Requests\Admin\ContentRequest;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Infrastructure\Storage\Content\ContentAttachmentStorage;
use Jinstan\Core\Infrastructure\View\AdminViewRenderer;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;
use Jinstan\Core\Application\Content\TemplateCatalogReader;
use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\View\Presenters\Admin\ContentFormPresenter;
use Jinstan\Core\View\Presenters\Admin\ContentListPresenter;

final class ContentAdminControllerFactory
{
    public static function create(
        Authenticator $auth,
        ContentTypeRegistry $types,
        ContentRepository $contents,
        ContentService $service,
        ContentAttachmentStorage $attachments,
        CsrfToken $csrf,
        AdminViewRenderer $views,
        AdminContentForm $form,
        SettingsReadRepository $settings,
        UrlGenerator $urls,
        TaxonomyReadRepository $taxonomy,
        TemplateCatalogReader $templates,
    ): ContentAdminController {
        $coordinator = new ContentAttachmentCoordinator($attachments, $contents, $types);

        return new ContentAdminController(
            $auth,
            $types,
            $csrf,
            $coordinator,
            new ContentListQuery($contents, $settings),
            new ContentListPresenter($views, $csrf, $settings, $urls),
            new ContentFormPresenter($form, $csrf, $contents, $settings, $urls, $taxonomy, $templates),
            new ContentSearchQuery($types, $contents, $taxonomy),
            new ContentEditQuery($contents),
            new CreateContent($service, $coordinator, $settings),
            new UpdateContent($service, $contents, $coordinator),
            new DeleteContent($contents, $types, $coordinator, new EventDispatcher()),
            new ContentRequest($settings),
            $views,
            $settings,
            $urls,
            new \Jinstan\Core\Http\ResponseFactory(),
        );
    }
}
