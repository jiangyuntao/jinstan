<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\Application\Content\ContentOperationDenied;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Content\Services\ContentAttachmentCoordinator;
use Jinstan\Core\Application\Content\Queries\ContentEditQuery;
use Jinstan\Core\View\Presenters\Admin\ContentFormPresenter;
use Jinstan\Core\View\Presenters\Admin\ContentListPresenter;
use Jinstan\Core\Application\Content\Queries\ContentListQuery;
use Jinstan\Core\Application\Content\Queries\ContentSearchQuery;
use Jinstan\Core\Application\Content\Services\CreateContent;
use Jinstan\Core\Application\Content\Services\DeleteContent;
use Jinstan\Core\Application\Content\Services\UpdateContent;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\Requests\Admin\ContentRequest;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContentAdminController
{
    private const ITEMS_PER_PAGE = 10;

    public function __construct(
        private readonly Authenticator $auth,
        private readonly ContentTypeRegistry $types,
        private readonly CsrfToken $csrf,
        private readonly ContentAttachmentCoordinator $attachmentCoordinator,
        private readonly ContentListQuery $listQuery,
        private readonly ContentListPresenter $listPresenter,
        private readonly ContentFormPresenter $formPresenter,
        private readonly ContentSearchQuery $searchQuery,
        private readonly ContentEditQuery $editQuery,
        private readonly CreateContent $createContent,
        private readonly UpdateContent $updateContent,
        private readonly DeleteContent $deleteContent,
        private readonly ContentRequest $contentRequest,
        private readonly AdminViewRenderer $views,
        private readonly SettingsReadRepository $settings,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $type = $this->types->get($params['type']);
        $locale = $this->contentLocale($request);
        $queryParameters = $request->getQueryParams();
        $query = is_string($queryParameters['q'] ?? null) ? trim($queryParameters['q']) : '';
        $status = is_string($queryParameters['status'] ?? null) ? $queryParameters['status'] : '';
        $page = filter_var($queryParameters['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $visibleColumns = $this->listPresenter->visibleColumns($queryParameters);
        $result = $this->listQuery->list(
            $type->handle,
            $locale,
            $query,
            $status,
            $page,
            $visibleColumns,
            $this->views->translate('content.untranslated'),
            self::ITEMS_PER_PAGE,
        );
        return $this->page('content-index', $this->typeLabel($type), $this->listPresenter->present(
            $type->handle,
            $this->typeLabel($type),
            $locale,
            $query,
            $status,
            $visibleColumns,
            $result,
            self::ITEMS_PER_PAGE,
        ));
    }

    public function create(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $type = $this->types->get($params['type']);
        return $this->formPage($this->views->translate('content.create_title', ['name' => $this->typeLabel($type)]), $type->handle, [
            'locale' => $this->contentLocale($request),
            'published' => ($this->settings->get('content.default_status', 'draft') ?? 'draft') === 'published',
        ]);
    }

    public function store(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $validation = $this->contentRequest->result($request);
        $input = $validation->normalized();
        try {
            $input = $validation->validated();
            $type = $this->types->get($params['type']);
            $this->createContent->execute(
                $request->getUploadedFiles(),
                $type,
                $input,
                $this->contentLocale($request),
                $this->auth->userId(),
                $this->auth->can('system.manage'),
            );
        } catch (ContentOperationDenied $exception) {
            return $this->responses->text($exception->getMessage(), 403);
        } catch (\Throwable $exception) {
            return $this->formPage($this->views->translate('创建失败'), $params['type'], $input, null, SafeErrorMessage::from($exception, '创建失败，请稍后重试。'), 422);
        }
        return $this->responses->redirect($this->route('admin.content.index', ['type' => $params['type'], 'locale' => $this->contentLocale($request)]));
    }

    public function edit(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $this->editQuery->formInput($params['type'], (int) $params['id'], $this->contentLocale($request));
        if ($input === null) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->formPage($this->views->translate('编辑内容'), $params['type'], $input, (int) $params['id']);
    }

    public function parentSearch(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $query = $request->getQueryParams()['q'] ?? '';
            $excludeId = (int) ($request->getQueryParams()['exclude'] ?? 0);
            $items = $this->searchQuery->parents(
                $params['type'],
                $this->contentLocale($request),
                is_string($query) ? $query : '',
                $excludeId > 0 ? $excludeId : null,
            );
        } catch (\Throwable) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->responses->json(['items' => $items]);
    }

    public function update(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $validation = $this->contentRequest->result($request);
        $input = $validation->normalized();
        try {
            $input = $validation->validated();
            $this->updateContent->execute(
                $request->getUploadedFiles(),
                $this->types->get($params['type']),
                (int) $params['id'],
                $input,
                $this->contentLocale($request),
                $this->auth->userId(),
                $this->auth->can('system.manage'),
            );
        } catch (ContentOperationDenied $exception) {
            return $this->responses->text($exception->getMessage(), 403);
        } catch (\Throwable $exception) {
            return $this->formPage($this->views->translate('更新失败'), $params['type'], $input, (int) $params['id'], SafeErrorMessage::from($exception, '更新失败，请稍后重试。'), 422);
        }
        return $this->responses->redirect($this->route('admin.content.index', ['type' => $params['type'], 'locale' => $this->contentLocale($request)]));
    }

    public function destroy(ServerRequestInterface $request, array $params): ResponseInterface
    {
        if (!$this->deleteContent->execute($params['type'], (int) $params['id'])) {
            return $this->responses->text('', 404);
        }

        return $this->responses->redirect($this->route('admin.content.index', ['type' => $params['type']]));
    }

    /** @param array<string, mixed> $input */
    private function formPage(string $title, string $typeHandle, array $input = [], ?int $id = null, ?string $error = null, int $status = 200): ResponseInterface
    {
        $type = $this->types->get($typeHandle);
        return $this->page('content-form', $title, ['pageHeading' => $title, 'error' => $error] + $this->formPresenter->present($type, $input, $id, $error !== null), $status);
    }

    public function termSearch(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $query = $request->getQueryParams()['q'] ?? '';
            $items = $this->searchQuery->terms(
                $params['type'],
                $params['group'],
                $this->contentLocale($request),
                is_string($query) ? $query : null,
            );
        } catch (\InvalidArgumentException) {
            return $this->responses->text('', 404);
        }

        return $this->responses->json(['items' => $items], headers: ['Cache-Control' => 'no-store']);
    }

    private function typeLabel(ContentTypeDefinition $type): string
    {
        // 仅翻译内置默认名称，保留用户重命名和自定义类型的原始名称。
        $key = match ([$type->handle, $type->label]) {
            ['article', '文章'] => 'admin.articles',
            ['page', '页面'] => 'admin.pages',
            ['product', '产品'] => 'admin.products',
            default => null,
        };

        return $key === null ? $type->label : $this->views->translate($key);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, string $title, array $data = [], int $status = 200): ResponseInterface
    {
        return $this->responses->html($this->views->render($view, $title, $data), $status);
    }

    /** @param array<string, mixed> $parameters */
    private function route(string $name, array $parameters): string
    {
        return $this->urls->route($name, $parameters);
    }

    private function contentLocale(ServerRequestInterface $request): string
    {
        $body = $request->getParsedBody();
        $input = is_array($body) ? $body : $request->getQueryParams();

        return ContentLocales::requested($input, $this->settings);
    }
}
