<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;

#[CoversClass(ProjectorServiceManager::class)]
final class InMemoryProjectorServiceManagerTest extends OrchestraTestCase
{
    private ProjectorServiceManager $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceManager = $this->app[ServiceManager::class];
        $this->assertEquals(ProjectorServiceManager::class, $this->serviceManager::class);
    }

    public function testProjectorManager(): void
    {
        $this->serviceManager->setDefaultDriver('in_memory');

        $projectorManager = $this->serviceManager->create('testing');

        $this->assertEquals(InMemoryProjectorManager::class, $projectorManager::class);
    }

    public function testExceptionRaisedWhenProjectorNameIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        $this->serviceManager->setDefaultDriver('in_memory');

        $this->serviceManager->create('foo');
    }

    #[Test]
    public function testExceptionRaisedWhenProjectorDriverIsNotDefined(): void
    {
        $this->app['config']->set('projector.projectors.foo', ['testing' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name testing is not defined');

        $this->serviceManager->setDefaultDriver('foo');

        $this->serviceManager->create('testing');
    }

    public function testExceptionRaisedWhenProjectionProviderKeyIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection provider is not defined');

        $this->assertEquals('in_memory', $this->app['config']->get('projector.projectors.in_memory.testing.provider'));

        $this->app['config']->set('projector.projectors.in_memory.testing.provider', null);

        $this->assertNull($this->app['config']->get('projector.projectors.in_memory.testing.provider'));

        $this->serviceManager->setDefaultDriver('in_memory');

        $this->serviceManager->create('testing');
    }

    public function testExtendProjectorManager(): void
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
            ClockServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
