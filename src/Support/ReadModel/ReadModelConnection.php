<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\ReadModel;

use Illuminate\Database\Connection;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Projector\ReadModel\InteractWithStack;

abstract class ReadModelConnection implements ReadModel
{
    use InteractWithStack;

    public function __construct(protected readonly Connection $connection)
    {
    }

    public function initialize(): void
    {
        $this->connection->getSchemaBuilder()->create($this->tableName(), $this->up());
    }

    public function isInitialized(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable($this->tableName());
    }

    public function reset(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->disableForeignKeyConstraints();

        $this->connection->table($this->tableName())->truncate();

        $schema->enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->disableForeignKeyConstraints();

        $schema->drop($this->tableName());

        $schema->enableForeignKeyConstraints();
    }

    abstract protected function up(): callable;

    abstract protected function tableName(): string;
}
