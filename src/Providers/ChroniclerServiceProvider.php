<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Serializer\ContentSerializer;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoader;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerProvider;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Larastorm\EventStore\EventStoreProviderFactory;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader as EventLoader;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;
use function array_map;

class ChroniclerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $chroniclerPath = __DIR__.'/../../config/chronicler.php';

    protected string $repositoryPath = __DIR__.'/../../config/aggregate.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->chroniclerPath => config_path('chronicler.php')]);
            $this->publishes([$this->repositoryPath => config_path('aggregate.php')]);

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
        $this->mergeConfigFrom($this->repositoryPath, 'aggregate');

        $this->registerBindings();
        $this->registerChroniclerProvidersIfInConfiguration();

        $this->app->singleton(ChroniclerManager::class, function (Application $app): ChroniclerManager {
            $eventStoreManager = new EventStoreManager(fn (): Container => $app);

            foreach (config('chronicler.defaults.providers', []) as $driver => $provider) {
                $eventStoreManager->addProvider($driver, $provider);
            }

            return $eventStoreManager;
        });

        $this->app->alias(ChroniclerManager::class, Chronicle::SERVICE_ID);

        $this->app->singleton(
            RepositoryManager::class,
            fn (Application $app): RepositoryManager => new AggregateRepositoryManager(fn () => $app)
        );
    }

    public function provides(): array
    {
        return [
            StreamEventSerializer::class,
            StreamCategory::class,
            ChroniclerManager::class,
            Chronicle::SERVICE_ID,
            RepositoryManager::class,
            InMemoryChroniclerProvider::class,
            ConnectionChroniclerProvider::class,
        ];
    }

    protected function registerBindings(): void
    {
        $this->app->singleton(StreamEventSerializer::class, function (Application $app): StreamEventSerializer {
            $normalizers = array_map(
                fn (string $normalizer): NormalizerInterface => $app[$normalizer],
                config('chronicler.event_serializer.normalizers')
            );

            $concrete = config('chronicler.event_serializer.concrete');

            $contentSerializer = null;
            if ($app->bound(ContentSerializer::class)) {
                $contentSerializer = $app[ContentSerializer::class];
            }

            return new $concrete($contentSerializer, ...$normalizers);
        });

        $this->app->bind(
            EventLoader::class,
            fn (Application $app): EventLoader => $app[StreamEventLoader::class]
        );

        // todo move to config

        $this->app->singleton(
            StreamCategory::class,
            fn (): StreamCategory => new DetermineStreamCategory()
        );
    }

    protected function registerChroniclerProvidersIfInConfiguration(): void
    {
        $providers = config('chronicler.defaults.providers', []);

        if (isset($providers['connection']) && $providers['connection'] === ConnectionChroniclerProvider::class) {
            $this->app->singleton(
                InMemoryChroniclerProvider::class,
                fn (Application $app): InMemoryChroniclerProvider => new InMemoryChroniclerProvider(fn (): Container => $app)
            );
        }

        if (isset($providers['in_memory']) && $providers['in_memory'] === InMemoryChroniclerProvider::class) {
            $this->app->singleton(
                ConnectionChroniclerProvider::class,
                fn (Application $app): ChroniclerProvider => new ConnectionChroniclerProvider(fn () => $app, $app[EventStoreProviderFactory::class])
            );
        }
    }
}
