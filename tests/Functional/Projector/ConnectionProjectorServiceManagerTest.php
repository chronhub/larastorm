<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Container\Container;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Projection\ConnectionProjectorManager;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;

final class ConnectionProjectorServiceManagerTest extends OrchestraTestCase
{
    private ProjectorServiceManager $serviceManager;

    protected function setUp(): void
    {
        parent::setUp();

        // we remove subscribers from chronicler config for basic test
        $this->app['config']->set('chronicler.providers.connection.publish.tracking.subscribers', []);

        $this->serviceManager = $this->app[ServiceManager::class];
        $this->assertEquals(ProjectorServiceManager::class, $this->serviceManager::class);
    }

    #[Test]
    public function it_create_projector_manager(): void
    {
        $this->serviceManager->setDefaultDriver('connection');

        $projectorManager = $this->serviceManager->create('default');

        $this->assertEquals(ConnectionProjectorManager::class, $projectorManager::class);
    }

    #[Test]
    public function it_raise_exception_if_projector_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('foo');
    }

    #[Test]
    public function it_raise_exception_if_projector_driver_is_not_defined(): void
    {
        // same as above but with empty config
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
        $this->expectExceptionMessage('Projection provider is not defined');

        $this->assertEquals('connection', $this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->app['config']->set('projector.projectors.connection.default.provider', null);

        $this->assertNull($this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('default');
    }

    #[Test]
    public function it_raise_exception_if_projection_provider_key_is_array_and_connection_key_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection provider connection name is not defined');

        $this->assertEquals('connection', $this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->app['config']->set('projector.providers.connection', []);

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('default');
    }

    #[Test]
    public function it_can_extend_manager(): void
    {
        $config = ['any_value'];

        $this->app['config']->set('projector.projectors.connection.foo', $config);

        $instance = $this->createMock(ProjectorManager::class);

        $this->serviceManager->extend(
            'foo',
            function (Container $container, string $name, array $projectorConfig) use ($instance, $config): ProjectorManager {
                TestCase::assertEquals($container, $this->app);
                TestCase::assertEquals('foo', $name);
                TestCase::assertEquals($projectorConfig, $config);

                return $instance;
            });

        $this->serviceManager->setDefaultDriver('connection');

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
