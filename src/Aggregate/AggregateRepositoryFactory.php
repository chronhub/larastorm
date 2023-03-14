<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Closure;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
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
