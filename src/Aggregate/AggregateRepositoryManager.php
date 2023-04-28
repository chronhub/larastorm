<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Larastorm\Support\Contracts\AggregateRepositoryManager as Manager;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as Repository;
use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class AggregateRepositoryManager implements Manager
{
    private readonly Container $container;

    private readonly AggregateRepositoryFactory $factory;

    /**
     * @var array<Repository>
     */
    private array $repositories = [];

    /**
     * @var array<non-empty-string, callable(Container, non-empty-string, array): Repository>
     */
    private array $customCreators = [];

    public function __construct(Closure $container)
    {
        $this->container = $container();
        $this->factory = new AggregateRepositoryFactory($container);
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

        return $this->factory->createRepository($streamName, $config);
    }
}
