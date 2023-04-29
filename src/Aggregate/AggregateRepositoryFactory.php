<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Larastorm\Snapshot\SnapshotStoreManager;
use Chronhub\Storm\Aggregate\AggregateReleaser;
use Chronhub\Storm\Aggregate\AggregateSnapshotRepository;
use Chronhub\Storm\Aggregate\GenericAggregateRepository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Closure;
use Illuminate\Contracts\Container\Container;
use RuntimeException;
use function array_map;
use function array_merge;
use function is_string;

class AggregateRepositoryFactory
{
    protected readonly Container $container;

    public readonly EventStoreResolver $eventStoreResolver;

    public readonly AggregateCacheFactory $aggregateCacheFactory;

    public readonly AggregateTypeFactory $aggregateTypeFactory;

    public readonly StreamProducerFactory $streamProducerFactory;

    public function __construct(Closure $container)
    {
        $this->container = $container();
        $this->eventStoreResolver = new EventStoreResolver($container);
        $this->aggregateTypeFactory = new AggregateTypeFactory($container);
        $this->aggregateCacheFactory = new AggregateCacheFactory();
        $this->streamProducerFactory = new StreamProducerFactory();
    }

    public function createRepository(string $streamName, array $config): AggregateRepository
    {
        $eventStore = $this->eventStoreResolver->resolve($config['chronicler']);

        $aggregateType = $this->aggregateTypeFactory->createType($config['aggregate_type']);

        $streamProducer = $this->streamProducerFactory->createStreamProducer(
            $streamName, $config['strategy'] ?? null
        );

        $aggregateCache = $this->aggregateCacheFactory->createCache(
            $aggregateType->current(),
            $config['cache']['size'] ?? 0,
            $config['cache']['tag'] ?? null,
            $config['cache']['driver'] ?? null
        );

        $eventDecorators = $this->chainMessageDecorator($config['event_decorators'] ?? []);

        $snapshotDriver = $config['use_snapshot'] ?? null;
        if (is_string($snapshotDriver)) {
            if (! $this->container->bound(SnapshotStoreManager::class)) {
                throw new RuntimeException('SnapshotStoreManager not bound in container');
            }

            $snapshotStoreManager = $this->container[SnapshotStoreManager::class];
            $snapshotQueryScope = $this->container['config']['snapshot'][$snapshotDriver]['query_scope'];

            return new AggregateSnapshotRepository(
                $eventStore,
                $streamProducer,
                $aggregateCache,
                $aggregateType,
                new AggregateReleaser($eventDecorators),
                $snapshotStoreManager->create($snapshotDriver),
                $this->container[$snapshotQueryScope],
            );
        }

        return new GenericAggregateRepository(
            $eventStore,
            $streamProducer,
            $aggregateCache,
            $aggregateType,
            new AggregateReleaser($eventDecorators),
        );
    }

    protected function chainMessageDecorator(array $repositoryDecorators = []): MessageDecorator
    {
        $messageDecorators = [];

        if ($this->container['config']['aggregate.repository.use_messager_decorators'] === true) {
            $messageDecorators = $this->container['config']['messager.decorators'] ?? [];
        }

        $messageDecorators = array_map(
            fn (string|MessageDecorator $decorator) => is_string($decorator) ? $this->container[$decorator] : $decorator,
            array_merge(
                $messageDecorators,
                $this->container['config']['aggregate.repository.event_decorators'] ?? [],
                $repositoryDecorators
            )
        );

        return new ChainMessageDecorator(...$messageDecorators);
    }
}
