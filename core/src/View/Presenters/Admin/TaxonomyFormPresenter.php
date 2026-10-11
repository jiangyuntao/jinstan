<?php

declare(strict_types=1);

namespace Jinstan\Core\View\Presenters\Admin;

use Jinstan\Core\View\AdminPageView;
use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Taxonomy\Queries\TaxonomyTranslationResolver;
use Jinstan\Core\I18n\ContentLocales;
use Jinstan\Core\Settings\SettingsReadRepository;

final class TaxonomyFormPresenter
{
    public function __construct(
        private readonly AdminViewRenderer $views,
        private readonly SettingsReadRepository $settings,
        private readonly TaxonomyTranslationResolver $translations,
    ) {
    }

    /**
     * @param array<string, mixed>|null $group
     * @param array<string, mixed> $values
     */
    public function present(
        bool $isTerm,
        bool $editing,
        bool $deleting,
        ?int $id,
        string $locale,
        ?array $group,
        array $values,
        ?string $error,
        string $backUrl,
    ): AdminPageView {
        $title = $this->title($isTerm, $editing, $deleting);
        $sourceReference = $isTerm && $id !== null && ($values['name'] ?? '') === ''
            ? $this->translations->source($id, $locale)
            : null;
        if ($sourceReference !== null) {
            $sourceReference['localeName'] = $this->translations->localeName($sourceReference['locale']);
            $sourceReference['locale'] = $sourceReference['localeName'] . ' (' . $sourceReference['locale'] . ')';
        }

        return new AdminPageView(
            'taxonomy-form',
            $title,
            [
                'heading' => $title,
                'isTerm' => $isTerm,
                'editing' => $editing,
                'deleting' => $deleting,
                'group' => $group,
                'values' => $values,
                'error' => $error,
                'backUrl' => $backUrl,
                'locale' => $locale,
                'locales' => ContentLocales::options($this->settings),
                'sourceReference' => $sourceReference,
            ],
        );
    }

    private function title(bool $isTerm, bool $editing, bool $deleting): string
    {
        if ($isTerm) {
            return $this->views->translate($deleting ? '删除条目' : ($editing ? '编辑条目' : '新建条目'));
        }

        return $this->views->translate($deleting ? '删除词汇表' : ($editing ? '编辑词汇表' : '新建词汇表'));
    }
}
