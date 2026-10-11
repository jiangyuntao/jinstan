<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\Application\Content\TemplateCatalogReader;
use Jinstan\Core\View\Forms\AdminContentForm;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Content\ContentTypeDefinition;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class ContentFormPresenter
{
    public function __construct(
        private readonly AdminContentForm $fields,
        private readonly CsrfToken $csrf,
        private readonly ContentReadRepository $contents,
        private readonly SettingsReadRepository $settings,
        private readonly UrlGenerator $urls,
        private readonly TaxonomyReadRepository $taxonomy,
        private readonly TemplateCatalogReader $templates,
    ) {
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function present(ContentTypeDefinition $type, array $input, ?int $id, bool $submitted): array
    {
        $form = $this->fields->data($type, $this->csrf, $input, $id, $this->parentOptions($type, $input));
        $form['template'] = is_string($input['template'] ?? null) ? $input['template'] : '';
        $form['templates'] = $this->templates->options('detail');
        $form['taxonomyGroups'] = $this->taxonomyForm($type, $input, $id, $submitted);
        $form['locale'] = is_string($input['locale'] ?? null) ? $input['locale'] : $this->settings->get('site.default_locale', ContentLocales::FALLBACK);
        $form['locales'] = ContentLocales::options($this->settings);
        $form['parentSearchUrl'] .= '?locale=' . rawurlencode((string) $form['locale']);
        $sourceReference = null;
        if ($id !== null && $this->contents->translation($id, (string) $form['locale']) === null) {
            $source = $this->sourceTranslation($id, (string) $form['locale']);
            if ($source !== null) {
                $sourceReference = [
                    'locale' => $source->locale,
                    'localeName' => $this->localeName($source->locale),
                    'title' => $source->title,
                    'slug' => $source->slug,
                    'fields' => $this->sourceFields($type, $source),
                ];
            }
        }

        return ['form' => $form, 'sourceReference' => $sourceReference];
    }

    /** @param array<string, mixed> $input
     *  @return array<int, string>
     */
    private function parentOptions(ContentTypeDefinition $type, array $input): array
    {
        if (!$type->hierarchical) {
            return [];
        }
        $parentId = (int) ($input['parent_id'] ?? 0);
        $parent = $parentId > 0 ? $this->contents->find($parentId) : null;
        if ($parent === null || $parent->type !== $type->handle) {
            return [];
        }
        $locale = is_string($input['locale'] ?? null) ? $input['locale'] : $this->settings->get('site.default_locale', ContentLocales::FALLBACK);
        $translation = $this->contents->translation($parentId, $locale);

        return [$parentId => $translation?->title ?? sprintf('#%d', $parentId)];
    }

    /** @param array<string, mixed> $input
     *  @return list<array<string, mixed>>
     */
    private function taxonomyForm(ContentTypeDefinition $type, array $input, ?int $id, bool $submitted): array
    {
        $labels = array_column($this->taxonomy->taxonomies(), 'label', 'handle');
        $locale = is_string($input['locale'] ?? null) ? $input['locale'] : ContentLocales::FALLBACK;
        $selected = $id === null ? [] : $this->taxonomy->selected($id, $locale);
        if ($submitted) {
            $selected = [];
            foreach ($type->taxonomies as $group => $settings) {
                foreach ((array) ($input['terms'][$group] ?? []) as $value) {
                    if (is_scalar($value) && (int) $value > 0) {
                        $selected[] = ['id' => (int) $value, 'taxonomy_handle' => $group, 'name' => '#' . (int) $value];
                    }
                }
            }
        }
        $result = [];
        foreach ($type->taxonomies as $group => $settings) {
            $items = array_values(array_filter($selected, static fn (array $term): bool => $term['taxonomy_handle'] === $group));
            $result[] = ['handle' => $group, 'label' => $labels[$group] ?? $group, 'required' => $settings['required'], 'config' => json_encode([
                'multiple' => $settings['multiple'],
                'items' => $items,
                'url' => $this->urls->route('admin.content.terms.search', ['type' => $type->handle, 'group' => $group, 'locale' => $locale]),
            ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)];
        }

        return $result;
    }

    private function sourceTranslation(int $contentId, string $targetLocale): ?ContentTranslation
    {
        $translations = $this->contents->translations($contentId);
        $preferred = [
            $this->settings->get('site.default_locale', ContentLocales::FALLBACK) ?? ContentLocales::FALLBACK,
            ContentLocales::FALLBACK,
        ];
        foreach ($preferred as $locale) {
            if ($locale === $targetLocale) {
                continue;
            }
            foreach ($translations as $translation) {
                if ($translation->locale === $locale) {
                    return $translation;
                }
            }
        }
        foreach ($translations as $translation) {
            if ($translation->locale !== $targetLocale) {
                return $translation;
            }
        }

        return null;
    }

    /** @return list<array{label: string, value: string, length: int, collapsible: bool}> */
    private function sourceFields(ContentTypeDefinition $type, ContentTranslation $translation): array
    {
        $fields = [];
        foreach ($type->fields as $field) {
            if (!$field->translatable || !array_key_exists($field->name, $translation->data)) {
                continue;
            }
            $value = $translation->data[$field->name];
            if (is_array($value) && isset($value['value']) && is_scalar($value['value'])) {
                $value = $value['value'];
            }
            $text = is_scalar($value) || $value === null
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $fields[] = [
                'label' => $field->label,
                'value' => $text,
                'length' => mb_strlen($text),
                'collapsible' => mb_strlen($text) > 500,
            ];
        }

        return $fields;
    }

    private function localeName(string $locale): string
    {
        return ContentLocales::options($this->settings)[$locale]
            ?? ContentLocales::catalog()[$locale]['name']
            ?? $locale;
    }
}
