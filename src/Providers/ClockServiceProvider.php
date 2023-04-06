<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Providers;

use Chronhub\Larastorm\Support\Facade\Clock;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class ClockServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(SystemClock::class, PointInTime::class);

        $this->app->alias(SystemClock::class, Clock::SERVICE_ID);
    }

    public function provides(): array
    {
        return [SystemClock::class, Clock::SERVICE_ID];
    }
}
