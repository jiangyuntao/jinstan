<?php

declare(strict_types=1);

namespace Jinstan\Core\Content;

/** Internal Extension API：后台列表与选择器使用的查询契约。 */
interface ContentAdminReadRepository extends ContentReadRepository
{
    /** @return array{items: list<Content>, total: int, page: int, pages: int, offset: int} */
    public function adminPage(string $type, string $status = '', int $page = 1, int $perPage = 10): array;

    /** @return list<array{id: int, title: string}> */
    public function searchTitles(
        string $type,
        string $locale,
        string $query,
        ?int $excludeId = null,
        int $limit = 20,
    ): array;
}
