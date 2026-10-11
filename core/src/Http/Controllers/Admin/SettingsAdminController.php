<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Settings\Services\SaveSystemSettings;
use Jinstan\Core\View\Presenters\Admin\SystemSettingsPresenter;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Http\Requests\Admin\SystemSettingsRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SettingsAdminController
{
    public function __construct(
        private readonly CsrfToken $csrf,
        private readonly SystemSettingsPresenter $presenter,
        private readonly SaveSystemSettings $saveSystemSettings,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly SystemSettingsRequest $systemSettingsRequest,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function system(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->page(
            'system-settings',
            $this->views->translate('settings.title'),
            $this->presenter->present($request->getQueryParams()['tab'] ?? 'site'),
        );
    }

    public function saveSystem(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $result = $this->systemSettingsRequest->result($request);
        if (!$result->isValid()) {
            return $this->responses->text($result->firstError() ?? '表单验证失败。', 422);
        }
        $validated = $result->validated();

        $this->saveSystemSettings->execute($validated, $request->getUri());

        return $this->responses->redirect(
            $this->urls->route('admin.settings.system', ['tab' => $validated['active_tab']]),
        );
    }

    /** @param array<string, mixed> $data */
    private function page(string $view, string $title, array $data): ResponseInterface
    {
        return $this->responses->html($this->views->render($view, $title, [
            'csrfToken' => $this->csrf->value(),
        ] + $data));
    }
}
