<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Aggregate\AggregateRepository;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as Repository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as Manager;
use function ucfirst;
use function method_exists;

final class AggregateRepositoryManager implements Manager
{
    private readonly Container $container;

    private readonly AggregateRepositoryFactory $factory;

    /**
     * @var array<Repository>
     */
    private array $repositories = [];

    /**
     * @var array<non-empty-string, callable(Container, non-empty-string, array, AggregateRepositoryFactory): AggregateRepository>
     */
    private array $customCreators = [];

    public function __construct(Closure $container)
    {
        $this->container = $container();
        $this->factory = new AggregateRepositoryFactory($container);
    }

    /**
     * @param  non-empty-string  $streamName
     */
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
            return $this->customCreators[$streamName]($this->container, $streamName, $config, $this->factory);
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
        $eventStore = $this->factory->eventStoreResolver->resolve($config['chronicler']);

        $aggregateType = $this->factory->aggregateTypeFactory->createType($config['aggregate_type']);

        $streamProducer = $this->factory->streamProducerFactory->createStreamProducer(
            $streamName, $config['strategy'] ?? null
        );

        $aggregateCache = $this->factory->aggregateCacheFactory->createCache(
            $aggregateType->current(),
            $config['cache']['size'] ?? 0,
            $config['cache']['tag'] ?? null,
            $config['cache']['driver'] ?? null
        );

        $eventDecorators = $this->factory->chainMessageDecorator($config['event_decorators'] ?? []);

        return new AggregateRepository($eventStore, $streamProducer, $aggregateCache, $aggregateType, $eventDecorators);
    }
}
