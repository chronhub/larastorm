<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\WriteLock;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

class WriteLockFactory
{
    public function __construct(protected Container $container)
    {
    }

    public function __invoke(Connection $connection, array $config): WriteLockStrategy
    {
        $writeLock = $config['write_lock'] ?? null;

        if ($writeLock === null) {
            throw new InvalidArgumentException('Write lock is not defined');
        }

        if ($writeLock === false) {
            return new FakeWriteLock();
        }

        $driver = $connection->getDriverName();

        // Use default write lock strategy
        if (true === $writeLock) {
            return match ($driver) {
                'pgsql' => new PgsqlWriteLock($connection),
                'mysql' => new MysqlWriteLock(),
                default => throw new InvalidArgumentException("Unavailable write lock strategy for driver $driver"),
            };
        }

        // at this point, write lock strategy should be a service, and we just resolve it
        return $this->container[$writeLock];
    }
}
