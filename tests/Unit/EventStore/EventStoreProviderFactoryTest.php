<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Chronicler\InMemory\InMemoryEventStream;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;

#[CoversClass(EventStoreDatabaseFactory::class)]

final class EventStoreProviderFactoryTest extends UnitTestCase
{
    private Container $container;

    private LockFactory|MockObject $lockFactory;

    private EventLoaderConnectionFactory|MockObject $streamEventLoaderFactory;

    private Connection|MockObject $connection;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->streamEventLoaderFactory = $this->createMock(EventLoaderConnectionFactory::class);
        $this->connection = $this->createMock(Connection::class);
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_return_event_store_database(bool $isTransactional): void
    {
        $writeLock = $this->createMock(WriteLockStrategy::class);
        $eventLoader = $this->createMock(StreamEventLoaderConnection::class);
        $persistence = $this->createMock(StreamPersistence::class);
        $streamCategory = $this->createMock(StreamCategory::class);

        $this->lockFactory
            ->expects($this->once())
            ->method('createLock')
            ->with($this->connection, false)
            ->willReturn($writeLock);

        $this->streamEventLoaderFactory
            ->expects($this->once())
            ->method('createEventLoader')
            ->with('cursor')
            ->willReturn($eventLoader);

        $this->container->instance('stream.persistence.pgsql', $persistence);
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->assertFalse($this->container->bound(EventStreamProvider::class));

        $factory = new EventStoreDatabaseFactory($this->lockFactory, $this->streamEventLoaderFactory);

        $factory->setContainer($this->container);

        $instance = $factory->createStore(
            $this->connection,
            'stream.persistence.pgsql',
            'cursor',
            false,
            $isTransactional
        );

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);

        $this->assertSame($writeLock, ReflectionProperty::getProperty($instance, 'writeLock'));
        $this->assertSame($eventLoader, ReflectionProperty::getProperty($instance, 'eventLoader'));
        $this->assertSame($persistence, ReflectionProperty::getProperty($instance, 'streamPersistence'));
        $this->assertSame($streamCategory, ReflectionProperty::getProperty($instance, 'streamCategory'));
        $this->assertInstanceOf(EventStream::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_resolve_event_stream_provider_from_container_if_bound(bool $isTransactional): void
    {
        $writeLock = $this->createMock(WriteLockStrategy::class);
        $eventLoader = $this->createMock(StreamEventLoaderConnection::class);
        $persistence = $this->createMock(StreamPersistence::class);
        $streamCategory = $this->createMock(StreamCategory::class);

        $this->lockFactory
            ->expects($this->once())
            ->method('createLock')
            ->with($this->connection, true)
            ->willReturn($writeLock);

        $this->streamEventLoaderFactory
            ->expects($this->once())
            ->method('createEventLoader')
            ->with('cursor')
            ->willReturn($eventLoader);

        $this->container->instance('stream.persistence.pgsql', $persistence);
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->container->bind(EventStreamProvider::class, fn () => new InMemoryEventStream());

        $factory = new EventStoreDatabaseFactory($this->lockFactory, $this->streamEventLoaderFactory);

        $factory->setContainer($this->container);

        $instance = $factory->createStore(
            $this->connection,
            'stream.persistence.pgsql',
            'cursor',
            true,
            $isTransactional
        );

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);

        $this->assertInstanceOf(InMemoryEventStream::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
