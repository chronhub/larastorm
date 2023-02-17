<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Larastorm\Projection\ProvideProjectorServiceManager;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;

final class InMemoryProvideProjectorServiceManagerTest extends OrchestraTestCase
{
    private ProvideProjectorServiceManager $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceManager = $this->app[ProjectorServiceManager::class];
        $this->assertEquals(ProvideProjectorServiceManager::class, $this->serviceManager::class);
    }

    /**
     * @test
     */
    public function it_create_projector_manager(): void
    {
        $this->serviceManager->setDefaultDriver('in_memory');

        $projectorManager = $this->serviceManager->create('testing');

        $this->assertEquals(InMemoryProjectorManager::class, $projectorManager::class);
    }

    /**
     * @test
     */
    public function it_raise_exception_if_projector_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        $this->serviceManager->setDefaultDriver('in_memory');

        $this->serviceManager->create('foo');
    }

    /**
     * @test
     */
    public function it_raise_exception_if_projector_driver_is_not_defined(): void
    {
        $this->app['config']->set('projector.projectors.foo', [
            'testing' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name testing is not defined');

        $this->serviceManager->setDefaultDriver('foo');

        $this->serviceManager->create('testing');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
