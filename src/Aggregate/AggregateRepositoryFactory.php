<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Closure;
use InvalidArgumentException;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Aggregate\AggregateRepository;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Aggregate\AbstractAggregateRepository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as Repository;
use function is_a;
use function array_map;
use function is_string;
use function array_merge;

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

    /**
     * @param  class-string|null  $extendedRepository
     */
    public function createRepository(string $streamName, array $config, ?string $extendedRepository = null): Repository
    {
        if ($extendedRepository && ! is_a($extendedRepository, AbstractAggregateRepository::class, true)) {
            throw new InvalidArgumentException(
                'Extended repository must be a subclass of '.AbstractAggregateRepository::class
            );
        }

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

        $aggregateRepositoryClass = $extendedRepository ?? AggregateRepository::class;

        return new $aggregateRepositoryClass($eventStore, $streamProducer, $aggregateCache, $aggregateType, $eventDecorators);
    }

    public function chainMessageDecorator(array $repositoryDecorators = []): MessageDecorator
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
