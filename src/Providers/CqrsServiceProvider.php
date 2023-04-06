<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Larastorm\Support\Facade\Report;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Producer\LogicalProducer;
use Chronhub\Storm\Routing\GroupRegistrar;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CqrsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(ProducerUnity::class, LogicalProducer::class);

        $this->app->singleton(Registrar::class, GroupRegistrar::class);

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
