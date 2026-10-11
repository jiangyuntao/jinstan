<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Infrastructure\Persistence\SEO\RedirectRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedirectSecurityTest extends TestCase
{
    #[DataProvider('unsafeRedirects')]
    public function testItRejectsUnsafeRedirectPaths(string $source, string $target): void
    {
        $repository = new RedirectRepository($this->connection());

        $this->expectException(\InvalidArgumentException::class);

        $repository->add($source, $target);
    }

    /** @return iterable<string, array{string, string}> */
    public static function unsafeRedirects(): iterable
    {
        yield 'absolute source' => ['https://attacker.test/old', '/new'];
        yield 'source query' => ['/old?campaign=1', '/new'];
        yield 'absolute target' => ['/old', 'https://attacker.test/new'];
        yield 'scheme-relative target' => ['/old', '//attacker.test/new'];
        yield 'backslash target' => ['/old', '/\\attacker.test/new'];
        yield 'header injection target' => ['/old', "/new\r\nX-Unsafe: yes"];
    }

    public function testItStoresLocalRedirectsWithQueryAndFragment(): void
    {
        $repository = new RedirectRepository($this->connection());

        $repository->add('/old', '/new?from=old#details');

        self::assertSame('/new?from=old#details', $repository->target('/old'));
    }

    public function testItIgnoresUnsafeTargetsAlreadyStoredByAnOlderVersion(): void
    {
        $connection = $this->connection();
        $connection->insert('redirects', [
            'source_path' => '/old',
            'target_path' => 'https://attacker.test/phishing',
            'status_code' => 301,
        ]);

        self::assertNull((new RedirectRepository($connection))->target('/old'));
    }

    private function connection(): \Jinstan\Core\Infrastructure\Database\Connection
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');

        return $connection;
    }
}
