<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\WriteLock;

use Illuminate\Database\ConnectionInterface;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;

final class PgsqlWriteLock implements WriteLockStrategy
{
    public function __construct(private readonly ConnectionInterface $connection)
    {
    }

    public function acquireLock(string $tableName): bool
    {
        $name = $this->determineLockName($tableName);

        return $this->connection->statement('select pg_advisory_lock( hashtext(\''.$name.'\') )');
    }

    public function releaseLock(string $tableName): bool
    {
        $name = $this->determineLockName($tableName);

        return $this->connection->statement('select pg_advisory_unlock( hashtext(\''.$name.'\') )');
    }

    private function determineLockName(string $tableName): string
    {
        return '_'.$tableName.'_write_lock';
    }
}
