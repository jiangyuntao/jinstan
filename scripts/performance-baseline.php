<?php

declare(strict_types=1);

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Http\Application as HttpApplication;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;

require dirname(__DIR__) . '/core/vendor/autoload.php';

$iterations = 20;
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--iterations=(\d+)$/', $argument, $matches) === 1) {
        $iterations = max(1, min(500, (int) $matches[1]));
    }
}

/** @return array{average_ms: float, p95_ms: float, max_ms: float} */
function measure(callable $operation, int $iterations): array
{
    $samples = [];
    for ($index = 0; $index < $iterations; $index++) {
        $started = hrtime(true);
        $operation();
        $samples[] = (hrtime(true) - $started) / 1_000_000;
    }
    sort($samples);
    $p95 = $samples[(int) ceil(count($samples) * 0.95) - 1];

    return [
        'average_ms' => round(array_sum($samples) / count($samples), 3),
        'p95_ms' => round($p95, 3),
        'max_ms' => round(max($samples), 3),
    ];
}

function removeBaselineProject(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . '/jinstan-baseline-') || !is_dir($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        if ($item->isLink() || $item->isFile()) {
            unlink($item->getPathname());
        } else {
            rmdir($item->getPathname());
        }
    }
    rmdir($path);
}

$project = InstalledTestProject::create();

try {
    $connection = $project->connection();
    $contents = new ContentRepository($connection);
    $settings = new SettingsRepository($connection);
    $page = $contents->create(
        new Content(0, 'page', null, 'draft', null, null, []),
        new ContentTranslation('zh-CN', '性能基线首页', 'performance-home', null, []),
    );
    $contents->setPublished($page->id, true);
    $settings->set('site.homepage_id', (string) $page->id);
    $article = $contents->create(
        new Content(0, 'article', null, 'draft', null, null, []),
        new ContentTranslation('zh-CN', '性能基线文章', 'performance-article', null, []),
    );
    $contents->setPublished($article->id, true);

    $bootstrapIterations = min(5, $iterations);
    $bootstrap = measure(
        static fn () => Bootstrap::application($project->path),
        $bootstrapIterations,
    );

    $_SESSION = [];
    $application = Bootstrap::application($project->path);
    $container = $application->container();
    if (!$container->get(Authenticator::class)->attempt($project->adminEmail(), $project->adminPassword())) {
        throw new RuntimeException('性能基线管理员认证失败。');
    }
    $http = $container->get(HttpApplication::class);
    $routes = [
        'homepage' => '/',
        'content_detail' => '/blog/performance-article',
        'admin_content_list' => '/admin/content/article',
    ];
    $results = [];
    foreach ($routes as $name => $path) {
        $request = static fn (): ServerRequest => new ServerRequest('GET', $path);
        $response = $http->handle($request());
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException(sprintf('性能基线路由 %s 返回 %d。', $path, $response->getStatusCode()));
        }
        $results[$name] = measure(static fn () => $http->handle($request()), $iterations);
    }

    echo json_encode([
        'php' => PHP_VERSION,
        'iterations' => $iterations,
        'bootstrap_iterations' => $bootstrapIterations,
        'bootstrap' => $bootstrap,
        'requests' => $results,
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $_SESSION = [];
    removeBaselineProject($project->path);
}
