<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Storm\Serializer\JsonSerializerFactory;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\ContentSerializer;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerProvider;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;
use function in_array;
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

        $this->registerSerializer();

        $this->registerBindings();

        $this->registerManagers();

        $this->registerChroniclerProvidersIfInConfiguration();
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
        if (in_array('serializer.normalizer.event_time.utc', config('chronicler.event_serializer.normalizers'))) {
            $this->app->singleton(
                'serializer.normalizer.event_time.utc',
                fn (Application $app): NormalizerInterface => new DateTimeNormalizer([
                    DateTimeNormalizer::FORMAT_KEY => $app[SystemClock::class]->getFormat(),
                    DateTimeNormalizer::TIMEZONE_KEY => 'UTC',
                ]));
        }

        $this->app->singleton(StreamEventSerializer::class, function (Application $app): StreamEventSerializer {
            $normalizers = array_map(
                fn (string $normalizer): NormalizerInterface|DenormalizerInterface => $app[$normalizer],
                config('chronicler.event_serializer.normalizers')
            );

            $contentSerializer = null;

            if ($app->bound(ContentSerializer::class)) {
                $contentSerializer = $app[ContentSerializer::class];
            }

            $serializerFactory = new JsonSerializerFactory();

            return $serializerFactory->createForStream($contentSerializer, ...$normalizers);
        });
    }

    protected function registerChroniclerProvidersIfInConfiguration(): void
    {
        $providers = config('chronicler.defaults.providers', []);

        if (isset($providers['connection']) && $providers['connection'] === ConnectionChroniclerProvider::class) {
            $this->app->singleton(
                ConnectionChroniclerProvider::class,
                fn (Application $app): ChroniclerProvider => new ConnectionChroniclerProvider(
                    fn () => $app, $app[EventStoreDatabaseFactory::class]
                )
            );
        }

        if (isset($providers['in_memory']) && $providers['in_memory'] === InMemoryChroniclerProvider::class) {
            $this->app->singleton(
                InMemoryChroniclerProvider::class,
                fn (Application $app): ChroniclerProvider => new InMemoryChroniclerProvider(fn () => $app)
            );
        }
    }

    protected function registerManagers(): void
    {
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
            'serializer.normalizer.event_time.utc',
        ];
    }
}
