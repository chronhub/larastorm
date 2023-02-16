<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Projector\ProjectorManagerFactory;
use Chronhub\Storm\Projector\InMemoryProjectorFactory;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use function ucfirst;
use function is_array;
use function is_string;
use function is_callable;
use function method_exists;

final class ProvideProjectorServiceManager implements ProjectorServiceManager
{
    private Container $app;

    /**
     * @var array<string, ProjectorManager>
     */
    private array $projectors = [];

    /**
     * @var array<string, Closure>
     */
    private array $customCreators = [];

    /**
     * @var array<string, string|ProjectorManagerFactory>
     */
    private array $factories = [];

    public function __construct(Closure $app)
    {
        $this->app = $app();
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

    public function shouldUse(string $driver, string|callable $factory): ProjectorServiceManager
    {
        $this->factories[$driver] = $factory;

        $this->setDefaultDriver($driver);

        return $this;
    }

    public function setDefaultDriver(string $driver): self
    {
        $this->app['config']['projector.defaults.factory'] = $driver;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['projector.defaults.factory'];
    }

    private function resolve(string $name): ProjectorManager
    {
        $driver = $this->getDefaultDriver();

        $config = $this->app['config']["projector.projectors.$driver.$name"];

        if ($config === null) {
            throw new InvalidArgumentException("Projector configuration $name is not defined");
        }

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        return $this->createProjectorManager($driver, $config);
    }

    private function createProjectorManager(string $driver, array $config): ProjectorManager
    {
        $factory = $this->callFactory($driver, $config);

        /**
         * @covers createConnectionManager
         * @covers createInMemoryManager
         */
        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Manager'));

        if (method_exists($this, $driverMethod)) {
            return $this->{$driverMethod}($factory);
        }

        throw new InvalidArgumentException("Projector service manager $driver is not defined");
    }

    private function createConnectionManager(ProjectorManagerFactory $managerFactory): ProjectorManager
    {
        return new ConnectionProjectorManager($managerFactory);
    }

    private function createInMemoryManager(ProjectorManagerFactory $managerFactory): ProjectorManager
    {
        return new InMemoryProjectorManager($managerFactory);
    }

    private function createConnectionFactory(...$args): ProjectorManagerFactory
    {
        return new ConnectionProjectorFactory(...$args);
    }

    private function createInMemoryFactory(...$args): ProjectorManagerFactory
    {
        return new InMemoryProjectorFactory(...$args);
    }

    private function callFactory(string $driver, array $config): ProjectorManagerFactory
    {
        $factory = $this->factories[$driver] ?? null;

        if (is_callable($factory)) {
            throw new \InvalidArgumentException('callable projector factory not supported right now');
        }

        if ($factory instanceof ProjectorManagerFactory) {
            return $factory;
        }

        $chronicler = $this->determineChronicler($config);

        $args = [
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->app[$config['scope']],
            $this->app[SystemClock::class],
            $this->determineProjectorOptions($config['options']),
        ];

        /**
         * @covers createConnectionFactory
         * @covers createInMemoryFactory
         */
        $driverMethod = 'create'.ucfirst(Str::camel($driver.'Factory'));

        if (method_exists($this, $driverMethod)) {
            return $this->factories[$driver] = $this->{$driverMethod}(...$args);
        }

        throw new InvalidArgumentException("Projector manager factory $driver is not defined");
    }

    private function determineChronicler(array $config): Chronicler
    {
        $chronicler = $config['chronicler'];

        if (is_string($chronicler) && $this->app->bound($chronicler)) {
            return $this->app[$chronicler];
        }

        [$driver, $name] = $chronicler;

        return $this->app[ChroniclerManager::class]->setDefaultDriver($driver)->create($name);
    }

    private function determineProjectionProvider(?string $providerKey): ProjectionProvider
    {
        $projectionProvider = $this->app['config']["projector.providers.$providerKey"] ?? null;

        if (! is_string($projectionProvider)) {
            throw new InvalidArgumentException('Projector provider key is not defined');
        }

        return $this->app[$projectionProvider];
    }

    protected function determineProjectorOptions(?string $optionKey): array|ProjectorOption
    {
        $options = $this->app['config']["projector.options.$optionKey"] ?? [];

        return is_array($options) ? $options : $this->app[$options];
    }

    private function callCustomCreator(string $name, array $config): ProjectorManager
    {
        return $this->customCreators[$name]($this->app, $name, $config);
    }
}
