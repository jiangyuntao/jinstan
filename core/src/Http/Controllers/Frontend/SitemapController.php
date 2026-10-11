<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Frontend;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Application\SEO\Queries\SitemapQuery;
use Jinstan\Core\View\Presenters\Frontend\SitemapPresenter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SitemapController
{
    public function __construct(
        private readonly SitemapQuery $query,
        private readonly SitemapPresenter $presenter,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->responses->text(
            $this->presenter->present($this->query->execute()),
            headers: ['Content-Type' => 'application/xml; charset=utf-8'],
        );
    }
}
