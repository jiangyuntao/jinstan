<?php

declare(strict_types=1);

namespace Jinstan\Tests\Unit\Database;

use Jinstan\Core\Database\Schema\Schema;
use PHPUnit\Framework\TestCase;

final class SchemaTest extends TestCase
{
    public function testItCompilesSupportedDrivers(): void
    {
        $schema = new Schema();
        $table = $schema->createTable('examples');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('name', 'string', ['length' => 100]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['name'], 'examples_name_unique');

        self::assertStringContainsString('INTEGER', $schema->statements('sqlite')[0]);
        self::assertStringContainsString('AUTO_INCREMENT', $schema->statements('mysql')[0]);
        self::assertStringContainsString('SERIAL', $schema->statements('pgsql')[0]);
    }
}
