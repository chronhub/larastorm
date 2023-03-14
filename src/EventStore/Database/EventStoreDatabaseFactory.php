<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;

class EventStoreDatabaseFactory
{
    protected Container $container;

    public function __construct(protected readonly LockFactory $lockFactory,
                                protected readonly EventLoaderConnectionFactory $eventLoaderFactory)
    {
    }

    public function createStore(Connection $connection,
                                string $streamPersistence,
                                null|bool|string $queryLoader,
                                bool|string $lock,
                                bool $isTransactional): ChroniclerDB
    {
        $args = [
            $connection,
            $this->container[$streamPersistence],
            $this->eventLoaderFactory->createEventLoader($queryLoader),
            $this->determineEventStreamProvider($connection),
            $this->container[StreamCategory::class],
            $this->lockFactory->createLock($connection, $lock),
        ];

        return $isTransactional
            ? new EventStoreTransactionalDatabase(...$args) : new EventStoreDatabase(...$args);
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function determineEventStreamProvider(Connection $connection): EventStreamProvider
    {
        if ($this->container->bound(EventStreamProvider::class)) {
            return $this->container[EventStreamProvider::class];
        }

        $model = new EventStream();
        $model->setConnection($connection->getName());

        return $model;
    }
}
