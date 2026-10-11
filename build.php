#!/usr/bin/env php
<?php

declare(strict_types=1);

use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifest;
use Jinstan\Core\Infrastructure\Filesystem\Assets\CoreAssetManifestBuilder;

$projectPath = __DIR__;

if (!function_exists('proc_open')) {
    throw new RuntimeException('打包需要启用 proc_open，以构建前端资源并安装生产依赖。');
}

fwrite(STDOUT, "正在安装前端依赖……\n");
$npm = proc_open(
    ['npm', 'ci', '--no-audit', '--no-fund'],
    [STDIN, STDOUT, STDERR],
    $pipes,
    $projectPath,
);
if (!is_resource($npm) || proc_close($npm) !== 0) {
    throw new RuntimeException('前端依赖安装失败，已停止打包。');
}

fwrite(STDOUT, "正在构建并压缩前端资源……\n");
$frontend = proc_open(
    ['npm', 'run', 'build'],
    [STDIN, STDOUT, STDERR],
    $pipes,
    $projectPath,
);
if (!is_resource($frontend) || proc_close($frontend) !== 0) {
    throw new RuntimeException('前端资源构建失败，已停止打包。');
}

fwrite(STDOUT, "正在安装生产依赖……\n");
$buildWorkspacePath = $projectPath . '/storage/updates/build-project-' . bin2hex(random_bytes(6));
$productionVendorPath = $buildWorkspacePath . '/core/vendor';
$removeDirectory = static function (string $directory) use (&$removeDirectory): void {
    if (!is_dir($directory)) {
        return;
    }
    foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $item) {
        if ($item->isDir() && !$item->isLink()) {
            $removeDirectory($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($directory);
};
$copyDirectory = static function (string $source, string $target) use (&$copyDirectory): void {
    if (!is_dir($target) && !mkdir($target, 0750, true) && !is_dir($target)) {
        throw new RuntimeException(sprintf('无法创建构建目录：%s', $target));
    }
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $item) {
        $destination = $target . '/' . $item->getFilename();
        if ($item->isDir() && !$item->isLink()) {
            $copyDirectory($item->getPathname(), $destination);
        } elseif (!$item->isLink() && !copy($item->getPathname(), $destination)) {
            throw new RuntimeException(sprintf('无法复制构建文件：%s', $item->getPathname()));
        }
    }
};
register_shutdown_function(static function () use (&$buildWorkspacePath, $removeDirectory): void {
    if ($buildWorkspacePath !== '') {
        $removeDirectory($buildWorkspacePath);
    }
});
$copyDirectory($projectPath . '/core/src', $buildWorkspacePath . '/core/src');
$copyDirectory($projectPath . '/site', $buildWorkspacePath . '/site');
foreach (['composer.json', 'composer.lock'] as $composerFile) {
    if (!copy($projectPath . '/' . $composerFile, $buildWorkspacePath . '/' . $composerFile)) {
        throw new RuntimeException(sprintf('无法准备生产依赖配置：%s', $composerFile));
    }
}
$composerEnvironment = getenv();
$composerEnvironment['COMPOSER_CACHE_DIR'] = $projectPath . '/storage/cache/composer';
$composer = proc_open(
    ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
    [STDIN, STDOUT, STDERR],
    $pipes,
    $buildWorkspacePath,
    $composerEnvironment,
);
if (!is_resource($composer) || proc_close($composer) !== 0) {
    throw new RuntimeException('生产依赖安装失败，已停止打包。');
}

require $projectPath . '/core/vendor/autoload.php';

$manifestPath = (new CoreAssetManifestBuilder())->build(
    $projectPath . '/core',
    $projectPath . '/public',
);
printf("Core 资源清单已更新：%s\n", $manifestPath);

$manifest = CoreAssetManifest::load($projectPath . '/core');
$releaseName = 'jinstan-' . $manifest->release();
$distPath = $projectPath . '/dist';
$archivePath = $distPath . '/' . $releaseName . '.zip';

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('打包需要 PHP Zip 扩展。');
}
if (!is_dir($distPath) && !mkdir($distPath, 0755, true) && !is_dir($distPath)) {
    throw new RuntimeException('无法创建 dist 目录。');
}

foreach ($manifest->files() as $path => $expectedHash) {
    $file = $projectPath . '/public/assets/core/' . $manifest->release() . '/' . $path;
    if (!is_file($file) || !hash_equals($expectedHash, (string) hash_file('sha256', $file))) {
        throw new RuntimeException(sprintf('Core 资源缺失或校验失败：%s', $path));
    }
}

$iconsCss = (string) file_get_contents(
    $projectPath . '/public/assets/core/' . $manifest->release() . '/tabler-icons/tabler-icons.min.css',
);
foreach (['core', 'site', 'plugins', 'themes'] as $sourceDirectory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $projectPath . '/' . $sourceDirectory,
        FilesystemIterator::SKIP_DOTS,
    ));
    foreach ($iterator as $sourceFile) {
        if (!$sourceFile->isFile() || !in_array($sourceFile->getExtension(), ['php', 'jst', 'js'], true)) {
            continue;
        }
        preg_match_all('/\bti-[a-z0-9-]+/', (string) file_get_contents($sourceFile->getPathname()), $matches);
        foreach (array_unique($matches[0]) as $icon) {
            if (!str_contains($iconsCss, '.' . $icon . ':before')) {
                throw new RuntimeException(sprintf('图标子集缺少 %s，请先更新 Core 图标资源。', $icon));
            }
        }
    }
}

