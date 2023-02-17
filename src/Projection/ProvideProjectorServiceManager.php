<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
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
use function method_exists;

final class ProvideProjectorServiceManager implements ProjectorServiceManager
{
    private Container $app;

    /**
     * @var array<string, ProjectorManager>
     */
    private array $projectors = [];

    /**
     * @var array<string, callable>
     */
    private array $customCreators = [];

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

    public function setDefaultDriver(string $driver): self
    {
        $this->app['config']['projector.defaults.projector'] = $driver;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['projector.defaults.projector'];
    }

    private function resolve(string $name): ProjectorManager
    {
        $driver = $this->getDefaultDriver();

        $config = $this->app['config']["projector.projectors.$driver.$name"];

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

        if (! method_exists($this, $driverMethod)) {
            throw new InvalidArgumentException("Projector driver $driver is not define");
        }

        return $this->{$driverMethod}($config);
    }

    private function createConnectionManager(array $config): ProjectorManager
    {
        $chronicler = $this->determineChronicler($config);

        return new ConnectionProjectorManager(
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->app[$config['scope']],
            $this->app[SystemClock::class],
            $this->determineProjectorOptions($config['options']),
        );
    }

    private function createInMemoryManager(array $config): ProjectorManager
    {
        $chronicler = $this->determineChronicler($config);

        return new InMemoryProjectorManager(
            $chronicler,
            $chronicler->getEventStreamProvider(),
            $this->determineProjectionProvider($config['provider'] ?? null),
            $this->app[$config['scope']],
            $this->app[SystemClock::class],
            $this->determineProjectorOptions($config['options']),
        );
    }

    private function determineChronicler(array $config): Chronicler
    {
        $chronicler = $config['chronicler'];

        if (is_string($chronicler) && $this->app->bound($chronicler)) {
            return $this->app[$chronicler];
        }

        if (is_array($chronicler)) {
            [$driver, $name] = $chronicler;

            return $this->app[ChroniclerManager::class]->setDefaultDriver($driver)->create($name);
        }

        throw new InvalidArgumentException('Event store from projector configuration is not defined');
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
