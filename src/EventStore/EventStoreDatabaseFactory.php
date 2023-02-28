<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderFactory;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;

class EventStoreDatabaseFactory
{
    protected Container $container;

    public function __construct(protected readonly LockFactory $lockFactory,
                                protected readonly EventLoaderFactory $eventLoaderFactory)
    {
    }

    public function createStore(Connection $connection,
                                array $config,
                                bool $isTransactional): ChroniclerConnection
    {
        $args = [
            $connection,
            $this->container[$config['strategy']],
            $this->eventLoaderFactory->createEventLoader($config['query_loader'] ?? null),
            $this->determineEventStreamProvider(),
            $this->container[StreamCategory::class],
            $this->lockFactory->createLock($connection, $config['write_lock'] ?? null),
        ];

        return $isTransactional ? new StoreTransactionalDatabase(...$args) : new StoreDatabase(...$args);
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function determineEventStreamProvider(): EventStreamProvider
    {
        return $this->container->bound(EventStreamProvider::class)
            ? $this->container[EventStreamProvider::class]
            : new EventStream();
    }
}
