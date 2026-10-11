<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Persistence\Content;

use DateTimeImmutable;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentAdminReadRepository;
use Jinstan\Core\Content\ContentReadRepository;
use Jinstan\Core\Content\ContentRelationReadRepository;
use Jinstan\Core\Content\ContentRelationWriteRepository;
use Jinstan\Core\Content\ContentRepositoryContract;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Infrastructure\Database\Connection;

final class ContentRepository implements ContentRepositoryContract, ContentAdminReadRepository, ContentRelationReadRepository, ContentRelationWriteRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(Content $content, ContentTranslation $translation): Content
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $ownsTransaction = !$this->connection->isTransactionActive();
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }

        try {
            $this->connection->insert('contents', [
                'type' => $content->type, 'parent_id' => $content->parentId, 'status' => $content->status,
                'template' => $content->template, 'author_id' => $content->authorId, 'data' => $this->json($content->data),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->insertTranslation($id, $translation, $now);
            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        return new Content($id, $content->type, $content->parentId, $content->status, $content->template, $content->authorId, $content->data);
    }

    public function translation(int $contentId, string $locale): ?ContentTranslation
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM content_translations WHERE content_id = ? AND locale = ?', [$contentId, $locale]);
        if ($row === false) {
            return null;
        }

        return new ContentTranslation($row['locale'], $row['title'], $row['slug'], $row['excerpt'], $this->decode($row['data']), $row['seo_title'], $row['meta_description']);
    }

    /** @return list<ContentTranslation> */
    public function translations(int $contentId): array
    {
        return array_map(fn (array $row): ContentTranslation => new ContentTranslation($row['locale'], $row['title'], $row['slug'], $row['excerpt'], $this->decode($row['data']), $row['seo_title'], $row['meta_description']), $this->connection->fetchAllAssociative('SELECT * FROM content_translations WHERE content_id = ?', [$contentId]));
    }

    public function find(int $id): ?Content
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM contents WHERE id = ?', [$id]);
        if ($row === false) {
            return null;
        }

        return $this->contentFromRow($row);
    }

    /** @return list<Content> */
    public function list(string $type): array
    {
        // 以自增 ID 作为稳定的默认排序，新建内容始终出现在列表前面。
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM contents WHERE type = ? ORDER BY id DESC', [$type]);

        return array_map(fn (array $row): Content => $this->contentFromRow($row), $rows);
    }

    /** @return array{items: list<Content>, total: int, page: int, pages: int, offset: int} */
    public function adminPage(string $type, string $status = '', int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, min(100, $perPage));
        $where = ' FROM contents WHERE type = ?';
        $parameters = [$type];
        if ($status !== '') {
            $where .= ' AND status = ?';
            $parameters[] = $status;
        }
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $where, $parameters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;
        $rows = $this->connection->fetchAllAssociative(
            'SELECT *' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $parameters,
        );

        return [
            'items' => array_map(fn (array $row): Content => $this->contentFromRow($row), $rows),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'offset' => $offset,
        ];
    }

    /** Public API：只读取当前语言的已发布内容，在数据库内分页。 */
    public function publishedPage(
        string $type,
        string $locale,
        int $page = 1,
        int $perPage = 12,
        string $sort = 'newest',
    ): array {
        $perPage = max(1, min(100, $perPage));
        $orderBy = match ($sort) {
            'oldest' => 'contents.published_at ASC, contents.id ASC',
            'title_asc' => 'content_translations.title ASC, contents.id ASC',
            'title_desc' => 'content_translations.title DESC, contents.id DESC',
            default => 'contents.published_at DESC, contents.id DESC',
        };
        $where = ' FROM contents JOIN content_translations ON content_translations.content_id = contents.id WHERE contents.type = ? AND contents.status = ? AND content_translations.locale = ?';
        $parameters = [$type, 'published', $locale];
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*)' . $where, $parameters);
        $pages = max(1, (int) ceil($total / $perPage));
        $items = [];
        if ($page >= 1 && $page <= $pages) {
            $items = $this->connection->fetchAllAssociative('SELECT contents.id, content_translations.title, content_translations.slug, content_translations.excerpt, contents.data AS shared_data, content_translations.data AS localized_data' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $parameters);
        }
        foreach ($items as &$item) {
            $item['data'] = array_merge($this->decode($item['shared_data']), $this->decode($item['localized_data']));
            unset($item['shared_data'], $item['localized_data']);
        }
        unset($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage];
    }

    public function findBySlug(string $type, string $locale, string $slug): ?Content
    {
        $row = $this->connection->fetchAssociative(
            'SELECT contents.* FROM contents INNER JOIN content_translations ON contents.id = content_translations.content_id WHERE contents.type = ? AND contents.status = ? AND content_translations.locale = ? AND content_translations.slug = ?',
            [$type, 'published', $locale, $slug],
        );

        return $row === false ? null : $this->contentFromRow($row);
    }

    /** @return list<array{type: string, slug: string, locale: string}> */
    public function publishedSlugs(): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT contents.type, content_translations.slug, content_translations.locale FROM contents INNER JOIN content_translations ON contents.id = content_translations.content_id WHERE contents.status = 'published'",
        );
    }

    /** @return list<array{id: int, title: string}> */
    public function searchTitles(string $type, string $locale, string $query, ?int $excludeId = null, int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $sql = 'SELECT contents.id, content_translations.title FROM contents INNER JOIN content_translations ON contents.id = content_translations.content_id WHERE contents.type = ? AND content_translations.locale = ? AND content_translations.title LIKE ?';
        $parameters = [$type, $locale, '%' . $query . '%'];
        if ($excludeId !== null) {
            $sql .= ' AND contents.id <> ?';
            $parameters[] = $excludeId;
        }
        $sql .= ' ORDER BY content_translations.title ASC, contents.id ASC LIMIT ' . $limit;

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'title' => (string) $row['title']], $this->connection->fetchAllAssociative($sql, $parameters));
    }

    public function update(Content $content): void
    {
        $this->connection->update('contents', [
            'parent_id' => $content->parentId, 'status' => $content->status, 'template' => $content->template, 'data' => $this->json($content->data),
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $content->id]);
    }

    public function saveTranslation(int $contentId, ContentTranslation $translation): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $id = $this->connection->fetchOne('SELECT id FROM content_translations WHERE content_id = ? AND locale = ?', [$contentId, $translation->locale]);

        if ($id === false) {
            $this->insertTranslation($contentId, $translation, $now);

            return;
        }

        $this->connection->update('content_translations', [
            'title' => $translation->title, 'slug' => $translation->slug, 'excerpt' => $translation->excerpt,
            'data' => $this->json($translation->data), 'seo_title' => $translation->seoTitle,
            'meta_description' => $translation->metaDescription, 'updated_at' => $now,
        ], ['id' => (int) $id]);
    }

    public function publish(int $id): void
    {
        $this->setPublished($id, true);
    }

    public function setPublished(int $id, bool $published): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $this->connection->update('contents', [
            'status' => $published ? 'published' : 'draft',
            'published_at' => $published ? $now : null,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        // 显式清理关系，避免 SQLite 部署未启用外键约束时留下孤立记录。
        $this->connection->delete('content_relations', ['source_content_id' => $id]);
        $this->connection->delete('content_relations', ['target_content_id' => $id]);
        $this->connection->delete('content_terms', ['content_id' => $id]);
        $this->connection->delete('content_translations', ['content_id' => $id]);
        $this->connection->delete('contents', ['id' => $id]);
    }

    public function transaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->connection->isTransactionActive();
        if ($ownsTransaction) {
            $this->connection->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->connection->commit();
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    public function relate(int $sourceId, string $relation, int $targetId, int $position = 0): void
    {
        $this->connection->insert('content_relations', [
            'source_content_id' => $sourceId, 'relation_name' => $relation, 'target_content_id' => $targetId,
            'position' => $position, 'meta' => '{}',
        ]);
    }

    /** @return list<Content> */
    public function related(int $sourceId, string $relation): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT contents.* FROM contents INNER JOIN content_relations ON contents.id = content_relations.target_content_id WHERE content_relations.source_content_id = ? AND content_relations.relation_name = ? ORDER BY content_relations.position ASC',
            [$sourceId, $relation],
        );

        return array_map(fn (array $row): Content => $this->contentFromRow($row), $rows);
    }

    private function insertTranslation(int $contentId, ContentTranslation $translation, string $now): void
    {
        $this->connection->insert('content_translations', [
            'content_id' => $contentId, 'locale' => $translation->locale, 'title' => $translation->title,
            'slug' => $translation->slug, 'excerpt' => $translation->excerpt, 'data' => $this->json($translation->data),
            'seo_title' => $translation->seoTitle, 'meta_description' => $translation->metaDescription, 'updated_at' => $now,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decode(string $data): array
    {
        return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $row */
    private function contentFromRow(array $row): Content
    {
        return new Content(
            (int) $row['id'],
            $row['type'],
            $row['parent_id'] === null ? null : (int) $row['parent_id'],
            $row['status'],
            $row['template'],
            $row['author_id'] === null ? null : (int) $row['author_id'],
            $this->decode($row['data']),
        );
    }
}
