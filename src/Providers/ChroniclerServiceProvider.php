<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerFactory;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Serializer\JsonSerializerFactory;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ChroniclerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $chroniclerPath = __DIR__.'/../../config/chronicler.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->chroniclerPath => config_path('chronicler.php')]);

            $loadMigration = config('chronicler.console.load_migration');

            if ($loadMigration === true) {
                $this->loadMigrationsFrom(__DIR__.'/../../database/event_stream');
            }

            $this->commands(config('chronicler.console.commands', []));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->chroniclerPath, 'chronicler');

        $this->registerSerializer();

        $this->registerBindings();

        $this->registerManager();

        $this->registerProviders();
    }

    protected function registerBindings(): void
    {
        $this->app->bind(
            StreamEventLoader::class,
            fn (Application $app): EventLoader => $app[EventLoader::class]
        );

        $this->app->singleton(StreamCategory::class, fn (): StreamCategory => new DetermineStreamCategory());
    }

    protected function registerSerializer(): void
    {
        $this->app->singleton(StreamEventSerializer::class, function (Application $app): StreamEventSerializer {
            $serializerFactory = new JsonSerializerFactory(fn (): Application => $app);

            return $serializerFactory->createStreamSerializer(
                null,
                ...config('chronicler.event_serializer.normalizers', [])
            );
        });
    }

    protected function registerProviders(): void
    {
        $this->app->singleton(
            EventStoreConnectionFactory::class,
            fn (Application $app): ChroniclerFactory => new EventStoreConnectionFactory(
                fn () => $app, $app[EventStoreDatabaseFactory::class]
            )
        );

        $this->app->singleton(
            InMemoryChroniclerFactory::class,
            fn (Application $app): ChroniclerFactory => new InMemoryChroniclerFactory(fn () => $app)
        );
    }

    protected function registerManager(): void
    {
        $this->app->singleton(ChroniclerManager::class, function (Application $app): ChroniclerManager {
            $eventStoreManager = new EventStoreManager(fn (): Container => $app);

            foreach (config('chronicler.defaults.providers', []) as $driver => $provider) {
                $eventStoreManager->addProvider($driver, $provider);
            }

            return $eventStoreManager;
        });

        $this->app->alias(ChroniclerManager::class, Chronicle::SERVICE_ID);
    }

    public function provides(): array
    {
        return [
            StreamEventSerializer::class,
            StreamCategory::class,
            ChroniclerManager::class,
            Chronicle::SERVICE_ID,
            InMemoryChroniclerFactory::class,
            EventStoreConnectionFactory::class,
        ];
    }
}
