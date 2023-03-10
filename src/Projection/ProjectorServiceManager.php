<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Storm\Serializer\ProjectorJsonSerializer;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;
use function ucfirst;
use function is_array;

final class ProjectorServiceManager implements ServiceManager
{
    private Container $container;

    private readonly EventStoreResolver $eventStoreResolver;

    private readonly ProjectionProviderFactory $projectionProviderFactory;

    /**
     * @var array<string, ProjectorManager>
     */
    private array $projectors = [];

    /**
     * @var array<string, callable(Container, string, array): ProjectorManager>
     */
    private array $customCreators = [];

    public function __construct(callable $container)
    {
        $this->container = $container();
        $this->eventStoreResolver = new EventStoreResolver($container);
        $this->projectionProviderFactory = new ProjectionProviderFactory($container);
    }

    public function create(string $name): ProjectorManager
    {
        return $this->projectors[$name] ?? $this->projectors[$name] = $this->resolve($name);
    }

    /**
     * @param  callable(Container, string, array): ProjectorManager  $callback
     */
    public function extend(string $name, callable $callback): ServiceManager
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
        return $this->createProjectorManagerInstance(ConnectionProjectorManager::class, $config);
    }

    private function createInMemoryManager(array $config): ProjectorManager
    {
        return $this->createProjectorManagerInstance(InMemoryProjectorManager::class, $config);
    }

    /**
     * @param  class-string  $manager
     */
    private function createProjectorManagerInstance(string $manager, array $config): ProjectorManager
    {
        $chronicler = $this->eventStoreResolver->resolve($config['chronicler']);

        $projectorManager = new $manager(
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->container[$config['scope']],
            $this->container[SystemClock::class],
            new ProjectorJsonSerializer(),
            $this->determineProjectorOptions($config['options']),
        );

        if (true === ($config['dispatcher'] ?? false)) {
            $projectorManager->setEventDispatcher($this->container['events']);
        }

        return $projectorManager;
    }

    private function determineProjectionProvider(?string $providerKey): ProjectionProvider
    {
        $projectionProvider = $this->container['config']["projector.providers.$providerKey"] ?? null;

        if ($projectionProvider === null) {
            throw new InvalidArgumentException('Projection provider is not defined');
        }

        return $this->projectionProviderFactory->createProvider($projectionProvider);
    }

    private function determineProjectorOptions(?string $optionKey): array|ProjectorOption
    {
        $options = $this->container['config']["projector.options.$optionKey"] ?? [];

        return is_array($options) ? $options : $this->container[$options];
    }

    private function callCustomCreator(string $name, array $config): ProjectorManager
    {
        return $this->customCreators[$name]($this->container, $name, $config);
    }
}
