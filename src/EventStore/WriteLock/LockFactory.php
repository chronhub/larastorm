<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\WriteLock;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

class LockFactory
{
    public function __construct(protected Container $container)
    {
    }

    public function createLock(Connection $connection, null|bool|string $lock): WriteLockStrategy
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
