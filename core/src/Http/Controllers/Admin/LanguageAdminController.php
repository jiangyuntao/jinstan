<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsRepositoryContract;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LanguageAdminController
{
    public function __construct(
        private readonly SettingsRepositoryContract $settings,
        private readonly CsrfToken $csrf,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function index(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->responses->html($this->views->render(
            'languages',
            $this->views->translate('语言管理'),
            [
                'profiles' => ContentLocales::profiles($this->settings),
                'defaultLocale' => $this->settings->get('site.default_locale', ContentLocales::FALLBACK),
                'csrfToken' => $this->csrf->value(),
            ],
        ));
    }

    public function form(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $profiles = ContentLocales::profiles($this->settings);
        $code = is_string($params['code'] ?? null) ? $params['code'] : '';
        $editing = $code !== '';
        if ($editing && !isset($profiles[$code])) {
            return $this->responses->text('Not Found', 404);
        }
        $catalog = ContentLocales::catalog();
        $available = array_diff_key($catalog, $profiles);
        $values = $editing ? $profiles[$code] : ['name' => '', 'direction' => 'ltr'];
        $availableOptions = [];
        foreach ($available as $candidateCode => $candidate) {
            $availableOptions[$candidateCode] = $candidate['name'] . ' (' . $candidateCode . ')';
        }

        return $this->responses->html($this->views->render(
            'language-form',
            $this->views->translate($editing ? '编辑语言' : '新增语言'),
            [
                'editing' => $editing,
                'code' => $code,
                'values' => $values,
                'available' => $available,
                'availableOptions' => $availableOptions,
                'catalogJson' => json_encode($catalog, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),
                'csrfToken' => $this->csrf->value(),
            ],
        ));
    }

    public function save(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $input = $request->getParsedBody();
        $input = is_array($input) ? $input : [];

        $catalog = ContentLocales::catalog();
        $profiles = ContentLocales::profiles($this->settings);
        $routeCode = is_string($params['code'] ?? null) ? $params['code'] : '';
        $code = $routeCode !== '' ? $routeCode : (is_string($input['code'] ?? null) ? $input['code'] : '');
        if (!isset($catalog[$code]) || ($routeCode === '' && isset($profiles[$code])) || ($routeCode !== '' && !isset($profiles[$code]))) {
            return $this->responses->text('语言代码无效或已经存在。', 422);
        }
        $name = trim((string) ($input['name'] ?? $catalog[$code]['name']));
        $direction = (string) ($input['direction'] ?? $catalog[$code]['direction']);
        if ($name === '' || mb_strlen($name) > 80 || !in_array($direction, ['ltr', 'rtl'], true)) {
            return $this->responses->text('语言名称或文字方向无效。', 422);
        }
        $profiles[$code] = ['name' => $name, 'direction' => $direction];
        $this->persist($profiles);

        return $this->responses->redirect($this->indexUrl());
    }

    public function delete(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $code = is_string($params['code'] ?? null) ? $params['code'] : '';
        $default = $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK;
        if ($code === $default || $code === ContentLocales::FALLBACK) {
            return $this->responses->text('默认语言和简体中文回退语言不能删除。', 422);
        }
        $profiles = ContentLocales::profiles($this->settings);
        unset($profiles[$code]);
        $this->persist($profiles);

        return $this->responses->redirect($this->indexUrl());
    }

    /** @param array<string, array{name: string, direction: string}> $profiles */
    private function persist(array $profiles): void
    {
        $this->settings->set('site.locales', json_encode($profiles, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $this->settings->set('site.enabled_locales', implode(',', array_keys($profiles)));
    }

    private function indexUrl(): string
    {
        return $this->urls->route('admin.languages.index');
    }
}
