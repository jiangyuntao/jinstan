<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Installer;

use Jinstan\Core\Application\Installer\EnvironmentGateway;
use Jinstan\Core\Application\Installer\Services\InstallApplicationService;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Http\SafeErrorMessage;
use Jinstan\Core\View\Presenters\Installer\InstallPresenter;
use Jinstan\Core\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class InstallController
{
    public function __construct(
        private readonly InstallApplicationService $application,
        private readonly CsrfToken $csrf,
        private readonly EnvironmentGateway $environment,
        private readonly InstallPresenter $presenter,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->html($this->presenter->form());
    }

    public function install(ServerRequestInterface $request): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? array_map('strval', $input) : [];

        if (!$this->csrf->valid($input['_token'] ?? null)) {
            return $this->responses->text('CSRF token is invalid.', 419);
        }

        if (!$this->environment->passes()) {
            return $this->responses->html($this->presenter->form('服务器环境未满足安装要求。'), 422);
        }

        try {
            $this->application->execute($input);
        } catch (\Throwable $exception) {
            return $this->responses->html(
                $this->presenter->form(SafeErrorMessage::from($exception, '安装失败，请检查数据库配置和目录权限。')),
                422,
            );
        }

        // 安装完成前本次请求尚未注册已安装站点路由，下一次请求再由首页路由接管。
        return $this->responses->redirect('/');
    }
}
