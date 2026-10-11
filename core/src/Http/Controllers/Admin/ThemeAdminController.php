<?php

declare(strict_types=1);

namespace Jinstan\Core\Http\Controllers\Admin;

use Jinstan\Core\View\AdminView as AdminViewRenderer;
use Jinstan\Core\Application\Theme\Queries\ThemeManagerQuery;
use Jinstan\Core\Application\Theme\Services\ThemeWriter;
use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Routing\UrlGenerator;
use Jinstan\Core\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ThemeAdminController
{
    public function __construct(
        private readonly CsrfToken $csrf,
        private readonly ThemeManagerQuery $managerQuery,
        private readonly ThemeWriter $writer,
        private readonly AdminViewRenderer $views,
        private readonly UrlGenerator $urls,
        private readonly ResponseFactory $responses,
    ) {
    }

    public function show(ServerRequestInterface $request, array $params): ResponseInterface
    {
        try {
            $data = $this->managerQuery->execute($request->getQueryParams());
        } catch (\OutOfBoundsException $exception) {
            return $this->responses->text($this->views->translate($exception->getMessage()), 404);
        }

        return $this->responses->html(
            $this->views->render('themes', $this->views->translate('theme.manager'), $data),
        );
    }

    public function save(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->saveFile($this->writableInput($request)));
    }

    public function createTheme(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->createTheme($this->writableInput($request)));
    }

    public function deleteTheme(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->deleteTheme($this->writableInput($request)));
    }

    public function createDirectory(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->createDirectory($this->writableInput($request)));
    }

    public function deleteDirectory(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->deleteDirectory($this->writableInput($request)));
    }

    public function renameDirectory(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->renameDirectory($this->writableInput($request)));
    }

    public function createFile(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->createFile($this->writableInput($request)));
    }

    public function deleteFile(ServerRequestInterface $request, array $params): ResponseInterface
    {
        return $this->perform(fn (): array => $this->writer->deleteFile($this->writableInput($request)));
    }

    /** @return array<string, mixed> */
    private function writableInput(ServerRequestInterface $request): array
    {
        $input = $request->getParsedBody();

        return is_array($input) ? $input : [];
    }

    /** @param \Closure(): array{theme: string, file: string} $operation */
    private function perform(\Closure $operation): ResponseInterface
    {
        try {
            $destination = $operation();

            return $this->redirect($destination['theme'], $destination['file']);
        } catch (\InvalidArgumentException|\RuntimeException $exception) {
            return $this->responses->text($this->views->translate($exception->getMessage()), 422);
        }
    }

    private function redirect(string $theme, string $file = ''): ResponseInterface
    {
        return $this->responses->redirect($this->themeUrl($theme, $file));
    }

    private function themeUrl(string $theme = '', string $file = ''): string
    {
        $parameters = [];
        if ($theme !== '') {
            $parameters['theme'] = $theme;
        }
        if ($file !== '') {
            $parameters['file'] = $file;
        }
        return $this->urls->route('admin.themes.index', $parameters);
    }
}
