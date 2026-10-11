<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\View\Presenters\Frontend\PublicFieldPresenter;
use Jinstan\Core\View\ThemeView;
use Jinstan\Core\Component\ComponentRegistry;
use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Field\ContentBodyField;
use Jinstan\Core\View\Presenters\Frontend\SeoRenderer;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\SEO\SchemaRegistry;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Settings\SnsPlatforms;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Latte\Loaders\StringLoader;
use Latte\Runtime\Html;

final class ThemeRenderer implements ThemeView
{
    private Engine $latte;

    private readonly ?ComponentRenderer $components;

    private readonly ?ComponentRegistry $registry;

    private readonly string $themePath;

    private readonly CoreAssetManifest $assets;

    public function __construct(string $themePath, private readonly ContentTypeRegistry $types, ?ComponentRegistry $registry = null, ?string $pluginsPath = null, private readonly ?SettingsReadRepository $settings = null)
    {
        $this->themePath = $themePath;
        $this->assets = CoreAssetManifest::load(dirname($themePath, 2) . '/core');
        $this->registry = $registry;
        $this->latte = new Engine();
        $this->latte->setLoader(new FileLoader());
        $this->latte->setCacheDirectory(dirname($themePath, 2) . '/storage/cache/latte');
        $this->components = $registry === null ? null : new ComponentRenderer($registry, $this->latte, $themePath, $pluginsPath);
        $this->registerCmsFunctions($this->latte, $registry);
    }

    private function registerCmsFunctions(Engine $engine, ?ComponentRegistry $registry): void
    {
        $engine->addFunction('hasComponent', static function (string $name) use ($registry): bool {
            if ($registry === null) {
                return false;
            }
            try {
                $registry->get($name);
                return true;
            } catch (\InvalidArgumentException) {
                return false;
            }
        });
        $engine->addFunction('coreAsset', fn (string $name): string => $this->assets->url($name));
        if ($this->components !== null) {
            $engine->addFunction('component', fn (string $name, array $parameters = [], ?Content $content = null, ?ContentTranslation $translation = null): Html => new Html($this->components->render($name, $parameters, ['content' => $content, 'translation' => $translation])));
        }
    }

    public function content(Content $content, ContentTranslation $translation, string $canonical = '/', array $hreflang = [], ?SchemaRegistry $schema = null, string $pageType = 'content'): string
    {
        $type = $this->types->get($content->type);
        $bodyData = '';
        foreach ($type->fields as $field) {
            if ($field->type instanceof ContentBodyField) {
                $bodyData = $field->translatable
                    ? ($translation->data[$field->name] ?? '')
                    : ($content->data[$field->name] ?? '');
                break;
            }
        }
        if (is_array($bodyData) && ($bodyData['format'] ?? '') === 'html_document') {
            $seo = new SeoRenderer($this->settings);
            $links = '';
            foreach ($hreflang as $locale => $url) {
                $links .= '<link rel="alternate" hreflang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
            }

            return $this->renderHtmlDocument((string) ($bodyData['value'] ?? ''), [
                'content' => $content,
                'translation' => $translation,
                'locale' => $translation->locale,
                'direction' => $this->direction($translation->locale),
                'seo_title' => $seo->title($translation, true, $pageType, $content->type),
                'seo_tags' => new Html($seo->tags($translation, $canonical, true, $pageType, $content->type) . $links . ($schema?->render($content, $translation) ?? '')),
                'site' => $this->siteSettings(),
            ]);
        }

        $templates = array_unique([$content->template, $type->defaultTemplate, 'content-detail']);

        foreach ($templates as $template) {
            $path = $this->templatePath($template);
            if ($path !== null && isset((new TemplateCatalog($this->themePath))->options('detail')[$template])) {
                $seo = new SeoRenderer($this->settings);
                $body = (new ContentBodyRenderer())->render($bodyData);
                $links = '';
                foreach ($hreflang as $locale => $url) {
                    $links .= '<link rel="alternate" hreflang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">';
                }
                return $this->latte->renderToString($path, [
                    'locale' => $translation->locale,
                    'direction' => $this->direction($translation->locale),
                    'content' => $content,
                    'translation' => $translation,
                    'body_html' => new Html($body),
                    'field_items' => (new PublicFieldPresenter(new ContentBodyRenderer()))->present($type, $content, $translation),
                    'seo_title' => $seo->title($translation, true, $pageType, $content->type),
                    'seo_tags' => new Html($seo->tags($translation, $canonical, true, $pageType, $content->type) . $links . ($schema?->render($content, $translation) ?? '')),
                    'site' => $this->siteSettings(),
                ]);
            }
        }

        throw new \RuntimeException('主题缺少可用内容模板。');
    }

