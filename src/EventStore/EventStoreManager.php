<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerFactory;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use function sprintf;
use function is_string;

final class EventStoreManager implements ChroniclerManager
{
    private Application $app;

    /**
     * @var array<Chronicler>
     */
    private array $chroniclers = [];

    /**
     * @var array<string, callable(Container, string, array): Chronicler>
     */
    private array $customCreators = [];

    /**
     * @var array<string, string|ChroniclerFactory>
     */
    private array $providers = [];

    public function __construct(Closure $app)
    {
        $this->app = $app();
    }

    public function create(string $name): Chronicler
    {
        return $this->chroniclers[$name] ?? $this->chroniclers[$name] = $this->resolveEventStore($name);
    }

    /**
     * @param  callable(Container, string, array): Chronicler  $callback
     */
    public function extend(string $name, callable $callback): self
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function shouldUse(string $driver, string|ChroniclerFactory $provider): self
    {
        $this->providers[$driver] = $provider;

        $this->setDefaultDriver($driver);

        return $this;
    }

    public function addProvider(string $driver, string|ChroniclerFactory $chroniclerFactory): self
    {
        $this->providers[$driver] = $chroniclerFactory;

        return $this;
    }

    public function setDefaultDriver(string $driver): self
    {
        $this->app['config']['chronicler.defaults.provider'] = $driver;

        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->app['config']['chronicler.defaults.provider'];
    }

    private function resolveEventStore(string $name): Chronicler
    {
        $driver = $this->getDefaultDriver();

        $config = $this->app['config']["chronicler.providers.$driver.$name"];

        if ($config === null) {
            throw new InvalidArgumentException(
                sprintf('Chronicler config %s is not defined', $name)
            );
        }

        if (isset($this->customCreators[$name])) {
            return $this->callCustomCreator($name, $config);
        }

        return $this->callProvider($name, $driver, $config);
    }

    private function callProvider(string $name, string $driver, array $config): Chronicler
    {
        $provider = $this->providers[$driver] ?? null;

        if (is_string($provider)) {
            $provider = $this->providers[$driver] = $this->app[$provider];
        }

        if ($provider instanceof ChroniclerFactory) {
            return $provider->createEventStore($name, $config);
        }

        throw new InvalidArgumentException(
            sprintf('Chronicler provider with name %s and driver %s is not defined', $name, $driver)
        );
    }

    private function callCustomCreator(string $name, array $config): Chronicler
    {
        return $this->customCreators[$name]($this->app, $name, $config);
    }
}
