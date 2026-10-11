<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Field;

use Jinstan\Core\Field\DefaultFieldTypes;
use Jinstan\Core\Field\FieldType;
use Jinstan\Core\Field\FieldTypeRegistry;
use Jinstan\Core\Field\TextField;
use PHPUnit\Framework\TestCase;

final class FieldTypeRegistryTest extends TestCase
{
    public function testItCreatesAndIdentifiesBuiltInFieldTypes(): void
    {
        $types = new FieldTypeRegistry();
        DefaultFieldTypes::register($types);

        $field = $types->create('text');

        self::assertInstanceOf(TextField::class, $field);
        self::assertSame('text', $types->handle($field));
    }

    public function testPluginFieldFactoryReceivesPersistedSettings(): void
    {
        $types = new FieldTypeRegistry();
        $types->register(
            'prefix_text',
            PrefixTextField::class,
            static fn (array $settings): FieldType => new PrefixTextField((string) ($settings['prefix'] ?? '')),
        );

        $field = $types->create('prefix_text', ['prefix' => 'SKU-']);

        self::assertInstanceOf(PrefixTextField::class, $field);
        self::assertSame('SKU-123', $field->normalize('123'));
        self::assertSame('prefix_text', $types->handle($field));
    }

    public function testItRejectsDuplicateHandles(): void
    {
        $types = new FieldTypeRegistry();
        $types->register('prefix_text', PrefixTextField::class, static fn (array $settings): FieldType => new PrefixTextField(''));

        $this->expectException(\RuntimeException::class);
        $types->register('prefix_text', AnotherTextField::class, static fn (array $settings): FieldType => new AnotherTextField());
    }
}

final readonly class PrefixTextField implements FieldType
{
    public function __construct(private string $prefix)
    {
    }

    public function normalize(mixed $value): string
    {
        return $this->prefix . trim((string) $value);
    }

    public function valid(mixed $value): bool
    {
        return is_scalar($value);
    }

    public function adminView(): string
    {
        return 'fields/text.jst';
    }

    public function adminData(): array
    {
        return ['prefix' => $this->prefix];
    }

    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}

final class AnotherTextField implements FieldType
{
    public function normalize(mixed $value): mixed
    {
        return $value;
    }

    public function valid(mixed $value): bool
    {
        return true;
    }

    public function adminView(): string
    {
        return 'fields/text.jst';
    }

    public function adminData(): array
    {
        return [];
    }

    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
