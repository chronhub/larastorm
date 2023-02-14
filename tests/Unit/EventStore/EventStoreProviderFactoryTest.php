<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\StoreDatabase;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Chronicler\InMemory\InMemoryEventStream;
use Chronhub\Larastorm\EventStore\EventStoreProviderFactory;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\StoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\WriteLock\WriteLockFactory;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoaderFactory;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Persistence\StreamPersistenceFactory;

final class EventStoreProviderFactoryTest extends ProphecyTestCase
{
    private Container $container;

    private ObjectProphecy|WriteLockFactory $writeLockFactory;

    private ObjectProphecy|StreamEventLoaderFactory $streamEventLoaderFactory;

    private StreamPersistenceFactory|ObjectProphecy $streamPersistenceFactory;

    private Connection|ObjectProphecy $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();
        $this->writeLockFactory = $this->prophesize(WriteLockFactory::class);
        $this->streamEventLoaderFactory = $this->prophesize(StreamEventLoaderFactory::class);
        $this->streamPersistenceFactory = $this->prophesize(StreamPersistenceFactory::class);
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_return_event_store_database(bool $isTransactional): void
    {
        $config = [
            'strategy' => 'single',
            'query_loader' => 'cursor',
        ];

        $writeLock = $this->prophesize(WriteLockStrategy::class)->reveal();
        $this->writeLockFactory
            ->__invoke($this->connection->reveal(), $config)
            ->willReturn($writeLock)
            ->shouldBeCalledOnce();

        $eventLoader = $this->prophesize(StreamEventLoaderConnection::class)->reveal();
        $this->streamEventLoaderFactory
            ->__invoke('cursor')
            ->willReturn($eventLoader)
            ->shouldBeCalledOnce();

        $persistence = $this->prophesize(StreamPersistence::class)->reveal();
        $this->streamPersistenceFactory
            ->__invoke('read', $this->connection->reveal(), 'single')
            ->willReturn($persistence)
            ->shouldBeCalledOnce();

        $streamCategory = $this->prophesize(StreamCategory::class)->reveal();
        $this->container->instance(StreamCategory::class, $streamCategory);
        $this->assertFalse($this->container->bound(EventStreamProvider::class));

        $factory = new EventStoreProviderFactory(
            $this->writeLockFactory->reveal(),
            $this->streamEventLoaderFactory->reveal(),
            $this->streamPersistenceFactory->reveal()
        );

        $factory->setContainer($this->container);

        $instance = $factory($this->connection->reveal(), 'read', $config, $isTransactional);

        if ($isTransactional) {
            $this->assertInstanceOf(StoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(StoreDatabase::class, $instance);
        }

        $this->assertSame($writeLock, ReflectionProperty::getProperty($instance, 'writeLock'));
        $this->assertSame($eventLoader, ReflectionProperty::getProperty($instance, 'eventLoader'));
        $this->assertSame($persistence, ReflectionProperty::getProperty($instance, 'streamPersistence'));
        $this->assertSame($streamCategory, ReflectionProperty::getProperty($instance, 'streamCategory'));
        $this->assertInstanceOf(EventStream::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_resolve_event_stream_provider_from_container_if_bound(bool $isTransactional): void
    {
        $config = [
            'strategy' => 'single',
            'query_loader' => 'cursor',
            'write_lock' => true,
        ];

        $writeLock = $this->prophesize(WriteLockStrategy::class)->reveal();
        $this->writeLockFactory
            ->__invoke($this->connection->reveal(), $config)
            ->willReturn($writeLock)
            ->shouldBeCalledOnce();

        $eventLoader = $this->prophesize(StreamEventLoaderConnection::class)->reveal();
        $this->streamEventLoaderFactory
            ->__invoke('cursor')
            ->willReturn($eventLoader)
            ->shouldBeCalledOnce();

        $persistence = $this->prophesize(StreamPersistence::class)->reveal();
        $this->streamPersistenceFactory
            ->__invoke('read', $this->connection->reveal(), 'single')
            ->willReturn($persistence)
            ->shouldBeCalledOnce();

        $streamCategory = $this->prophesize(StreamCategory::class)->reveal();
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->container->bind(EventStreamProvider::class, fn () => new InMemoryEventStream());

        $factory = new EventStoreProviderFactory(
            $this->writeLockFactory->reveal(),
            $this->streamEventLoaderFactory->reveal(),
            $this->streamPersistenceFactory->reveal()
        );

        $factory->setContainer($this->container);

        $instance = $factory($this->connection->reveal(), 'read', $config, $isTransactional);

        if ($isTransactional) {
            $this->assertInstanceOf(StoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(StoreDatabase::class, $instance);
        }

        $this->assertInstanceOf(InMemoryEventStream::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
