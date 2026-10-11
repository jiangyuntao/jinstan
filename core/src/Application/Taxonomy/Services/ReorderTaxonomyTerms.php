<?php

declare(strict_types=1);

namespace Jinstan\Core\Application\Taxonomy\Services;

use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;

final class ReorderTaxonomyTerms
{
    public function __construct(private readonly TaxonomyRepositoryContract $taxonomies)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{taxonomy: string, page: int}
     */
    public function execute(array $input): array
    {
        $handle = is_string($input['taxonomy'] ?? null) ? trim($input['taxonomy']) : '';
        $order = $input['order'] ?? [];
        if ($handle === '' || !is_array($order) || count($order) > 20) {
            throw new \InvalidArgumentException('分类法排序数据无效。');
        }

        $ids = [];
        foreach ($order as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \InvalidArgumentException('分类法排序项目无效。');
            }
            $ids[] = (int) $id;
        }

        $page = max(1, (int) (filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));
        $this->taxonomies->taxonomy($handle);
        $this->taxonomies->reorder($handle, $ids, $page);

        return ['taxonomy' => $handle, 'page' => $page];
    }
}
