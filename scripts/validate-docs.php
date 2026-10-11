#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectPath = dirname(__DIR__);
$errors = [];

foreach (['installation', 'upgrade', 'configuration', 'content-types', 'fields', 'themes', 'layouts', 'templates', 'components', 'plugins', 'events', 'routing', 'database', 'multilingual', 'seo', 'api'] as $directory) {
    if (!is_dir($projectPath . '/docs/' . $directory)) {
        $errors[] = '缺少文档目录：docs/' . $directory;
    }
}

foreach (glob($projectPath . '/plugins/*', GLOB_ONLYDIR) ?: [] as $plugin) {
    if (!is_file($plugin . '/README.md')) {
        $errors[] = '官方 Plugin 缺少 README：' . substr($plugin, strlen($projectPath) + 1);
    }
}

$markdown = [$projectPath . '/README.md', $projectPath . '/SECURITY.md', $projectPath . '/CHANGELOG.md'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectPath . '/docs', FilesystemIterator::SKIP_DOTS),
);
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'md') {
        $markdown[] = $file->getPathname();
    }
}

foreach ($markdown as $file) {
    if (!is_file($file)) {
        $errors[] = '缺少文档：' . substr($file, strlen($projectPath) + 1);
        continue;
    }
    $source = (string) file_get_contents($file);
    preg_match_all('/\[[^\]]*]\(([^)]+)\)/', $source, $matches);
    foreach ($matches[1] as $target) {
        $target = trim($target, " <>\t\n\r\0\x0B");
        if ($target === ''
            || str_starts_with($target, '#')
            || preg_match('~^(?:https?|mailto):~i', $target) === 1
        ) {
            continue;
        }
        $path = rawurldecode(explode('#', $target, 2)[0]);
        $resolved = str_starts_with($path, '/')
            ? $projectPath . $path
            : dirname($file) . '/' . $path;
        if (!file_exists($resolved)) {
            $errors[] = sprintf(
                '%s 包含无效链接：%s',
                substr($file, strlen($projectPath) + 1),
                $target,
            );
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

printf("文档验证通过：%d 个 Markdown 文件。\n", count($markdown));
