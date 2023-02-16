<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\ServiceProvider;
use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Producer\LogicalProducer;
use Chronhub\Storm\Routing\RoutingRegistrar;
use Chronhub\Larastorm\Support\Facade\Report;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Illuminate\Contracts\Foundation\Application;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Illuminate\Contracts\Support\DeferrableProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;

class CqrsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function boot(): void
    {
    }

    public function register(): void
    {
        $this->app->singleton(ProducerUnity::class, LogicalProducer::class);

        $this->app->singleton(Registrar::class, RoutingRegistrar::class);

        $this->app->singleton(
            ReporterManager::class,
            fn (Application $app): ReporterManager => new CqrsManager(fn (): Application => $app)
        );

        $this->app->alias(ReporterManager::class, Report::SERVICE_ID);
    }

    public function provides(): array
    {
        return [
            ProducerUnity::class,
            Registrar::class,
            ReporterManager::class,
            Report::SERVICE_ID,
        ];
    }
}