    public function listing(\Jinstan\Core\Content\ContentTypeDefinition $type, array $pagination, string $locale, string $canonical, ?string $previousUrl, ?string $nextUrl): string
    {
        $catalog = new TemplateCatalog($this->themePath);
        $path = null;
        foreach (array_unique([$type->listTemplate, 'content-list']) as $handle) {
            if (isset($catalog->options('list')[$handle])) {
                $path = $catalog->path($handle);
                break;
            }
        }
        if ($path === null) {
            throw new \RuntimeException('当前主题缺少列表模板。');
        }
        $translation = new ContentTranslation($locale, $type->label, '', null, []);
        $seo = new SeoRenderer($this->settings);
        $title = $seo->title($translation, true, 'listing', $type->label, $pagination['page']);
        $fieldPresenter = new PublicFieldPresenter(new ContentBodyRenderer());
        $pagination['items'] = array_map(static function (array $item) use ($fieldPresenter, $type): array {
            $item['data'] = $fieldPresenter->presentListData($type, $item['data']);

            return $item;
        }, $pagination['items']);
        return $this->latte->renderToString($path, [
            'type' => $type, 'pagination' => $pagination, 'items' => $pagination['items'],
            'previousUrl' => $previousUrl, 'nextUrl' => $nextUrl, 'locale' => $locale,
            'direction' => $this->direction($locale),
            'seo_title' => $title, 'seo_tags' => new Html($seo->tags($translation, $canonical, true, 'listing', $type->label, $pagination['page'])),
            'site' => $this->siteSettings(),
        ]);
    }

    /** @param list<array{type: \Jinstan\Core\Content\ContentTypeDefinition, items: array, url: string}> $sections */
    public function homepage(array $sections, string $locale, string $canonical): string
    {
        $catalog = new TemplateCatalog($this->themePath);
        $path = $catalog->path($catalog->homepage());
        if ($path === null) {
            throw new \RuntimeException('当前主题的首页模板不可用。');
        }

        $site = $this->siteSettings();
        $seo = new SeoRenderer($this->settings);

        return $this->latte->renderToString($path, [
            'sections' => $sections,
            'locale' => $locale,
            'direction' => $this->direction($locale),
            'seo_title' => $seo->siteTitle(),
            'seo_tags' => new Html($seo->siteTags($canonical)),
            'site' => $site,
        ]);
    }

    private function templatePath(?string $template): ?string
    {
        return (new TemplateCatalog($this->themePath))->path($template);
    }

    private function direction(string $locale): string
    {
        return $this->settings === null ? 'ltr' : ContentLocales::direction($locale, $this->settings);
    }

    /** @param array<string, mixed> $data */
    private function renderHtmlDocument(string $source, array $data): string
    {
        $engine = new Engine();
        $engine->setLoader(new StringLoader());
        $engine->setCacheDirectory(dirname($this->themePath, 2) . '/storage/cache/latte-html');
        $this->registerCmsFunctions($engine, $this->registry);

        return $engine->renderToString($source, $data);
    }

    /** @return array{name: string, tagline: string, description: string, url: string, contactEmail: string, footer: string, sns: list<array{handle: string, label: string, url: string, icon: string}>, dateFormat: string, year: string, custom: array<string, string>} */
    private function siteSettings(): array
    {
        $sns = [];
        foreach (SnsPlatforms::GROUPS as $platforms) {
            foreach ($platforms as $handle => $label) {
                $url = $this->settings?->get('site.sns.' . $handle, '') ?? '';
                if ($url !== '' && SnsPlatforms::isValidUrl($url)) {
                    $sns[] = ['handle' => $handle, 'label' => $label, 'url' => $url, 'icon' => SnsPlatforms::icon($handle)];
                }
            }
        }

        return [
            'name' => $this->settings?->get('site.name', 'Jinstan') ?? 'Jinstan',
            'tagline' => $this->settings?->get('site.tagline', '') ?? '',
            'description' => $this->settings?->get('site.description', '') ?? '',
            'url' => $this->settings?->get('site.url', '') ?? '',
            'contactEmail' => $this->settings?->get('site.contact_email', '') ?? '',
            'footer' => $this->settings?->get(
                'site.footer',
                sprintf('© %s %s. 版权所有。', date('Y'), $this->settings?->get('site.name', 'Jinstan') ?? 'Jinstan'),
            ) ?? '',
            'sns' => $sns,
            'dateFormat' => $this->settings?->get('site.date_format', 'Y-m-d') ?? 'Y-m-d',
            'year' => date('Y'),
            'custom' => $this->settings?->custom() ?? [],
        ];
    }
}
