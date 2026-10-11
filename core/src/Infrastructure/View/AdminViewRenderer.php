<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\Infrastructure\Filesystem\I18n\PhpTranslationCatalogLoader;
use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\I18n\Translator;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Version;
use Jinstan\Core\View\ViewNamespaceRegistry;
use Jinstan\Core\View\AdminView;
use Latte\Engine;

final class AdminViewRenderer implements AdminView
{
    private readonly Engine $latte;

    private readonly Translator $translator;

    private readonly CoreAssetManifest $assets;

    /** @param null|callable(string): bool $permissionChecker */
    public function __construct(
        private readonly string $viewsPath,
        string $cachePath,
        private readonly UrlGenerator $urls,
        private readonly ?CsrfToken $csrf = null,
        ?Translator $translator = null,
        private readonly mixed $permissionChecker = null,
        private readonly ?ContentTypeRegistry $contentTypes = null,
        ?ViewNamespaceRegistry $viewNamespaces = null,
    ) {
        if (!is_dir($cachePath) && !mkdir($cachePath, 0750, true) && !is_dir($cachePath)) {
            throw new \RuntimeException(sprintf('无法创建后台模板缓存目录：%s', $cachePath));
        }
        $this->latte = new Engine();
        $this->latte->setCacheDirectory($cachePath);
        $viewNamespaces ??= new ViewNamespaceRegistry(new LocalViewPathResolver());
        $viewNamespaces->register('admin', $viewsPath);
        $this->latte->setLoader(new NamespacedViewLoader($viewNamespaces));
        $this->translator = $translator ?? new Translator(
            dirname($viewsPath, 3) . '/lang/admin',
            static fn (): string => 'zh-CN',
            loader: new PhpTranslationCatalogLoader(),
        );
        $this->assets = CoreAssetManifest::load(dirname($viewsPath, 3));
        $this->latte->addFunction('t', fn (string $key, array $parameters = []): string => $this->translator->translate($key, $parameters));
        $this->latte->addFunction('coreAsset', fn (string $name): string => $this->assets->url($name));
        $this->latte->addFunction('route', fn (string $name, array $parameters = []): string => $this->urls->route($name, $parameters));
    }

    /** @param array<string, string|int|float> $parameters */
    public function translate(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, string $title, array $data = []): string
    {
        $can = fn (string $permission): bool => $this->permissionChecker === null
            || ($this->permissionChecker)($permission);

        $view = str_contains($view, '::') ? $view : 'admin::' . $view . '.jst';

        return $this->latte->renderToString($view, [
            'pageTitle' => $title,
            'csrfToken' => ($this->csrf ?? new CsrfToken())->value(),
            'coreVersion' => Version::CURRENT,
            'adminLocale' => $this->translator->locale(),
            'canContent' => $can('content.manage'),
            'canSettings' => $can('settings.manage'),
            'canTools' => $can('system.manage'),
            'canAccounts' => $can('users.manage'),
            'adminContentTypes' => $this->adminContentTypes(),
        ] + $data);
    }

    /** @return list<array{handle: string, label: string, icon: string}> */
    private function adminContentTypes(): array
    {
        if ($this->contentTypes === null) {
            return [
                ['handle' => 'article', 'label' => $this->translate('admin.articles'), 'icon' => 'ti-news'],
                ['handle' => 'page', 'label' => $this->translate('admin.pages'), 'icon' => 'ti-file-text'],
                ['handle' => 'product', 'label' => $this->translate('admin.products'), 'icon' => 'ti-shopping-bag'],
            ];
        }

        return array_values(array_map(
            fn (ContentTypeDefinition $type): array => [
                'handle' => $type->handle,
                'label' => match ($type->handle) {
                    'article' => $this->translate('admin.articles'),
                    'page' => $this->translate('admin.pages'),
                    'product' => $this->translate('admin.products'),
                    default => $type->label,
                },
                'icon' => match ($type->handle) {
                    'article' => 'ti-news',
                    'page' => 'ti-file-text',
                    'product' => 'ti-shopping-bag',
                    default => 'ti-file-description',
                },
            ],
            $this->contentTypes->all(),
        ));
    }

}
