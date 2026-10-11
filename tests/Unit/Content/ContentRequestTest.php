<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Content;

use Jinstan\Core\Http\Requests\Admin\ContentRequest;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Http\Exceptions\ValidationException;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ContentRequestTest extends TestCase
{
    public function testItNormalizesFixedFieldsWithoutChangingDynamicFieldValues(): void
    {
        $request = (new ServerRequest('POST', '/admin/content/article'))->withParsedBody([
            'title' => ' 标题 ',
            'slug' => ' article ',
            'data' => ['body' => ' 保留空格 ', 'custom' => ['nested' => true]],
            'terms' => ['category' => ['2']],
        ]);

        self::assertSame([
            'title' => '标题',
            'slug' => 'article',
            'data' => ['body' => ' 保留空格 ', 'custom' => ['nested' => true]],
            'terms' => ['category' => ['2']],
        ], (new ContentRequest($this->settings()))->validated($request));
    }

    public function testItRejectsMalformedOuterStructure(): void
    {
        $result = (new ContentRequest($this->settings()))->validate([
            'title' => ['unexpected'],
            'slug' => ['unexpected'],
            'data' => 'unexpected',
            'terms' => 'unexpected',
            'template' => ['unexpected'],
            'parent_id' => ['unexpected'],
        ]);

        try {
            $result->validated();
            self::fail('Expected validation to fail.');
        } catch (ValidationException $exception) {
            self::assertSame(['title', 'slug', 'data', 'terms', 'template', 'parent_id'], array_keys($exception->errors()));
        }
    }

    public function testItRejectsInvalidLocaleAndPublishingInput(): void
    {
        $result = (new ContentRequest($this->settings()))->validate([
            'locale' => 'invalid',
            'published' => ['yes'],
        ]);

        self::assertSame(['locale', 'published'], array_keys($result->errors()));
        self::assertSame('en', $result->normalized()['locale']);
        self::assertFalse($result->normalized()['published']);
    }

    public function testItRejectsMalformedMediaOperationsBeforeTheyCanChangeAttachments(): void
    {
        $result = (new ContentRequest($this->settings()))->validate([
            'remove_media' => '1',
            'manage_media' => ['hero' => ['1']],
            'media_order' => ['gallery' => 'not-a-list'],
        ]);

        self::assertSame(['remove_media', 'manage_media', 'media_order'], array_keys($result->errors()));
        self::assertSame([], $result->normalized()['media_order']);
    }

    public function testItPreservesValidMediaOrderTokensForTheStorageLayer(): void
    {
        $input = [
            'remove_media' => ['hero' => '0'],
            'manage_media' => ['gallery' => '1'],
            'media_order' => ['gallery' => ['aa/bb/existing.png', 'new']],
        ];

        self::assertSame($input + ['title' => '', 'slug' => '', 'data' => [], 'terms' => []], (new ContentRequest($this->settings()))->validate($input)->validated());
    }

    private function settings(): SettingsRepository
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 3) . '/core/src/Database/Migrations');
        $settings = new SettingsRepository($connection);
        $settings->set('site.default_locale', 'en');
        $settings->set('site.enabled_locales', 'en zh-CN');

        return $settings;
    }
}