$temporaryPath = $archivePath . '.tmp-' . bin2hex(random_bytes(4));
$archive = new ZipArchive();
if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    throw new RuntimeException('无法创建发布压缩包。');
}
$archiveClosed = false;

try {
    $addFile = static function (string $source, string $target) use ($archive): void {
        if (!$archive->addFile($source, $target)) {
            throw new RuntimeException(sprintf('无法添加发布文件：%s', $source));
        }
        if (!$archive->setCompressionName($target, ZipArchive::CM_DEFLATE, 9)) {
            throw new RuntimeException(sprintf('无法设置发布文件的压缩等级：%s', $target));
        }
    };

    $addDirectory = static function (
        string $source,
        string $target,
        bool $skipVendor = false,
        bool $vendorContents = false,
    ) use ($archive, $addFile): void {
        $archive->addEmptyDir($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || $item->getFilename() === '.DS_Store') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($source) + 1));
            if ($skipVendor && ($relative === 'vendor' || str_starts_with($relative, 'vendor/'))) {
                continue;
            }
            $vendorRelative = $vendorContents
                ? $relative
                : (str_starts_with($relative, 'vendor/') ? substr($relative, 7) : null);
            if ($vendorRelative !== null && (
                preg_match('~^(?:bin(?:/|$)|.+/(?:tests?|docs?|examples?|\.github)(?:/|$))~i', $vendorRelative) === 1
                || preg_match('~/(?:CHANGELOG|CONTRIBUTING|README|UPGRADE)(?:\.[^/]*)?$~i', $vendorRelative) === 1
            )) {
                continue;
            }
            $entry = $target . '/' . $relative;
            if ($item->isDir()) {
                $archive->addEmptyDir($entry);
            } else {
                $addFile($item->getPathname(), $entry);
            }
        }
    };

    foreach (['core', 'config', 'docs', 'site', 'plugins', 'themes'] as $directory) {
        $addDirectory(
            $projectPath . '/' . $directory,
            $releaseName . '/' . $directory,
            $directory === 'core',
        );
    }
    $addDirectory($productionVendorPath, $releaseName . '/core/vendor', vendorContents: true);

    foreach (['cms', 'CHANGELOG.md', 'composer.json', 'composer.lock', 'LICENSE', 'README.md', 'SECURITY.md'] as $file) {
        if (is_file($projectPath . '/' . $file)) {
            $addFile($projectPath . '/' . $file, $releaseName . '/' . $file);
        }
    }

    $archive->addEmptyDir($releaseName . '/public');
    $addFile($projectPath . '/public/index.php', $releaseName . '/public/index.php');
    $archive->addEmptyDir($releaseName . '/public/assets');
    $logo = $projectPath . '/public/assets/jinstan-cms-logo-light.png';
    if (is_file($logo)) {
        $addFile($logo, $releaseName . '/public/assets/jinstan-cms-logo-light.png');
    }
    $addDirectory(
        $projectPath . '/public/assets/core/' . $manifest->release(),
        $releaseName . '/public/assets/core/' . $manifest->release(),
    );

    $archive->addEmptyDir($releaseName . '/storage');
    foreach (['backups', 'cache', 'config', 'content-attachments', 'database', 'logs', 'media', 'updates'] as $directory) {
        $archive->addEmptyDir($releaseName . '/storage/' . $directory);
    }

    if (!$archive->close()) {
        throw new RuntimeException('无法完成发布压缩包。');
    }
    $archiveClosed = true;
    if (is_file($archivePath) && !unlink($archivePath)) {
        throw new RuntimeException('无法替换已有发布压缩包。');
    }
    if (!rename($temporaryPath, $archivePath)) {
        throw new RuntimeException('无法启用新发布压缩包。');
    }
} catch (Throwable $exception) {
    if (!$archiveClosed) {
        $archive->close();
    }
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
    throw $exception;
}

printf("发布包已生成：%s\n", $archivePath);
printf("压缩包大小：%.2f MiB\n", filesize($archivePath) / 1024 / 1024);
$checksumPath = $archivePath . '.sha256';
$checksum = hash_file('sha256', $archivePath);
if ($checksum === false
    || file_put_contents($checksumPath, $checksum . '  ' . basename($archivePath) . "\n", LOCK_EX) === false
) {
    throw new RuntimeException('无法生成发布包 SHA-256 校验文件。');
}
printf("SHA-256：%s\n", $checksum);
$removeDirectory($buildWorkspacePath);
$buildWorkspacePath = '';
fwrite(STDOUT, "隔离的生产依赖已清理，开发环境未被修改。\n");
