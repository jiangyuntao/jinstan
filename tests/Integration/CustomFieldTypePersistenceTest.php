<?php

declare(strict_types=1);

namespace Jinstan\Tests\Integration;

use Jinstan\Core\Application\Content\Services\EditableContentTypes;
use Jinstan\Core\Infrastructure\Database\ConnectionFactory;
use Jinstan\Core\Infrastructure\Database\MigrationRunner;
use Jinstan\Core\Field\DefaultFieldTypes;
use Jinstan\Core\Field\FieldType;
use Jinstan\Core\Field\FieldTypeRegistry;
use Jinstan\Core\Infrastructure\Persistence\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class CustomFieldTypePersistenceTest extends TestCase
{
    public function testCustomFieldSettingsSurviveContentTypeExportAndReload(): void
    {
        $connection = (new ConnectionFactory())->create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new MigrationRunner())->migrate($connection, dirname(__DIR__, 2) . '/core/src/Database/Migrations');
        $fieldTypes = new FieldTypeRegistry();
        DefaultFieldTypes::register($fieldTypes);
        $fieldTypes->register(
            'rating_test',
            PersistedRatingField::class,
            static fn (array $settings): FieldType => new PersistedRatingField((int) ($settings['maximum'] ?? 5)),
        );
        $editable = new EditableContentTypes(new SettingsRepository($connection), $fieldTypes);

        $definition = $editable->definition([
            'handle' => 'review',
            'label' => '评测',
            'fields' => [[
                'name' => 'rating',
                'label' => '评分',
                'type' => 'rating_test',
                'maximum' => 10,
            ]],
        ]);
        $exported = $editable->export($definition);
        $reloaded = $editable->definition($exported);

        $field = $reloaded->fields['rating']->type;
        self::assertInstanceOf(PersistedRatingField::class, $field);
        self::assertSame(10, $field->adminData()['maximum']);
        self::assertSame('rating_test', $exported['fields'][0]['type']);
        self::assertSame(10, $exported['fields'][0]['maximum']);
    }
}

final readonly class PersistedRatingField implements FieldType
{
    public function __construct(private int $maximum)
    {
    }

    public function normalize(mixed $value): int
    {
        return (int) $value;
    }

    public function valid(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false
            && (int) $value >= 0
            && (int) $value <= $this->maximum;
    }

    public function adminView(): string
    {
        return 'fields/number.jst';
    }

    public function adminData(): array
    {
        return ['maximum' => $this->maximum];
    }

    public function adminValue(mixed $value): mixed
    {
        return $value;
    }
}
