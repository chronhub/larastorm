<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Projector\ProjectorManager;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Storm\Serializer\ProjectorJsonSerializer;
use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Projector\InMemorySubscriptionFactory;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
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
     * @var array<string, ProjectorManagerInterface>
     */
    private array $projectors = [];

    /**
     * @var array<string, callable(Container, string, array): ProjectorManagerInterface>
     */
    private array $customCreators = [];

    public function __construct(callable $container)
    {
        $this->container = $container();
        $this->eventStoreResolver = new EventStoreResolver($container);
        $this->projectionProviderFactory = new ProjectionProviderFactory($container);
    }

    public function create(string $name): ProjectorManagerInterface
    {
        return $this->projectors[$name] ?? $this->projectors[$name] = $this->resolve($name);
    }

    /**
     * @param  callable(Container, string, array): ProjectorManagerInterface  $callback
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

    private function resolve(string $name): ProjectorManagerInterface
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

    private function createProjectorManager(string $driver, array $config): ProjectorManagerInterface
    {
        /**
         * @covers createConnectionManager
         * @covers createInMemoryManager
         */
        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Manager'));

        return $this->{$driverMethod}($config);
    }

    private function createConnectionManager(array $config): ProjectorManagerInterface
    {
        $args = $this->makeSubscription($config);

        $subscription = new ConnectionSubscriptionFactory(...$args);

        if (true === ($config['dispatcher'] ?? false)) {
            $subscription->setEventDispatcher($this->container['events']);
        }

        return new ProjectorManager($subscription);
    }

    private function createInMemoryManager(array $config): ProjectorManagerInterface
    {
        $args = $this->makeSubscription($config);

        $subscription = new InMemorySubscriptionFactory(...$args);

        return new ProjectorManager($subscription);
    }

    private function makeSubscription(array $config): array
    {
        $chronicler = $this->eventStoreResolver->resolve($config['chronicler']);

        return [
            $chronicler,
            $this->determineProjectionProvider($config['provider'] ?? null),
            $chronicler->getEventStreamProvider(),
            $this->container[$config['scope']],
            $this->container[SystemClock::class],
            $this->container[MessageAlias::class],
            new ProjectorJsonSerializer(),
            $this->determineProjectorOptions($config['options']),
        ];
    }

    private function determineProjectionProvider(?string $providerKey): ProjectionProvider
    {
        $projectionProvider = $this->container['config']["projector.providers.$providerKey"] ?? null;

        if ($projectionProvider === null) {
            throw new InvalidArgumentException('Projection provider is not defined');
        }

        return $this->projectionProviderFactory->createProvider($projectionProvider);
    }

    private function determineProjectorOptions(?string $optionKey): array|ProjectionOption
    {
        $options = $this->container['config']["projector.options.$optionKey"] ?? [];

        return is_array($options) ? $options : $this->container[$options];
    }

    private function callCustomCreator(string $name, array $config): ProjectorManagerInterface
    {
        return $this->customCreators[$name]($this->container, $name, $config);
    }
}
