<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Illuminate\Database\Connection;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\WriteLock\WriteLockFactory;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoaderFactory;
use Chronhub\Larastorm\EventStore\Persistence\StreamPersistenceFactory;

class EventStoreProviderFactory
{
    protected Container $container;

    public function __construct(protected readonly WriteLockFactory $writeLockFactory,
                                protected readonly StreamEventLoaderFactory $streamEventLoaderFactory,
                                protected readonly StreamPersistenceFactory $streamPersistenceFactory)
    {
    }

    public function __invoke(Connection $connection,
                             string $name,
                             array $config,
                             bool $isTransactional): ChroniclerConnection
    {
        $args = [
            $connection,
            ($this->streamPersistenceFactory)($name, $connection, $config['strategy'] ?? null),
            ($this->streamEventLoaderFactory)($config['query_loader'] ?? null),
            $this->determineEventStreamProvider(),
            $this->container[StreamCategory::class],
            ($this->writeLockFactory)($connection, $config),
        ];

        return $isTransactional ? new StoreTransactionalDatabase(...$args) : new StoreDatabase(...$args);
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    private function determineEventStreamProvider(): EventStreamProvider
    {
        return $this->container->bound(EventStreamProvider::class)
            ? $this->container[EventStreamProvider::class]
            : new EventStream();
    }
}
