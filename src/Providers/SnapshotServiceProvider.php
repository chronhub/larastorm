<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\Snapshot\SnapshotStoreManager;
use Chronhub\Larastorm\Support\Contracts\SnapshotStoreManager as StoreManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SnapshotServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $repositoryPath = __DIR__.'/../../config/snapshot.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->repositoryPath => config_path('snapshot.php')]);
        }

        $loadMigration = config('snapshot.connection.console.load_migration');

        if ($loadMigration === true) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/snapshot');
        }

        $this->commands(config('snapshot.connection.console.commands', []));
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->repositoryPath, 'snapshot');

        $this->app->singleton(
            StoreManager::class,
            fn (Application $app) => new SnapshotStoreManager(fn () => $app)
        );

    }

    public function provides(): array
    {
        return [StoreManager::class];
    }
}
