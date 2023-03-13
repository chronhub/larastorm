<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Illuminate\Support\AggregateServiceProvider;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;

class LaraStormServiceProvider extends AggregateServiceProvider
{
    protected $providers = [
        MessagerServiceProvider::class,
        CqrsServiceProvider::class,
        AggregateRepositoryServiceProvider::class,
        ProjectorServiceProvider::class,
    ];
}
