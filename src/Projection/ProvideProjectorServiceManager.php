<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use function ucfirst;
use function is_array;
use function is_string;

final class ProvideProjectorServiceManager implements ProjectorServiceManager
{
    private Container $container;

    private EventStoreResolver $eventStoreResolver;

    /**
     * @var array<string, ProjectorManager>
     */
    private array $projectors = [];

    /**
     * @var array<string, callable>
     */
    private array $customCreators = [];

    public function __construct(callable $container)
    {
        $this->container = $container();
        $this->eventStoreResolver = new EventStoreResolver($container);
    }

    public function create(string $name): ProjectorManager
    {
        return $this->projectors[$name] ?? $this->projectors[$name] = $this->resolve($name);
    }

    public function extend(string $name, callable $callback): ProjectorServiceManager
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function setDefaultDriver(string $driver): self
    {
        $this->container['config']['projector.defaults.projector'] = $driver;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->container['config']['projector.defaults.projector'];
    }

    private function resolve(string $name): ProjectorManager
    {
        $driver = $this->getDefaultDriver();

        $config = $this->container['config']["projector.projectors.$driver.$name"];

        if (empty($config)) {
            throw new InvalidArgumentException("Projector configuration with name $name is not defined");
        }

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        return $this->createProjectorManager($driver, $config);
    }

    private function createProjectorManager(string $driver, array $config): ProjectorManager
    {
        /**
         * @covers createConnectionManager
         * @covers createInMemoryManager
         */
        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Manager'));

        return $this->{$driverMethod}($config);
    }

    private function createConnectionManager(array $config): ProjectorManager
    {
        $chronicler = $this->eventStoreResolver->resolve($config['chronicler']);

        return new ConnectionProjectorManager(
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->container[$config['scope']],
            $this->container[SystemClock::class],
            $this->determineProjectorOptions($config['options']),
        );
    }

    private function createInMemoryManager(array $config): ProjectorManager
    {
        $chronicler = $this->eventStoreResolver->resolve($config['chronicler']);

        return new InMemoryProjectorManager(
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->container[$config['scope']],
            $this->container[SystemClock::class],
            $this->determineProjectorOptions($config['options']),
        );
    }

    private function determineProjectionProvider(?string $providerKey): ProjectionProvider
    {
        $projectionProvider = $this->container['config']["projector.providers.$providerKey"] ?? null;

        if (! is_string($projectionProvider)) {
            throw new InvalidArgumentException('Projector provider key is not defined');
        }

        return $this->container[$projectionProvider];
    }

    protected function determineProjectorOptions(?string $optionKey): array|ProjectorOption
    {
        $options = $this->container['config']["projector.options.$optionKey"] ?? [];

        return is_array($options) ? $options : $this->container[$options];
    }

    private function callCustomCreator(string $name, array $config): ProjectorManager
    {
        return $this->customCreators[$name]($this->container, $name, $config);
    }
}
