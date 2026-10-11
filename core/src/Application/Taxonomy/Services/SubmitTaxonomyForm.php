<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Services;

use Jinstan\Core\Content\ContentTypeRegistry;
use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;

final class SubmitTaxonomyForm
{
    public function __construct(
        private readonly TaxonomyRepositoryContract $taxonomies,
        private readonly ContentTypeRegistry $types,
    ) {
    }

    /** @param array<string, mixed> $values */
    public function execute(
        bool $isTerm,
        bool $editing,
        bool $deleting,
        string $handle,
        ?int $id,
        string $locale,
        array $values,
        bool $confirmed,
    ): void {
        if ($deleting) {
            $this->delete($isTerm, $handle, $id, $confirmed);

            return;
        }

        if ($isTerm) {
            $this->saveTerm($editing, $handle, $id, $locale, $values);

            return;
        }

        $label = $this->text($values, 'label');
        if ($editing) {
            $this->taxonomies->updateTaxonomy($handle, $label);

            return;
        }

        $this->taxonomies->createTaxonomy($this->text($values, 'handle'), $label);
    }

    private function delete(bool $isTerm, string $handle, ?int $id, bool $confirmed): void
    {
        if (!$confirmed) {
            throw new \InvalidArgumentException('请确认删除影响后再提交。');
        }
        if ($isTerm) {
            if ($id === null) {
                throw new \InvalidArgumentException('条目不存在。');
            }
            $this->taxonomies->deleteTerm($handle, $id);

            return;
        }

        $this->taxonomies->deleteTaxonomy($handle, $this->types);
    }

    /** @param array<string, mixed> $values */
    private function saveTerm(bool $editing, string $handle, ?int $id, string $locale, array $values): void
    {
        $name = $this->text($values, 'name');
        $slug = $this->text($values, 'slug');
        if ($editing) {
            if ($id === null) {
                throw new \InvalidArgumentException('条目不存在。');
            }
            $this->taxonomies->updateTerm($handle, $id, $locale, $name, $slug);

            return;
        }

        $this->taxonomies->createTerm($handle, $locale, $name, $slug);
    }

    /** @param array<string, mixed> $values */
    private function text(array $values, string $key): string
    {
        return is_string($values[$key] ?? null) ? trim($values[$key]) : '';
    }
}
