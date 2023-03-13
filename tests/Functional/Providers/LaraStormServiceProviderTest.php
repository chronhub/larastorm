<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\LaraStormServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;

#[CoversClass(LaraStormServiceProvider::class)]
class LaraStormServiceProviderTest extends OrchestraTestCase
{
    private array $expectedProviders = [
        MessagerServiceProvider::class,
        CqrsServiceProvider::class,
        ChroniclerServiceProvider::class,
        AggregateRepositoryServiceProvider::class,
        ProjectorServiceProvider::class,
    ];

    #[Test]
    public function it_assert_service_providers_not_registered(): void
    {
        $loadedProviders = $this->app->getLoadedProviders();

        foreach ($this->expectedProviders as $expectedProvider) {
            $this->assertArrayNotHasKey($expectedProvider, $loadedProviders);
        }

        $serviceProvider = $this->app->getProvider(LaraStormServiceProvider::class);
        $this->assertNull($serviceProvider);
    }

    #[Test]
    public function it_assert_service_providers_registered(): void
    {
        $this->app->register(LaraStormServiceProvider::class);

        $serviceProvider = $this->app->getProvider(LaraStormServiceProvider::class);
        $this->assertInstanceOf(LaraStormServiceProvider::class, $serviceProvider);

        $serviceProvider->register();

        $loadedProviders = $this->app->getLoadedProviders();

        foreach ($this->expectedProviders as $expectedProvider) {
            $this->assertArrayHasKey($expectedProvider, $loadedProviders);
        }
    }
}
