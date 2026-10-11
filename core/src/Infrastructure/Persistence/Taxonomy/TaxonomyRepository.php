<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Taxonomy;

use Jinstan\Core\Infrastructure\Database\Connection;
use Jinstan\Core\Settings\SettingsReadRepository;
use Jinstan\Core\Taxonomy\TaxonomyRepositoryContract;

final class TaxonomyRepository implements TaxonomyRepositoryContract
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?SettingsReadRepository $settings = null,
    ) {
    }
    public function taxonomy(string $handle): array
    {
        return $this->connection->fetchAssociative('SELECT handle, label, (SELECT COUNT(*) FROM terms WHERE taxonomy_handle = taxonomies.handle) AS term_count FROM taxonomies WHERE handle = ?', [$handle]) ?: throw new \OutOfBoundsException('词汇表不存在。');
    }

    public function term(string $handle, int $id, string $locale = 'en'): array
    {
        return $this->connection->fetchAssociative("SELECT terms.*, COALESCE(term_translations.name, '') AS name, COALESCE(term_translations.slug, '') AS slug, (SELECT COUNT(*) FROM content_terms WHERE term_id = terms.id) AS content_count FROM terms LEFT JOIN term_translations ON term_translations.term_id = terms.id AND term_translations.locale = ? WHERE terms.taxonomy_handle = ? AND terms.id = ?", [$locale, $handle, $id]) ?: throw new \OutOfBoundsException('条目不存在。');
    }

    public function termTranslations(int $id): array
    {
        return $this->connection->fetchAllAssociative('SELECT locale, name, slug FROM term_translations WHERE term_id = ? ORDER BY locale', [$id]);
    }

    public function updateTaxonomy(string $handle, string $label): void
    {
        $this->taxonomy($handle);
        $this->validateText($label, 100, '词汇表名称');
        $this->connection->update('taxonomies', ['label' => $label], ['handle' => $handle]);
    }

    public function deleteTaxonomy(string $handle, \Jinstan\Core\Content\ContentTypeRegistry $types): void
    {
        $this->transaction(function () use ($handle, $types): void {
            $group = $this->taxonomy($handle);
            if ((int) $group['term_count'] > 0) {
                throw new \InvalidArgumentException('此词汇表仍有条目，请先清空条目。');
            }
            foreach ($types->all() as $type) {
                if (array_key_exists($handle, $type->taxonomies)) {
                    throw new \InvalidArgumentException('此词汇表仍被内容类型“' . $type->label . '”绑定，请先解除绑定。');
                }
            }
            // 停用或未成功加载的定义也可能保留绑定，删除前检查持久化配置。
            $saved = $this->savedContentTypeDefinitions();
            foreach ($saved as $definition) {
                if (array_key_exists($handle, $definition['taxonomies'] ?? [])) {
                    throw new \InvalidArgumentException('此词汇表仍有内容类型绑定，请先解除绑定。');
                }
            }
            $this->connection->delete('taxonomies', ['handle' => $handle]);
        });
    }

    /** @return list<array<string, mixed>> */
    private function savedContentTypeDefinitions(): array
    {
        $json = $this->settings?->get('content.types', '[]');
        if ($json === null) {
            $stored = $this->connection->fetchOne(
                'SELECT setting_value FROM settings WHERE setting_key = ?',
                ['content.types'],
            );
            $json = is_string($stored) ? $stored : '[]';
        }

        $definitions = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($definitions) ? array_values($definitions) : [];
    }

    public function updateTerm(string $handle, int $id, string $locale, string $name, string $slug): void
    {
        $this->term($handle, $id, $locale);
        $this->validateTerm($locale, $name, $slug, $id);
        if ($this->connection->fetchOne('SELECT term_id FROM term_translations WHERE term_id = ? AND locale = ?', [$id, $locale]) === false) {
            $this->connection->insert('term_translations', ['term_id' => $id, 'locale' => $locale, 'name' => $name, 'slug' => $slug]);
            return;
        }
        $this->connection->update('term_translations', ['name' => $name, 'slug' => $slug], ['term_id' => $id, 'locale' => $locale]);
    }

    public function deleteTerm(string $handle, int $id): void
    {
        $this->transaction(function () use ($handle, $id): void {
            $this->term($handle, $id);
            if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM terms WHERE parent_id = ?', [$id]) > 0) {
                throw new \InvalidArgumentException('此条目仍有子条目，请先调整或删除子条目。');
            }
            // 外键级联仅移除翻译、排序和关联记录，内容本身保留。
            $this->connection->delete('terms', ['id' => $id]);
        });
    }

    private function validateText(string $value, int $limit, string $label): void
    {
        if (trim($value) === '' || mb_strlen($value) > $limit) {
            throw new \InvalidArgumentException($label . '不能为空且不能超过 ' . $limit . ' 个字符。');
        }
    }

    private function validateTerm(string $locale, string $name, string $slug, int $id = 0): void
    {
        $this->validateText($name, 190, '名称');
        $this->validateText($slug, 190, 'URL 别名');
        if (preg_match('/[\\s\\/?#]/u', $slug)) {
            throw new \InvalidArgumentException('URL 别名不能包含空白、斜杠、问号或井号。');
        }
        if ($this->connection->fetchOne('SELECT term_id FROM term_translations WHERE locale = ? AND slug = ? AND term_id <> ?', [$locale, $slug, $id]) !== false) {
            throw new \InvalidArgumentException('此语言下的 URL 别名已被使用。');
        }
    }

    public function createTaxonomy(string $handle, string $label): void
    {
        $this->validateText($label, 100, '词汇表名称');
        if (!preg_match('/^[a-z][a-z0-9_]{0,99}$/', $handle)) {
            throw new \InvalidArgumentException('固定标识必须以小写字母开头，只能包含小写字母、数字和下划线，最多 100 个字符。');
        }
        if ($this->connection->fetchOne('SELECT handle FROM taxonomies WHERE handle = ?', [$handle]) !== false) {
            throw new \InvalidArgumentException('固定标识已被使用。');
        }
        $this->connection->insert('taxonomies', ['handle' => $handle, 'label' => $label]);
    }
    public function createTerm(string $taxonomy, string $locale, string $name, string $slug, ?int $parentId = null): int
    {
        $this->taxonomy($taxonomy);
        $this->validateTerm($locale, $name, $slug);
        if ($parentId !== null && $this->connection->fetchOne('SELECT taxonomy_handle FROM terms WHERE id = ?', [$parentId]) !== $taxonomy) {
            throw new \InvalidArgumentException('父条目必须属于当前词汇表。');
        }
        return $this->transaction(function () use ($taxonomy, $locale, $name, $slug, $parentId): int {
            $this->connection->insert('terms', ['taxonomy_handle' => $taxonomy, 'parent_id' => $parentId]);
            $id = (int) $this->connection->lastInsertId();
            $this->connection->insert('term_translations', ['term_id' => $id, 'locale' => $locale, 'name' => $name, 'slug' => $slug]);
            $position = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(COALESCE(taxonomy_term_positions.position, terms.id * 10)), 0) FROM terms LEFT JOIN taxonomy_term_positions ON taxonomy_term_positions.term_id = terms.id WHERE terms.taxonomy_handle = ?', [$taxonomy]);
            $this->connection->insert('taxonomy_term_positions', ['term_id' => $id, 'position' => $position + 10]);

            return $id;
        });
    }

    /** @param list<int> $termIds */
    public function reorder(string $taxonomy, array $termIds, int $page): void
    {
        if (count($termIds) > 20 || count($termIds) !== count(array_unique($termIds))) {
            throw new \InvalidArgumentException('分类法排序数据无效。');
        }
        foreach ($termIds as $termId) {
            if ($this->connection->fetchOne('SELECT taxonomy_handle FROM terms WHERE id = ?', [$termId]) !== $taxonomy) {
                throw new \InvalidArgumentException('分类法排序项目无效。');
            }
        }

        $this->transaction(function () use ($termIds, $page): void {
            foreach ($termIds as $index => $termId) {
                $position = (($page - 1) * 20 + $index + 1) * 10;
                if ($this->connection->fetchOne('SELECT term_id FROM taxonomy_term_positions WHERE term_id = ?', [$termId]) === false) {
                    $this->connection->insert('taxonomy_term_positions', ['term_id' => $termId, 'position' => $position]);
                } else {
                    $this->connection->update('taxonomy_term_positions', ['position' => $position], ['term_id' => $termId]);
                }
            }
        });
    }
    public function assign(int $contentId, int $termId): void
    {
        $this->connection->insert('content_terms', ['content_id' => $contentId, 'term_id' => $termId]);
    }

    /** @return list<array> */
    public function selected(int $contentId, string $locale = 'en'): array
    {
        return $this->connection->fetchAllAssociative("SELECT terms.id, terms.taxonomy_handle, COALESCE(term_translations.name, '') AS name FROM content_terms JOIN terms ON terms.id = content_terms.term_id LEFT JOIN term_translations ON term_translations.term_id = terms.id AND term_translations.locale = ? WHERE content_terms.content_id = ?", [$locale, $contentId]);
    }

    /** @return list<array> */
    public function search(string $group, string $query, string $locale = 'en'): array
    {
        $where = 'terms.taxonomy_handle = ? AND term_translations.locale = ?';
        $parameters = [$group, $locale];
        if (trim($query) !== '') {
            $where .= " AND (term_translations.name LIKE ? ESCAPE '!' OR term_translations.slug LIKE ? ESCAPE '!')";
            $pattern = '%' . strtr(trim($query), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $parameters[] = $pattern;
            $parameters[] = $pattern;
        }

        return $this->connection->fetchAllAssociative('SELECT terms.id, term_translations.name FROM terms JOIN term_translations ON term_translations.term_id = terms.id LEFT JOIN taxonomy_term_positions ON taxonomy_term_positions.term_id = terms.id WHERE ' . $where . ' ORDER BY COALESCE(taxonomy_term_positions.position, terms.id * 10), term_translations.name, terms.id LIMIT 20', $parameters);
    }

    /** @return array<string, list<int>> */
    public function validateSelections(array $bindings, array $input): array
    {
        $labels = array_column($this->taxonomies(), 'label', 'handle');
        foreach ($input as $group => $values) {
            if (!isset($bindings[$group])) {
                throw new \InvalidArgumentException('不能选择未启用的分类分组。');
            }
        }
        $result = [];
        foreach ($bindings as $group => $settings) {
            $values = $input[$group] ?? [];
            if (!isset($labels[$group]) || !is_array($values) || count($values) > 100) {
                throw new \InvalidArgumentException('分类选择无效或超过 100 项。');
            }
            $ids = [];
            foreach ($values as $value) {
                if ($value === '') {
                    continue;
                }
                if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw new \InvalidArgumentException('分类条目无效。');
                }
                $id = (int) $value;
                if ($this->connection->fetchOne('SELECT taxonomy_handle FROM terms WHERE id = ?', [$id]) !== $group) {
                    throw new \InvalidArgumentException('分类条目不属于所选分组。');
                }
                $ids[$id] = $id;
            }
            if ($settings['required'] && $ids === []) {
                throw new \InvalidArgumentException('请选择“' . $labels[$group] . '”。');
            }
            if (!$settings['multiple'] && count($ids) > 1) {
                throw new \InvalidArgumentException('“' . $labels[$group] . '”只能选择一项。');
            }
            $result[$group] = array_values($ids);
        }

        return $result;
    }

    public function sync(int $contentId, array $selections): void
    {
        // 仅替换当前启用分组，解绑分组时保留历史关系。
        foreach ($selections as $group => $ids) {
            $this->connection->executeStatement('DELETE FROM content_terms WHERE content_id = ? AND term_id IN (SELECT id FROM terms WHERE taxonomy_handle = ?)', [$contentId, $group]);
            foreach ($ids as $id) {
                $this->assign($contentId, $id);
            }
        }
    }

    public function transaction(callable $operation): mixed
    {
        $this->connection->beginTransaction();
        try {
            $result = $operation();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }
    /** @return list<array{handle:string,label:string}> */
    public function taxonomies(): array
    {
        return $this->connection->fetchAllAssociative('SELECT handle, label, (SELECT COUNT(*) FROM terms WHERE taxonomy_handle = taxonomies.handle) AS term_count FROM taxonomies ORDER BY label');
    }
    /** @return list<array{id:int,taxonomy_handle:string,parent_id:int|null,name:string,slug:string}> */
    public function terms(string $locale = 'en'): array
    {
        return $this->connection->fetchAllAssociative('SELECT terms.id, terms.taxonomy_handle, terms.parent_id, term_translations.name, term_translations.slug FROM terms JOIN term_translations ON term_translations.term_id = terms.id WHERE term_translations.locale = ? ORDER BY term_translations.name', [$locale]);
    }

    /** @return array{items: array, total: int, page: int, pages: int} */
    public function termPage(string $taxonomy, string $query = '', int $page = 1, string $locale = 'en'): array
    {
        $where = ' FROM terms LEFT JOIN term_translations ON term_translations.term_id = terms.id AND term_translations.locale = ? WHERE terms.taxonomy_handle = ?';
        $parameters = [$locale, $taxonomy];
        if ($query !== '') {
            $where .= " AND (term_translations.name LIKE ? ESCAPE '!' OR term_translations.slug LIKE ? ESCAPE '!')";
            $pattern = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $parameters[] = $pattern;
            $parameters[] = $pattern;
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $where, $parameters);
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($page, $pages));
        $items = $this->connection->fetchAllAssociative(
            "SELECT terms.id, term_translations.locale, COALESCE(term_translations.name, '') AS name, COALESCE(term_translations.slug, '') AS slug, (SELECT COUNT(*) FROM content_terms WHERE term_id = terms.id) AS content_count" . str_replace(' FROM terms', ' FROM terms LEFT JOIN taxonomy_term_positions ON taxonomy_term_positions.term_id = terms.id', $where) . ' ORDER BY COALESCE(taxonomy_term_positions.position, terms.id * 10), term_translations.name, terms.id LIMIT 20 OFFSET ' . (($page - 1) * 20),
            $parameters,
        );

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
