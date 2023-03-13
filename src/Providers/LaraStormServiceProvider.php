<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Illuminate\Support\AggregateServiceProvider;

class LaraStormServiceProvider extends AggregateServiceProvider
{
    protected $providers = [
        ClockServiceProvider::class,
        MessagerServiceProvider::class,
        CqrsServiceProvider::class,
        ChroniclerServiceProvider::class,
        AggregateRepositoryServiceProvider::class,
        ProjectorServiceProvider::class,
    ];
}
