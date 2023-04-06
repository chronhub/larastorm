<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ProjectorServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected string $projectorPath = __DIR__.'/../../config/projector.php';

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$this->projectorPath => config_path('projector.php')],
                'config'
            );

            $console = config('projector.console') ?? [];

            if (true === ($console['load_migrations'] ?? false)) {
                $this->loadMigrationsFrom(__DIR__.'/../../database/projection');
            }

            $this->commands($console['commands'] ?? []);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->projectorPath, 'projector');

        $this->app->singleton(
            ServiceManager::class,
            fn (Application $app): ServiceManager => new ProjectorServiceManager(fn (): Application => $app)
        );

        $this->app->alias(ServiceManager::class, Project::SERVICE_ID);
    }

    public function provides(): array
    {
        return [
            ServiceManager::class,
            Project::SERVICE_ID,
        ];
    }
}
