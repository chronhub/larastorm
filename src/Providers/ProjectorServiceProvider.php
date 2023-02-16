<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Chronhub\Larastorm\Support\Facade\Project;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Larastorm\Projection\ProvideProjectorServiceManager;

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
            ProjectorServiceManager::class,
            fn (Application $app): ProjectorServiceManager => new ProvideProjectorServiceManager(fn (): Application => $app)
        );

        $this->app->alias(ProjectorServiceManager::class, Project::SERVICE_ID);
    }

    public function provides(): array
    {
        return [
            ProjectorServiceManager::class,
            Project::SERVICE_ID,
        ];
    }
}
