<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProviderFactory;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider as Provider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(EventStreamProviderFactory::class)]
class EventStreamProviderFactoryTest extends UnitTestCase
{
    private string $configPath = 'chronicler.defaults.event_stream_provider';

    private Container $container;

    private Connection|MockObject $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->container = Container::getInstance();
        $this->container->instance('config', new Repository([
            'chronicler' => [
                'defaults' => [
                    'event_stream_provider' => [
                        'connection' => null,
                    ],
                ],
            ],
        ]));
    }

    public function testMakeDefaultInstance(): void
    {
        $factory = new EventStreamProviderFactory($this->container);

        /** @var EventStreamProvider $provider */
        $provider = $factory->createProvider($this->connection, null);

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertInstanceOf(EventStreamProvider::class, $provider);
        $this->assertEquals($provider->tableName, $provider::TABLE_NAME);

        $connection = ReflectionProperty::getProperty($provider, 'connection');
        $this->assertSame($this->connection, $connection);
    }

    public function testMakeInstanceWithProvidedKey(): void
    {
        $this->container['config']->set($this->configPath.'.foo', [
            'table_name' => 'foo',
        ]);

        $factory = new EventStreamProviderFactory($this->container);

        /** @var EventStreamProvider $provider */
        $provider = $factory->createProvider($this->connection, 'foo');

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertInstanceOf(EventStreamProvider::class, $provider);
        $this->assertEquals('foo', $provider->tableName);

        $connection = ReflectionProperty::getProperty($provider, 'connection');
        $this->assertSame($this->connection, $connection);
    }

    public function testMakeInstanceWithDefaultConnection(): void
    {
        $this->container['config']->set($this->configPath.'.connection', [
            'table_name' => 'foo_bar',
        ]);

        $factory = new EventStreamProviderFactory($this->container);

        /** @var EventStreamProvider $provider */
        $provider = $factory->createProvider($this->connection, null);

        $this->assertInstanceOf(Provider::class, $provider);
        $this->assertInstanceOf(EventStreamProvider::class, $provider);
        $this->assertEquals('foo_bar', $provider->tableName);

        $connection = ReflectionProperty::getProperty($provider, 'connection');
        $this->assertSame($this->connection, $connection);
    }

    public function testResolveProviderInIoc(): void
    {
        $this->container['config']->set($this->configPath.'.connection', 'event_stream_provider.id');

        $mock = $this->createMock(Provider::class);
        $this->container->instance('event_stream_provider.id', $mock);

        $factory = new EventStreamProviderFactory($this->container);

        $provider = $factory->createProvider($this->connection, 'connection');

        $this->assertSame($provider, $mock);
    }
}
