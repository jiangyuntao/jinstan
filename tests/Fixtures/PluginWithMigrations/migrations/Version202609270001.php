<?php

declare(strict_types=1);

namespace Jinstan\Tests\Fixtures\PluginWithMigrations\Migrations;

use Jinstan\Core\Database\Migration;
use Jinstan\Core\Database\Schema\Schema;

final class Version202609270001 extends Migration
{
    public function getDescription(): string
    {
        return '创建测试插件记录表。';
    }

    public function up(Schema $schema): void
    {
        $records = $schema->createTable('test_plugin_records');
        $records->addColumn('id', 'integer', ['autoincrement' => true]);
        $records->addColumn('label', 'string', ['length' => 100]);
        $records->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('test_plugin_records');
    }
}
