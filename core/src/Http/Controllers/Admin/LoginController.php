<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Http\Requests\Auth\LoginRequest;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Security\RateLimiter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController
{
    public function __construct(
        private readonly Authenticator $auth,
        private readonly CsrfToken $csrf,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly LoginRequest $loginRequest,
        private readonly ResponseFactory $responses,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    public function show(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->html($this->views->render('login', '登录', [
            'csrfToken' => $this->csrf->value(),
        ]));
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $input = $request->getParsedBody();
        $token = is_array($input) && is_string($input['_token'] ?? null) ? $input['_token'] : null;
        if (!$this->csrf->valid($token)) {
            return $this->responses->text('CSRF token is invalid.', 419);
        }
        $credentials = $this->loginRequest->validated($request);
        $client = $this->clientAddress($request);
        $email = strtolower($credentials['email']);
        $ipKey = 'login:ip:' . $client;
        $pairKey = 'login:pair:' . $client . ':' . $email;
        $ipLimit = $this->rateLimiter->consume($ipKey, 30, 900);
        $pairLimit = $this->rateLimiter->consume($pairKey, 5, 900);
        if (!$ipLimit->allowed || !$pairLimit->allowed) {
            return $this->responses->text('登录尝试过多，请稍后重试。', 429, [
                'Retry-After' => (string) max($ipLimit->retryAfter, $pairLimit->retryAfter),
            ]);
        }
        if (!$this->auth->attempt($credentials['email'], $credentials['password'])) {
            return $this->responses->text('邮箱或密码错误。', 422);
        }

        $this->rateLimiter->clear($pairKey);
        $this->csrf->regenerate();

        return $this->responses->redirect($this->urls->route('admin.dashboard'));
    }

    private function clientAddress(ServerRequestInterface $request): string
    {
        $address = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false
            ? $address
            : 'unknown';
    }
}
