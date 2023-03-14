<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProviderFactory;

class EventStoreDatabaseFactory
{
    protected Container $container;

    public function __construct(protected readonly LockFactory $lockFactory,
                                protected readonly EventLoaderConnectionFactory $eventLoaderFactory,
                                protected readonly EventStreamProviderFactory $eventStreamProviderFactory)
    {
    }

    public function createStore(Connection $connection,
                                bool $isTransactional,
                                array $config): ChroniclerDB
    {
        $args = [
            $connection,
            $this->container[$config['strategy']],
            $this->eventLoaderFactory->createEventLoader($config['query_loader'] ?? null),
            $this->eventStreamProviderFactory->createProvider($connection, $config['event_stream_provider'] ?? null),
            $this->container[StreamCategory::class],
            $this->lockFactory->createLock($connection, $config['write_lock'] ?? null),
        ];

        return $isTransactional
            ? new EventStoreTransactionalDatabase(...$args) : new EventStoreDatabase(...$args);
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
}
