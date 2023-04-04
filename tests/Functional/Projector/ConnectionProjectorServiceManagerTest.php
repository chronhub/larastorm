<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Projector\ProjectorManager;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
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

    public function testCreateProjectorManager(): void
    {
        $this->serviceManager->setDefaultDriver('connection');

        $projectorManager = $this->serviceManager->create('default');

        $this->assertEquals(ProjectorManager::class, $projectorManager::class);
    }

    public function testExceptionRaisedWithUndefinedProjectorName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name foo is not defined');

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('foo');
    }

    public function testExceptionRaisedWithUndefinedProjectorDriver(): void
    {
        // same as above but with empty config
        $this->app['config']->set('projector.projectors.foo', ['testing' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projector configuration with name testing is not defined');

        $this->serviceManager->setDefaultDriver('foo');

        $this->serviceManager->create('testing');
    }

    public function testExceptionRaisedWithUndefinedProviderKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection provider is not defined');

        $this->assertEquals('connection', $this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->app['config']->set('projector.projectors.connection.default.provider', null);

        $this->assertNull($this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('default');
    }

    public function testExceptionRaisedWithInvalidProviderName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Projection provider connection name is not defined');

        $this->assertEquals('connection', $this->app['config']->get('projector.projectors.connection.default.provider'));

        $this->app['config']->set('projector.providers.connection', []);

        $this->serviceManager->setDefaultDriver('connection');

        $this->serviceManager->create('default');
    }

    public function testExtendManager(): void
    {
        $config = ['any_value'];

        $this->app['config']->set('projector.projectors.connection.foo', $config);

        $instance = $this->createMock(ProjectorManagerInterface::class);

        $this->serviceManager->extend(
            'foo',
            function (Container $container, string $name, array $projectorConfig) use ($instance, $config): ProjectorManagerInterface {
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
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
