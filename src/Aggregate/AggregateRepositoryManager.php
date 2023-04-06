<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Storm\Aggregate\AggregateRepository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepository as Repository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as Manager;
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
     * @var array<non-empty-string, callable(Container, non-empty-string, array): AggregateRepository>
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
            return $this->customCreators[$streamName]($this->container, $streamName, $config);
        }

        return $this->callAggregateRepository($streamName, $config);
    }

    private function callAggregateRepository(string $streamName, array $config): Repository
    {
        $driver = $config['type']['alias'] ?? null;

        /**
         * @covers createGenericRepositoryDriver
         * @covers createExtendedRepositoryDriver
         */
        $driverMethod = match ($driver) {
            'generic' => 'createGenericRepositoryDriver',
            'extended' => 'createExtendedRepositoryDriver',
            default => throw new InvalidArgumentException("Aggregate repository with stream name $streamName is not defined"),
        };

        return $this->{$driverMethod}($streamName, $config);
    }

    private function createGenericRepositoryDriver(string $streamName, array $config): Repository
    {
        return $this->factory->createRepository($streamName, $config);
    }

    private function createExtendedRepositoryDriver(string $streamName, array $config): Repository
    {
        $repositoryClass = $config['type']['concrete'] ?? null;

        if ($repositoryClass === null) {
            throw new InvalidArgumentException(
                "Missing concrete key for aggregate repository with stream name $streamName"
            );
        }

        return $this->factory->createRepository($streamName, $config, $repositoryClass);
    }
}
