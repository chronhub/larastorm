<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Larastorm\Projection\ProvideProjectorServiceManager;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;

#[CoversClass(ProvideProjectorServiceManager::class)]
final class InMemoryProvideProjectorServiceManagerTest extends OrchestraTestCase
{
    private ProvideProjectorServiceManager $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceManager = $this->app[ProjectorServiceManager::class];
        $this->assertEquals(ProvideProjectorServiceManager::class, $this->serviceManager::class);
    }

    #[Test]
    public function it_create_projector_manager(): void
    {
        $this->serviceManager->setDefaultDriver('in_memory');

        $projectorManager = $this->serviceManager->create('testing');

        $this->assertEquals(InMemoryProjectorManager::class, $projectorManager::class);
    }

    #[Test]
    public function it_raise_exception_if_projector_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        $this->serviceManager->setDefaultDriver('in_memory');

        $this->serviceManager->create('foo');
    }

    #[Test]
    public function it_raise_exception_if_projector_driver_is_not_defined(): void
    {
        $this->app['config']->set('projector.projectors.foo', ['testing' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name testing is not defined');

        $this->serviceManager->setDefaultDriver('foo');

        $this->serviceManager->create('testing');
    }

    #[Test]
    public function it_raise_exception_if_projection_provider_key_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector provider key is not defined');

        $this->assertEquals('in_memory', $this->app['config']->get('projector.projectors.in_memory.testing.provider'));

        $this->app['config']->set('projector.projectors.in_memory.testing.provider', null);

        $this->assertNull($this->app['config']->get('projector.projectors.in_memory.testing.provider'));

        $this->serviceManager->setDefaultDriver('in_memory');

        $this->serviceManager->create('testing');
    }

    #[Test]
    public function it_can_extend_manager(): void
    {
        $config = ['any_value'];

        $this->app['config']->set('projector.projectors.in_memory.foo', $config);

        $instance = $this->createMock(ProjectorManager::class);

        $this->serviceManager->extend(
            'foo',
            function (Container $container, string $name, array $projectorConfig) use ($instance, $config): ProjectorManager {
                TestCase::assertEquals($container, $this->app);
                TestCase::assertEquals('foo', $name);
                TestCase::assertEquals($projectorConfig, $config);

                return $instance;
            });

        $this->serviceManager->setDefaultDriver('in_memory');

        $projectorManager = $this->serviceManager->create('foo');

        $this->assertSame($instance, $projectorManager);
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
