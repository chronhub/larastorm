<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Closure;
use InvalidArgumentException;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerProvider;
use function is_string;

final class EventStoreManager implements ChroniclerManager
{
    /**
     * @var Application
     */
    private Application $app;

    /**
     * @var array<Chronicler>
     */
    private array $chroniclers = [];

    /**
     * @var array<string, callable>
     */
    private array $customCreators = [];

    /**
     * @var array<string, string|ChroniclerProvider>
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

    public function extend(string $name, callable $callback): static
    {
        $this->customCreators[$name] = $callback;

        return $this;
    }

    public function shouldUse(string $driver, string|ChroniclerProvider $provider): static
    {
        $this->providers[$driver] = $provider;

        $this->setDefaultDriver($driver);

        return $this;
    }

    /**
     * Set default driver
     *
     * @param  string  $driver
     * @return self
     */
    public function setDefaultDriver(string $driver): self
    {
        $this->app['config']['chronicler.defaults.provider'] = $driver;

        return $this;
    }

    /**
     * Get default driver
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->app['config']['chronicler.defaults.provider'];
    }

    private function resolveEventStore(string $name): Chronicler
    {
        $driver = $this->getDefaultDriver();

        $config = $this->app['config']["chronicler.providers.$driver.$name"];

        if ($config === null) {
            throw new InvalidArgumentException("Chronicler config $name is not defined");
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

        if ($provider instanceof ChroniclerProvider) {
            return $provider->resolve($name, $config);
        }

        throw new InvalidArgumentException("Chronicler provider with name $name and driver $driver is not defined");
    }

    /**
     * Resolve custom chronicler
     *
     * @param  string  $name
     * @param  array  $config
     * @return Chronicler
     */
    private function callCustomCreator(string $name, array $config): Chronicler
    {
        return $this->customCreators[$name]($this->app, $name, $config);
    }
}
