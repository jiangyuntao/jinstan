<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\View\Presenters\Admin\PluginAdminPresenter;
use Jinstan\Core\Application\Plugin\Queries\PluginAdminQuery;
use Jinstan\Core\Application\Plugin\Services\PluginWriter;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PluginAdminController
{
    public function __construct(
        private readonly PluginAdminQuery $query,
        private readonly PluginAdminPresenter $presenter,
        private readonly PluginWriter $writer,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->present($this->presenter->index($this->query->plugins()));
    }

    public function toggle(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];

        try {
            $this->writer->toggle($params['name'], $input);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '插件操作失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->pluginUrl());
    }

    public function settings(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $plugin = $this->query->settings($params['name']);
        } catch (\OutOfBoundsException) {
            return $this->responses->text('Plugin settings not found.', 404);
        }

        return $this->present($this->presenter->settings($plugin));
    }

    public function saveSettings(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];
        try {
            $this->writer->saveSettings($params['name'], $input);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '保存插件设置失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->pluginUrl($params['name'], true));
    }

    public function uninstall(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $this->writer->uninstall($params['name']);
        } catch (\Throwable $exception) {
            return $this->responses->text(SafeErrorMessage::from($exception, '卸载插件失败，请稍后重试。'), 422);
        }

        return $this->responses->redirect($this->pluginUrl());
    }

    private function pluginUrl(string $name = '', bool $saved = false): string
    {
        if ($name === '') {
            return $this->urls->route('admin.plugins.index');
        }
        $parameters = ['name' => $name];
        if ($saved) {
            $parameters['_feedback'] = 'saved';
        }

        return $this->urls->route('admin.plugins.settings', $parameters);
    }

    private function present(AdminPageView $view): ResponseInterface
    {
        return $this->responses->html($view->render($this->views));
    }
}
