<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Serializer\ConvertStreamEvent;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Serializer\ContentSerializer;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoader;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerProvider;
use Chronhub\Larastorm\EventStore\EventStoreProviderFactory;
use Chronhub\Storm\Contracts\Serializer\StreamEventConverter;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader as EventLoader;
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
                $this->loadMigrationsFrom(__DIR__.'/../../database');
            }

            $this->commands(config('chronicler.console.commands', []));
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->chroniclerPath, 'chronicler');
        $this->mergeConfigFrom($this->repositoryPath, 'aggregate');

        $this->registerBindings();
        $this->registerChroniclerProviders();

        $this->app->singleton(ChroniclerManager::class, function (Application $app): ChroniclerManager {
            return new EventStoreManager(fn (): Container => $app);
        });
    }

    public function provides(): array
    {
        return [
            StreamEventSerializer::class,
            StreamCategory::class,
            StreamEventConverter::class,
            ChroniclerManager::class,
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

        // todo move to config

        $this->app->singleton(
            StreamCategory::class, fn (): StreamCategory => new DetermineStreamCategory()
        );

        $this->app->singleton(
            StreamEventConverter::class, fn (Application $app): StreamEventConverter => $app[ConvertStreamEvent::class]
        );

        $this->app->bind(EventLoader::class, fn (Application $app): EventLoader => $app[StreamEventLoader::class]);
    }

    private function registerChroniclerProviders(): void
    {
        $this->app->singleton(
            InMemoryChroniclerProvider::class,
            fn (Application $app): InMemoryChroniclerProvider => new InMemoryChroniclerProvider(fn (): Container => $app)
        );

        $this->app->singleton(
            ConnectionChroniclerProvider::class,
            fn (Application $app): ChroniclerProvider => new ConnectionChroniclerProvider(fn () => $app, $app[EventStoreProviderFactory::class])
        );
    }
}
