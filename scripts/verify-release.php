#!/usr/bin/env php
<?php

declare(strict_types=1);

use Jinstan\Core\Bootstrap\Bootstrap;
use Nyholm\Psr7\ServerRequest;

$archivePath = $argv[1] ?? '';
if ($archivePath === '' || !is_file($archivePath)) {
    fwrite(STDERR, "用法：php scripts/verify-release.php dist/jinstan-<version>.zip\n");
    exit(1);
}
if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('验证发布包需要 PHP Zip 扩展。');
}

$archive = new ZipArchive();
if ($archive->open($archivePath) !== true) {
    throw new RuntimeException('无法打开发布压缩包。');
}

$entries = [];
for ($index = 0; $index < $archive->numFiles; ++$index) {
    $name = $archive->getNameIndex($index);
    if (!is_string($name) || $name === '' || str_starts_with($name, '/') || str_contains($name, '../')) {
        throw new RuntimeException('发布包包含不安全路径。');
    }
    $entries[$name] = true;
}

$roots = [];
foreach (array_keys($entries) as $entry) {
    $root = strstr($entry, '/', true);
    $roots[$root === false ? $entry : $root] = true;
}
if (count($roots) !== 1) {
    throw new RuntimeException('发布包必须只有一个根目录。');
}
$root = (string) array_key_first($roots);
if (preg_match('/^jinstan-[A-Za-z0-9][A-Za-z0-9._-]*$/', $root) !== 1) {
    throw new RuntimeException('发布包根目录名称无效。');
}

foreach ([
    'CHANGELOG.md',
    'LICENSE',
    'README.md',
    'SECURITY.md',
    'cms',
    'composer.json',
    'composer.lock',
    'core/vendor/autoload.php',
    'core/src/Bootstrap/Bootstrap.php',
    'core/assets/manifest.php',
    'config/app.php',
    'docs/installation/README.md',
    'public/index.php',
    'themes/default/theme.json',
    'storage/cache/',
    'storage/config/',
    'storage/database/',
    'storage/logs/',
] as $required) {
    if (!isset($entries[$root . '/' . $required])) {
        throw new RuntimeException(sprintf('发布包缺少：%s', $required));
    }
}

foreach (array_keys($entries) as $entry) {
    $relative = substr($entry, strlen($root) + 1);
    if (preg_match('~^(?:tests|node_modules|dist|\.git)(?:/|$)~', $relative) === 1
        || preg_match('~^core/vendor/(?:bin/(?:phpunit|php-cs-fixer)|phpunit/|friendsofphp/)~', $relative) === 1
        || preg_match('~^storage/(?:installed\.php|config/database\.php|database/.+|logs/.+|cache/.+)~', $relative) === 1) {
        throw new RuntimeException(sprintf('发布包包含开发文件或运行数据：%s', $relative));
    }
}

$temporaryPath = sys_get_temp_dir() . '/jinstan-release-verify-' . bin2hex(random_bytes(6));
if (!mkdir($temporaryPath, 0750, true) && !is_dir($temporaryPath)) {
    throw new RuntimeException('无法创建发布包验证目录。');
}

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

try {
    if (!$archive->extractTo($temporaryPath)) {
        throw new RuntimeException('无法解压发布包。');
    }
    $projectPath = $temporaryPath . '/' . $root;
    require $projectPath . '/core/vendor/autoload.php';

    /** @var array{release: string, files: array<string, string>} $manifest */
    $manifest = require $projectPath . '/core/assets/manifest.php';
    foreach ($manifest['files'] as $path => $hash) {
        $asset = $projectPath . '/public/assets/core/' . $manifest['release'] . '/' . $path;
        if (!is_file($asset) || !hash_equals($hash, (string) hash_file('sha256', $asset))) {
            throw new RuntimeException(sprintf('发布资源校验失败：%s', $path));
        }
    }

    $run = static function (array $command, string $workingDirectory): string {
        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workingDirectory,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动发布包命令。');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                "发布包命令失败：%s\n%s",
                implode(' ', $command),
                trim((string) $error),
            ));
        }

        return (string) $output;
    };

    $manifestOutput = $run([PHP_BINARY, $projectPath . '/cms', 'assets:manifest'], $projectPath);
    if (!str_contains($manifestOutput, 'Core 资源清单已生成')) {
        throw new RuntimeException('发布包 CLI 资源命令未正常完成。');
    }

    $_SESSION = [];
    $application = Bootstrap::create($projectPath);
    $response = $application->handle(new ServerRequest('GET', '/install'));
    if ($response->getStatusCode() !== 200 || !str_contains((string) $response->getBody(), '安装 Jinstan')) {
        throw new RuntimeException('发布包无法从 /install 启动。');
    }

    if (preg_match('/name="_token" value="([a-f0-9]{64})"/', (string) $response->getBody(), $matches) !== 1) {
        throw new RuntimeException('无法读取安装页 CSRF Token。');
    }
    $installRequest = (new ServerRequest('POST', '/install'))->withParsedBody([
        '_token' => $matches[1],
        'site_name' => 'Jinstan Release Verification',
        'email' => 'release@example.test',
        'password' => 'release-test-password',
        'database_driver' => 'sqlite',
    ]);
    $installResponse = $application->handle($installRequest);
    if ($installResponse->getStatusCode() !== 303 || $installResponse->getHeaderLine('Location') !== '/') {
        throw new RuntimeException('发布包无法通过 Web Installer 完成安装。');
    }

    $installedApplication = Bootstrap::create($projectPath);
    $loginPage = $installedApplication->handle(new ServerRequest('GET', '/login'));
    if ($loginPage->getStatusCode() !== 200
        || preg_match('/name="_token" value="([a-f0-9]{64})"/', (string) $loginPage->getBody(), $matches) !== 1
    ) {
        throw new RuntimeException('安装后的登录页不可用。');
    }
    $loginResponse = $installedApplication->handle(
        (new ServerRequest('POST', '/login'))->withParsedBody([
            '_token' => $matches[1],
            'email' => 'release@example.test',
            'password' => 'release-test-password',
        ]),
    );
    if ($loginResponse->getStatusCode() !== 303 || $loginResponse->getHeaderLine('Location') !== '/admin') {
        throw new RuntimeException('发布包安装后的管理员登录失败。');
    }
    $adminResponse = $installedApplication->handle(new ServerRequest('GET', '/admin'));
    if ($adminResponse->getStatusCode() !== 200 || !str_contains((string) $adminResponse->getBody(), '后台概览')) {
        throw new RuntimeException('发布包安装后的后台不可用。');
    }

    $databaseOutput = $run([PHP_BINARY, $projectPath . '/cms', 'database:status'], $projectPath);
    if (!str_contains($databaseOutput, 'Driver: sqlite') || !str_contains($databaseOutput, 'Migrations: 1 executed')) {
        throw new RuntimeException('发布包 CLI 数据库命令未通过安装后检查。');
    }
} finally {
    $archive->close();
    $removeDirectory($temporaryPath);
}

printf("发布包验证通过：%s（%d 个条目）\n", $archivePath, count($entries));
