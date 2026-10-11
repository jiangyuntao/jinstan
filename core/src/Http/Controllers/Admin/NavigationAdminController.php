<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Navigation\Queries\NavigationItemFormQuery;
use Jinstan\Core\View\Presenters\Admin\NavigationListPresenter;
use Jinstan\Core\Application\Navigation\Queries\NavigationListQuery;
use Jinstan\Core\Application\Navigation\Services\NavigationWriter;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class NavigationAdminController
{
    public function __construct(
        private readonly CsrfToken $csrf,
        private readonly SettingsReadRepository $settings,
        private readonly NavigationListQuery $listQuery,
        private readonly NavigationWriter $writer,
        private readonly NavigationItemFormQuery $itemFormQuery,
        private readonly NavigationListPresenter $listPresenter,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->page(
            'navigation',
            $this->listPresenter->present($this->listQuery->execute($request->getQueryParams())),
        );
    }

    public function itemForm(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        try {
            $data = $this->itemFormQuery->execute($id, $request->getQueryParams());
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Not Found', 404);
        }

        return $this->page('navigation-item-form', $data);
    }

    public function createMenu(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->write($request, $this->writer->createMenu(...));
    }

    public function createItem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->write($request, $this->writer->createItem(...));
    }

    public function updateItem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->write(
            $request,
            function (array $input) use ($params): void {
                $this->writer->updateItem((int) $params['id'], $input);
            },
        );
    }

    public function deleteItem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->write(
            $request,
            function (array $input) use ($params): void {
                $this->writer->deleteItem((int) $params['id']);
            },
        );
    }

    public function reorderItems(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $menu = is_array($input) ? (string) ($input['menu'] ?? '') : '';

        return $this->write($request, $this->writer->reorderItems(...), $menu);
    }

    private function write(ServerRequestInterface $request, callable $operation, string $menu = ''): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        try {
            $operation($input);
        } catch (\InvalidArgumentException | \OutOfBoundsException $exception) {
            return $this->responses->text($exception->getMessage(), 422);
        } catch (\PDOException) {
            return $this->responses->text('保存失败，数据可能已被修改或标识重复，请检查后重试。', 422);
        } catch (\Throwable) {
            return $this->responses->text('保存失败，请稍后重试。', 500);
        }

        $menu = $menu !== '' ? $menu : (string) ($input['menu'] ?? '');
        $query = ['locale' => ContentLocales::requested($input, $this->settings)];
        if ($menu !== '') {
            $query['menu'] = $menu;
        }
        $location = $this->urls->route('admin.navigation.index', $query);

        return $this->responses->redirect($location);
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, array $data): ResponseInterface
    {
        return $this->responses->html($this->views->render($view, $this->views->translate('admin.navigation'), [
            'csrfToken' => $this->csrf->value(),
        ] + $data));
    }
}
