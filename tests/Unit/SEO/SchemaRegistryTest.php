<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\SEO;

use Jinstan\Core\Content\Content;
use Jinstan\Core\Content\ContentTranslation;
use Jinstan\Core\SEO\SchemaRegistrar;
use Jinstan\Core\SEO\SchemaRegistry;
use PHPUnit\Framework\TestCase;

final class SchemaRegistryTest extends TestCase
{
    public function testItExposesThePluginRegistrarContractAndRendersJsonLd(): void
    {
        $registry = new SchemaRegistry();

        self::assertInstanceOf(SchemaRegistrar::class, $registry);

        $content = new Content(1, 'article', null, 'published', null, null, []);
        $translation = new ContentTranslation('zh-CN', '标题', 'slug', '', []);
        $registry->add(static fn (): array => ['@type' => 'Article', 'name' => '标题']);

        self::assertSame(
            '<script type="application/ld+json">[{"@type":"Article","name":"标题"}]</script>',
            $registry->render($content, $translation),
        );
    }
}
