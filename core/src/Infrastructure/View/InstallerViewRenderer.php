<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\View;

use Jinstan\Core\View\InstallerView;
use Latte\Engine;

final class InstallerViewRenderer implements InstallerView
{
    private readonly Engine $latte;

    public function __construct(string $viewsPath, string $cachePath)
    {
        if (!is_dir($cachePath) && !mkdir($cachePath, 0750, true) && !is_dir($cachePath)) {
            throw new \RuntimeException(sprintf('无法创建安装模板缓存目录：%s', $cachePath));
        }

        $this->latte = new Engine();
        $this->latte->setCacheDirectory($cachePath);
        $this->latte->setLoader(new \Latte\Loaders\FileLoader($viewsPath));
    }

    /** @param array<string, mixed> $data */
    public function render(string $view, array $data): string
    {
        return $this->latte->renderToString($view, $data);
    }
}
