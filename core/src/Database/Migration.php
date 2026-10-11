<?php

declare(strict_types=1);

namespace Jinstan\Core\Database;

use Jinstan\Core\Database\Schema\Schema;
use Jinstan\Core\Infrastructure\Database\Connection;

abstract class Migration
{
    abstract public function up(Schema $schema): void;

    public function upData(Connection $connection): void
    {
    }

    abstract public function down(Schema $schema): void;
}
