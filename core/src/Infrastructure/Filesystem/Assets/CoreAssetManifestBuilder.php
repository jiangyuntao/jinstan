<?php

declare(strict_types=1);

namespace Jinstan\Core\Infrastructure\Filesystem\Assets;

final class CoreAssetManifestBuilder
{
    /** @var array<string, string> */
    private const ASSETS = [
        'admin.css' => 'admin.css',
        'alpine.js' => 'alpine.min.js',
        'charts.js' => 'chart.js/chart.umd.min.js',
        'code-editor.js' => 'codeflask.min.js',
        'icons.css' => 'tabler-icons/tabler-icons.min.css',
    ];

    public function build(string $corePath, string $publicPath): string
    {
        $version = $this->version($corePath . '/src/Version.php');
        $releasePath = rtrim($publicPath, '/') . '/assets/core/' . $version;
        if (!is_dir($releasePath)) {
            throw new \RuntimeException(sprintf('Core 资源目录不存在：%s', $releasePath));
        }

        foreach (self::ASSETS as $path) {
            if (!is_file($releasePath . '/' . $path)) {
                throw new \RuntimeException(sprintf('缺少 Core 资源：%s', $path));
            }
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($releasePath, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($releasePath) + 1));
            $files[$path] = (string) hash_file('sha256', $file->getPathname());
        }
        ksort($files);

        $manifest = [
            'release' => $version,
            'assets' => self::ASSETS,
            'files' => $files,
        ];
        $manifestPath = rtrim($corePath, '/') . '/assets/manifest.php';
        $directory = dirname($manifestPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('无法创建 Core 资源清单目录。');
        }

        $temporary = $manifestPath . '.tmp-' . bin2hex(random_bytes(4));
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($manifest, true) . ";\n";
        if (file_put_contents($temporary, $contents, LOCK_EX) === false || !rename($temporary, $manifestPath)) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw new \RuntimeException('无法写入 Core 资源清单。');
        }

        return $manifestPath;
    }

    private function version(string $versionPath): string
    {
        $source = is_file($versionPath) ? file_get_contents($versionPath) : false;
        if (!is_string($source)
            || preg_match("/CURRENT\\s*=\\s*'([^']+)'/", $source, $matches) !== 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $matches[1]) !== 1
        ) {
            throw new \RuntimeException('无法读取有效的 Core 版本。');
        }

        return $matches[1];
    }
}
