<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Http;

use Jinstan\Core\Http\ResponseFactory;
use Jinstan\Core\Infrastructure\Filesystem\Http\LocalResponseFileReader;
use PHPUnit\Framework\TestCase;

final class ResponseFactoryTest extends TestCase
{
    public function testCreatesHtmlJsonAndRedirectResponses(): void
    {
        $responses = new ResponseFactory();

        $html = $responses->html('<h1>Jinstan</h1>');
        self::assertSame('text/html; charset=utf-8', $html->getHeaderLine('Content-Type'));

        $json = $responses->json(['name' => 'Jinstan', 'path' => '/docs']);
        self::assertSame('application/json; charset=utf-8', $json->getHeaderLine('Content-Type'));
        self::assertSame('{"name":"Jinstan","path":"/docs"}', (string) $json->getBody());

        $redirect = $responses->redirect('/admin');
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame('/admin', $redirect->getHeaderLine('Location'));
    }

    public function testCreatesAFileResponse(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jinstan-response-');
        self::assertNotFalse($path);
        file_put_contents($path, 'file body');

        $response = (new ResponseFactory(new LocalResponseFileReader()))->file($path, 'text/plain');

        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('9', $response->getHeaderLine('Content-Length'));
        self::assertSame('file body', (string) $response->getBody());
        unlink($path);
    }
}
