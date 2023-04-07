<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\WriteLock;

use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;

class LockFactory
{
    public function __construct(protected Container $container)
    {
    }

    public function createLock(Connection $connection, bool|string|null $lock): WriteLockStrategy
    {
        if ($lock === false || $lock === null) {
            return new FakeWriteLock();
        }

        $driver = $connection->getDriverName();

        if ($lock === true) {
            return match ($driver) {
                'pgsql' => new PgsqlWriteLock($connection),
                'mysql' => new MysqlWriteLock(),
                default => throw new InvalidArgumentException("Unavailable write lock strategy for driver $driver"),
            };
        }

        return $this->container[$lock];
    }
}
