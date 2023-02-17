<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Aggregate\AggregateRepository;
use Chronhub\Storm\Message\ChainMessageDecorator;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as Repository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as Manager;
use function ucfirst;
use function array_map;
use function is_string;
use function array_merge;
use function method_exists;

final class AggregateRepositoryManager implements Manager
{
    private readonly Container $container;

    private readonly EventStoreResolver $eventStoreResolver;

    private readonly AggregateCacheFactory $aggregateCacheFactory;

    private readonly AggregateTypeFactory $aggregateTypeFactory;

    private readonly StreamProducerFactory $streamProducerFactory;

    /**
     * @var array<Repository>
     */
    private array $repositories = [];

    /**
     * @var array<string, string|Repository>
     */
    private array $customCreators = [];

    public function __construct(Closure $container)
    {
        $this->container = $container();
        $this->eventStoreResolver = new EventStoreResolver($container);
        $this->aggregateTypeFactory = new AggregateTypeFactory($container);
        $this->aggregateCacheFactory = new AggregateCacheFactory();
        $this->streamProducerFactory = new StreamProducerFactory();
    }

    public function create(string $streamName): Repository
    {
        return $this->repositories[$streamName] ?? $this->repositories[$streamName] = $this->resolve($streamName);
    }

    public function extends(string $streamName, callable $aggregateRepository): void
    {
        $this->customCreators[$streamName] = $aggregateRepository;
    }

    private function resolve(string $streamName): Repository
    {
        $config = $this->container['config']["aggregate.repository.repositories.$streamName"];

        if ($config === null) {
            throw new InvalidArgumentException("Repository config with stream name $streamName is not defined");
        }

        if (isset($this->customCreators[$streamName])) {
            return $this->customCreators[$streamName]($this->container, $streamName, $config);
        }

        return $this->callAggregateRepository($streamName, $config);
    }

    private function callAggregateRepository(string $streamName, array $config): Repository
    {
        $driverMethod = 'create'.ucfirst(Str::camel($config['repository'].'RepositoryDriver'));

        /**
         * @covers createGenericRepositoryDriver
         */
        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($streamName, $config);
        }

        throw new InvalidArgumentException("Aggregate repository with stream name $streamName is not defined");
    }

    private function createGenericRepositoryDriver(string $streamName, array $config): Repository
    {
        $aggregateType = ($this->aggregateTypeFactory)($config['aggregate_type']);

        return new AggregateRepository(
            $this->eventStoreResolver->resolve($config['chronicler']),
            ($this->streamProducerFactory)($streamName, $config['strategy'] ?? null),
            ($this->aggregateCacheFactory)($aggregateType->current(), $config['cache'] ?? []),
            $aggregateType,
            $this->makeEventDecorators($config['event_decorators'] ?? [])
        );
    }

    private function makeEventDecorators(array $aggregateEventDecorators = []): MessageDecorator
    {
        $eventDecorators = [];

        if ($this->container['config']['aggregate.repository.use_messager_decorators'] === true) {
            $eventDecorators = $this->container['config']['messager.decorators'] ?? [];
        }

        $eventDecorators = array_map(
            fn (string|MessageDecorator $decorator) => is_string($decorator) ? $this->container[$decorator] : $decorator,
            array_merge(
                $eventDecorators,
                $this->container['config']['aggregate.repository.event_decorators'] ?? [],
                $aggregateEventDecorators
            )
        );

        return new ChainMessageDecorator(...$eventDecorators);
    }
}