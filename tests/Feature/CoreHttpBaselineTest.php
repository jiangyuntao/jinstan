<?php

declare(strict_types=1);

namespace Jinstan\Tests\Feature;

use Jinstan\Core\Application\Auth\Services\Authenticator;
use Jinstan\Core\Bootstrap\Bootstrap;
use Jinstan\Core\Infrastructure\Persistence\Content\ContentRepository;
use Jinstan\Core\Security\CsrfToken;
use Jinstan\Tests\Support\InstalledTestProject;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class CoreHttpBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testInstallerIsAvailableBeforeInstallationAndLockedAfterwards(): void
    {
        $uninstalled = InstalledTestProject::create(false);
        $available = Bootstrap::create($uninstalled->path)->handle(new ServerRequest('GET', '/install'));
        self::assertSame(200, $available->getStatusCode());
        self::assertStringContainsString('安装 Jinstan', (string) $available->getBody());

        $installed = InstalledTestProject::create();
        $locked = Bootstrap::create($installed->path)->handle(new ServerRequest('GET', '/install'));
        self::assertSame(404, $locked->getStatusCode());
    }

    public function testPublicLoginAndProtectedAdminResponsesRemainStable(): void
    {
        $project = InstalledTestProject::create();
        $application = Bootstrap::create($project->path);

        $home = $application->handle(new ServerRequest('GET', '/'));
        self::assertSame(200, $home->getStatusCode());
        self::assertNotSame('', (string) $home->getBody());
        self::assertSame('nosniff', $home->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('SAMEORIGIN', $home->getHeaderLine('X-Frame-Options'));
        self::assertSame(
            "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'",
            $home->getHeaderLine('Content-Security-Policy'),
        );

        $login = $application->handle(new ServerRequest('GET', '/login'));
        self::assertSame(200, $login->getStatusCode());
        self::assertStringContainsString('<form', (string) $login->getBody());

        foreach (['/admin', '/admin/content/article', '/admin/content/article/create'] as $path) {
            $response = $application->handle(new ServerRequest('GET', $path));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/login', $response->getHeaderLine('Location'));
        }
    }

    public function testLoginAndContentWritesRejectMissingCsrfTokens(): void
    {
        $project = InstalledTestProject::create();
        $application = Bootstrap::create($project->path);

        $login = $application->handle(
            (new ServerRequest('POST', '/login'))->withParsedBody([
                'email' => $project->adminEmail(),
                'password' => $project->adminPassword(),
            ]),
        );
        self::assertSame(419, $login->getStatusCode());
        self::assertSame('nosniff', $login->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $login->getHeaderLine('Referrer-Policy'));

        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($project->connection()));
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));

        $write = $application->handle(
            (new ServerRequest('POST', '/admin/content/article'))->withParsedBody([
                'title' => 'Missing token',
                'slug' => 'missing-token',
            ]),
        );
        self::assertSame(419, $write->getStatusCode());
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $project = InstalledTestProject::create();
        $application = Bootstrap::create($project->path);
        $csrf = new CsrfToken();

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $response = $application->handle(
                (new ServerRequest('POST', '/login'))->withParsedBody([
                    '_token' => $csrf->value(),
                    'email' => $project->adminEmail(),
                    'password' => 'incorrect-password',
                ]),
            );
            self::assertSame(422, $response->getStatusCode());
        }

        $blocked = $application->handle(
            (new ServerRequest('POST', '/login'))->withParsedBody([
                '_token' => $csrf->value(),
                'email' => $project->adminEmail(),
                'password' => 'incorrect-password',
            ]),
        );

        self::assertSame(429, $blocked->getStatusCode());
        self::assertGreaterThan(0, (int) $blocked->getHeaderLine('Retry-After'));
    }

    public function testAuthenticatedContentLifecycleRemainsAvailableThroughHttp(): void
    {
        $project = InstalledTestProject::create();
        $auth = new Authenticator(new \Jinstan\Core\Infrastructure\Persistence\Auth\DatabaseAuthenticationRepository($project->connection()));
        self::assertTrue($auth->attempt($project->adminEmail(), $project->adminPassword()));

        $application = Bootstrap::create($project->path);
        $csrf = new CsrfToken();
        $create = $application->handle(
            (new ServerRequest('POST', '/admin/content/article'))->withParsedBody([
                '_token' => $csrf->value(),
                'locale' => 'en',
                'title' => 'Baseline article',
                'slug' => 'baseline-article',
                'published' => '1',
                'data' => ['content' => 'Baseline body'],
            ]),
        );
        self::assertSame(303, $create->getStatusCode());

        $contents = new ContentRepository($project->connection());
        $articles = $contents->list('article');
        self::assertCount(1, $articles);
        $article = $articles[0];
        self::assertSame('published', $article->status);

        foreach ([
            '/admin/content/article',
            '/admin/content/article/create',
            '/admin/content/article/' . $article->id . '/edit?locale=en',
        ] as $path) {
            self::assertSame(200, $application->handle(new ServerRequest('GET', $path))->getStatusCode());
        }

        $update = $application->handle(
            (new ServerRequest('POST', '/admin/content/article/' . $article->id))->withParsedBody([
                '_token' => $csrf->value(),
                'locale' => 'en',
                'title' => 'Updated baseline article',
                'slug' => 'updated-baseline-article',
                'published' => '0',
                'data' => ['content' => 'Updated baseline body'],
            ]),
        );
        self::assertSame(303, $update->getStatusCode());
        self::assertSame('draft', $contents->find($article->id)?->status);
        self::assertSame('Updated baseline article', $contents->translation($article->id, 'en')?->title);

        $delete = $application->handle(
            (new ServerRequest('POST', '/admin/content/article/' . $article->id . '/delete'))->withParsedBody([
                '_token' => $csrf->value(),
            ]),
        );
        self::assertSame(303, $delete->getStatusCode());
        self::assertNull($contents->find($article->id));
    }
}
