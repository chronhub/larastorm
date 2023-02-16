<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Producer\LogicalProducer;
use Chronhub\Storm\Routing\RoutingRegistrar;
use Chronhub\Larastorm\Support\Facade\Report;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

final class CqrsServiceProviderTest extends OrchestraTestCase
{
    /**
     * @test
     */
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(ProducerUnity::class));
        $this->assertInstanceOf(LogicalProducer::class, $this->app[ProducerUnity::class]);

        $this->assertTrue($this->app->bound(Registrar::class));
        $this->assertInstanceOf(RoutingRegistrar::class, $this->app[Registrar::class]);

        $this->assertTrue($this->app->bound(ReporterManager::class));
        $this->assertInstanceOf(CqrsManager::class, $this->app[ReporterManager::class]);

        $this->assertTrue($this->app->bound(Report::SERVICE_ID));
        $this->assertEquals(Report::getFacadeRoot(), $this->app[ReporterManager::class]);
    }

    /**
     * @test
     */
    public function it_assert_provides(): void
    {
        $provider = $this->app->getProvider(CqrsServiceProvider::class);

        $this->assertEquals([
            ProducerUnity::class,
            Registrar::class,
            ReporterManager::class,
            Report::SERVICE_ID,
        ], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            CqrsServiceProvider::class,
        ];
    }
}
