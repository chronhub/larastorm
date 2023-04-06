<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\LaraStormServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

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

    public function testAssertServiceProvidersNotDiscovered(): void
    {
        $loadedProviders = $this->app->getLoadedProviders();

        foreach ($this->expectedProviders as $expectedProvider) {
            $this->assertArrayNotHasKey($expectedProvider, $loadedProviders);
        }

        $serviceProvider = $this->app->getProvider(LaraStormServiceProvider::class);
        $this->assertNull($serviceProvider);
    }

    public function testAssertServiceProvidersRegistered(): void
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
