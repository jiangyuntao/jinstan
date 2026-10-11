<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Queries;

use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyReadRepository;

final class TaxonomyTermListQuery
{
    public function __construct(
        private readonly TaxonomyReadRepository $taxonomies,
        private readonly SettingsReadRepository $settings,
        private readonly TaxonomyTranslationResolver $translations,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function execute(string $handle, array $parameters): array
    {
        $group = $this->taxonomies->taxonomy($handle);
        $search = mb_substr($this->text($parameters, 'q'), 0, 100);
        $locale = ContentLocales::requested($parameters, $this->settings);
        $result = $this->taxonomies->termPage($handle, $search, $this->page($parameters), $locale);

        foreach ($result['items'] as &$item) {
            $item['translated'] = $item['locale'] !== null;
            if ($item['translated']) {
                continue;
            }

            $source = $this->translations->source((int) $item['id'], $locale);
            $item['name'] = $source['name'] ?? null;
            $item['slug'] = $source['slug'] ?? '';
            $item['sourceLocale'] = isset($source['locale'])
                ? $this->translations->localeName($source['locale']) . ' (' . $source['locale'] . ')'
                : null;
        }
        unset($item);

        return [
            'group' => $group,
            'search' => $search,
            'locale' => $locale,
            'result' => $result,
        ];
    }

    /** @param array<string, mixed> $input */
    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    }

    /** @param array<string, mixed> $input */
    private function page(array $input): int
    {
        return max(1, (int) (filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
    }
}
