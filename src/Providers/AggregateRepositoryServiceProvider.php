<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AggregateRepositoryServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $repositoryPath = __DIR__.'/../../config/aggregate.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->repositoryPath => config_path('aggregate.php')]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->repositoryPath, 'aggregate');

        $this->app->singleton(
            RepositoryManager::class,
            fn (Application $app): RepositoryManager => new AggregateRepositoryManager(fn () => $app)
        );
    }

    public function provides(): array
    {
        return [RepositoryManager::class];
    }
}
