<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProviderFactory;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;

class EventStoreDatabaseFactory
{
    protected Container $container;

    public function createStore(Connection $connection,
                                bool $isTransactional,
                                array $config): ChroniclerDB
    {
        $args = [
            $connection,
            $this->container[$config['strategy']],
            $this->createStreamEventLoader($config),
            $this->createEventStreamProvider($connection, $config),
            $this->container[StreamCategory::class],
            $this->createWriteLock($connection, $config),
        ];

        return $isTransactional
            ? new EventStoreTransactionalDatabase(...$args) : new EventStoreDatabase(...$args);
    }

    protected function createWriteLock(Connection $connection, array $config): WriteLockStrategy
    {
       $factory = new LockFactory($this->container);

       return $factory->createLock($connection, $config['write_lock'] ?? null);
    }

    protected function createStreamEventLoader(array $config): StreamEventLoaderConnection
    {
        $factory = new EventLoaderConnectionFactory($this->container);

        return $factory->createEventLoader($config['query_loader'] ?? null);
    }

    protected function createEventStreamProvider(Connection $connection, array $config): EventStreamProvider
    {
        $factory = new EventStreamProviderFactory($this->container);

        return $factory->createProvider($connection, $config['event_stream_provider'] ?? null);
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
}
