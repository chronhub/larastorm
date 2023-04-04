<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Larastorm\Support\Facade\Clock;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\Providers\ClockServiceProvider;

#[CoversClass(ClockServiceProvider::class)]
class ClockServiceProviderTest extends OrchestraTestCase
{
    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(SystemClock::class));
        $this->assertTrue($this->app->bound(Clock::SERVICE_ID));
        $this->assertInstanceOf(PointInTime::class, $this->app[SystemClock::class]);
    }

    public function testProvides(): void
    {
        $provider = $this->app->getProvider(ClockServiceProvider::class);

        $this->assertEquals([
            SystemClock::class,
            Clock::SERVICE_ID,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [ClockServiceProvider::class];
    }
}
