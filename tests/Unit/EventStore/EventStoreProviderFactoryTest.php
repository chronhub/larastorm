<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Chronicler\InMemory\InMemoryEventStream;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderFactory;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;

final class EventStoreProviderFactoryTest extends ProphecyTestCase
{
    private Container $container;

    private ObjectProphecy|LockFactory $lockFactory;

    private ObjectProphecy|EventLoaderFactory $streamEventLoaderFactory;

    private Connection|ObjectProphecy $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();
        $this->lockFactory = $this->prophesize(LockFactory::class);
        $this->streamEventLoaderFactory = $this->prophesize(EventLoaderFactory::class);
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_return_event_store_database(bool $isTransactional): void
    {
        $writeLock = $this->prophesize(WriteLockStrategy::class)->reveal();
        $this->lockFactory
            ->createLock($this->connection->reveal(), false)
            ->willReturn($writeLock)
            ->shouldBeCalledOnce();

        $eventLoader = $this->prophesize(StreamEventLoaderConnection::class)->reveal();
        $this->streamEventLoaderFactory
            ->createEventLoader('cursor')
            ->willReturn($eventLoader)
            ->shouldBeCalledOnce();

        $persistence = $this->prophesize(StreamPersistence::class)->reveal();
        $this->container->instance('stream.persistence.pgsql', $persistence);

        $streamCategory = $this->prophesize(StreamCategory::class)->reveal();
        $this->container->instance(StreamCategory::class, $streamCategory);
        $this->assertFalse($this->container->bound(EventStreamProvider::class));

        $factory = new EventStoreDatabaseFactory(
            $this->lockFactory->reveal(),
            $this->streamEventLoaderFactory->reveal()
        );

        $factory->setContainer($this->container);

        $instance = $factory->createStore(
            $this->connection->reveal(),
            'stream.persistence.pgsql',
            'cursor',
            false,
            $isTransactional
        );

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabaseDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);

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
        $writeLock = $this->prophesize(WriteLockStrategy::class)->reveal();
        $this->lockFactory
            ->createLock($this->connection->reveal(), true)
            ->willReturn($writeLock)
            ->shouldBeCalledOnce();

        $eventLoader = $this->prophesize(StreamEventLoaderConnection::class)->reveal();
        $this->streamEventLoaderFactory
            ->createEventLoader('cursor')
            ->willReturn($eventLoader)
            ->shouldBeCalledOnce();

        $persistence = $this->prophesize(StreamPersistence::class)->reveal();
        $this->container->instance('stream.persistence.pgsql', $persistence);

        $streamCategory = $this->prophesize(StreamCategory::class)->reveal();
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->container->bind(EventStreamProvider::class, fn () => new InMemoryEventStream());

        $factory = new EventStoreDatabaseFactory(
            $this->lockFactory->reveal(),
            $this->streamEventLoaderFactory->reveal(),
        );

        $factory->setContainer($this->container);

        $instance = $factory->createStore(
            $this->connection->reveal(),
            'stream.persistence.pgsql',
            'cursor',
            true,
            $isTransactional
        );

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabaseDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);

        $this->assertInstanceOf(InMemoryEventStream::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
