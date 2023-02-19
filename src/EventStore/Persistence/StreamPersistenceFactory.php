<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use function is_string;

class StreamPersistenceFactory
{
    public function __construct(protected Container $container)
    {
    }

    public function __invoke(string $name, Connection $connection, ?string $persistenceKey): StreamPersistence
    {
        if ($persistenceKey === 'single_indexed' && $connection->getDriverName() !== 'mysql') {
            throw new InvalidArgumentException('Stream persistence single_indexed is only available for mysql');
        }

        return match (true) {
            $persistenceKey === 'single' => $this->container[PgsqlSingleStreamPersistence::class],
            //$persistenceKey === 'single_indexed' => $this->app[IndexedSingleStreamPersistence::class],
            $persistenceKey === 'per_aggregate' => $this->container[PerAggregateStreamPersistence::class],
            is_string($persistenceKey) => $this->container[$persistenceKey],
            default => throw new InvalidArgumentException("Invalid persistence strategy for chronicler $name")
        };
    }
}
