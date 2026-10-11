<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Frontend;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\View\Presenters\Frontend\PublicContentPresenter;
use Jinstan\Core\Application\Content\Queries\PublicContentQuery;
use Jinstan\Core\Application\Content\Queries\PublicHomepageQuery;
use Jinstan\Core\Application\Content\Queries\PublicNotFoundQuery;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PublicContentController
{
    public function __construct(
        private readonly PublicHomepageQuery $homepageQuery,
        private readonly PublicContentPresenter $presenter,
        private readonly PublicContentQuery $contentQuery,
        private readonly PublicNotFoundQuery $notFoundQuery,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function homepage(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $homepage = $this->homepageQuery->execute($params);
        if ($homepage['kind'] === 'not_found') {
            return $this->notFound($request, $params);
        }

        return $this->responses->html($this->presenter->homepage($homepage));
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $content = $this->contentQuery->detail($request->getUri()->getPath(), $params);
        if ($content['kind'] === 'not_found') {
            return $this->notFound($request, $params);
        }
        if ($content['kind'] === 'redirect') {
            return $this->responses->redirect($content['target'], 301);
        }

        return $this->responses->html($this->presenter->content($content));
    }

    public function listing(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $listing = $this->contentQuery->listing($params, $request->getQueryParams());
        if ($listing['kind'] === 'not_found') {
            return $this->notFound($request, $params);
        }

        return $this->responses->html($this->presenter->listing($listing));
    }

    public function notFound(ServerRequestInterface $request, array $params = []): ResponseInterface
    {
        $notFound = $this->notFoundQuery->execute($request->getUri()->getPath(), $params);
        if ($notFound['kind'] === 'plain') {
            return $this->responses->text('Not Found', 404);
        }

        return $this->responses->html($this->presenter->notFound($notFound), 404, [
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
